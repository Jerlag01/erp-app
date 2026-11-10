<?php

namespace App\Actions\Gpw;

use App\Enums\GpwStatus;
use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\GpwRequest;
use App\Models\GpwRequestKey;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkSiteContact;
use App\Support\ClientPortal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Asks contact persons for a GPW in a period: the requester picks who it goes to — any contact person
 * with an e-mail address (the site's own contacts are suggested, then those of its area), also people
 * without a client portal login, who decide with the buttons in the mail. The request is stored with them
 * and mailed to each, in their language, with a copy to the requester. The mail links to a page
 * where the contact approves or refuses it; client portal users who see the project can do so too.
 */
class RequestGpw
{
    /** Whether the last request could be mailed. */
    public bool $mailFailed = false;

    /**
     * The suggestions of the request window, with an e-mail address: for an intervention (600, 608.1) the
     * intervention contacts of the area, for other projects the site's own contacts and those of its area.
     *
     * @return Collection<int, WorkSiteContact>
     */
    public static function candidatesFor(Project $project): Collection
    {
        $project->loadMissing('category', 'workSite.contacts', 'workSite.area.contacts', 'workSite.area.interventionContacts');

        $contacts = $project->isIntervention()
            ? ($project->workSite?->area?->interventionContacts ?? new Collection)
            : ($project->workSite?->allContacts() ?? new Collection);

        return $contacts->filter(fn (WorkSiteContact $contact): bool => filled($contact->email))->values();
    }

    /**
     * Contact persons who are only on an intervention list (of any area): never offered for other projects.
     *
     * @return list<int>
     */
    public static function interventionOnlyIds(): array
    {
        $intervention = DB::table('work_site_area_intervention_contact')->pluck('work_site_contact_id');
        $elsewhere = DB::table('work_site_area_contact')->pluck('work_site_contact_id')
            ->merge(DB::table('work_site_work_site_contact')->pluck('work_site_contact_id'));

        return $intervention->diff($elsewhere)->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
    }

    /**
     * The suggestions of the request window (the site's and the area's contacts): "Sophie Dubois — sophie@… (FR)" by id.
     *
     * @return array<int, string>
     */
    public static function candidateOptions(Project $project): array
    {
        return self::candidatesFor($project)->mapWithKeys(fn (WorkSiteContact $contact): array => [$contact->id => self::optionLabel($contact)])->all();
    }

    public static function optionLabel(WorkSiteContact $contact): string
    {
        return trim($contact->fullName().($contact->company ? ' ('.$contact->company.')' : '').' — '.$contact->email.' ('.strtoupper($contact->preferredLocale()).')');
    }

    /**
     * Any contact person with an e-mail address, by name, company or e-mail; for a project that is not an
     * intervention, without the people who are only on an intervention list.
     *
     * @return array<int, string>
     */
    public static function searchRecipients(string $search, ?Project $project = null): array
    {
        $term = '%'.addcslashes($search, '%_\\').'%';

        return WorkSiteContact::query()
            ->whereNotNull('email')->where('email', '!=', '')
            ->when($project !== null && ! $project->isIntervention(), fn ($query) => $query->whereKeyNot(self::interventionOnlyIds()))
            ->where(fn ($query) => $query->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('company', 'like', $term)->orWhere('email', 'like', $term))
            ->orderBy('last_name')->orderBy('first_name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (WorkSiteContact $contact): array => [$contact->id => self::optionLabel($contact)])
            ->all();
    }

    /**
     * @param  array{starts_on: string, ends_on: string, remarks?: string|null, contact_ids: list<int|string>}  $data  contact_ids: the people chosen in the request window
     */
    public function handle(Project $project, User $actor, array $data): GpwRequest
    {
        abort_unless(Gate::forUser($actor)->allows('requestGpw', $project), 403);

        // Always chosen by the requester (nothing goes out automatically): people with an e-mail address; for
        // a project that is not an intervention, not those who are only on an intervention list.
        $contacts = WorkSiteContact::query()
            ->whereKey(array_map('intval', (array) ($data['contact_ids'] ?? [])))
            ->whereNotNull('email')->where('email', '!=', '')
            ->when(! $project->isIntervention(), fn ($query) => $query->whereKeyNot(self::interventionOnlyIds()))
            ->get();
        $contact = $contacts->first();

        if ($contact === null) {
            throw ValidationException::withMessages(['contact_ids' => __('erp.gpw.errors.no_recipient')]);
        }

        $from = CarbonImmutable::parse($data['starts_on'])->startOfDay();
        $until = CarbonImmutable::parse($data['ends_on'])->startOfDay();

        if ($from->isBefore(today())) {
            throw ValidationException::withMessages(['starts_on' => __('erp.gpw.errors.past')]);
        }

        if ($until->isBefore($from)) {
            throw ValidationException::withMessages(['ends_on' => __('validation.after_or_equal', ['attribute' => __('erp.gpw.ends_on'), 'date' => $from->format('d/m/Y')])]);
        }

        $request = GpwRequest::query()->create([
            'project_id' => $project->id,
            'requested_by' => $actor->id,
            'work_site_contact_id' => $contact->id,
            'contact_ids' => $contacts->modelKeys(),
            'contact_name' => mb_substr($contacts->map(fn (WorkSiteContact $contact): string => $contact->fullName())->implode(', '), 0, 500),
            'contact_email' => mb_substr($contacts->pluck('email')->implode(', '), 0, 1000),
            'locale' => $contact->preferredLocale(),
            'starts_on' => $from->toDateString(),
            'ends_on' => $until->toDateString(),
            'remarks' => filled($data['remarks'] ?? null) ? trim((string) $data['remarks']) : null,
            'status' => GpwStatus::Pending,
        ]);

        $this->mailFailed = ! $this->mail($request);

        return $request;
    }

    /**
     * The link in the mail to the contact: signed, valid until the end of the requested period.
     */
    public static function decisionUrl(GpwRequest $request, ?string $locale = null): string
    {
        $expires = CarbonImmutable::parse($request->ends_on)->endOfDay();

        return URL::temporarySignedRoute('gpw.decide', $expires->isAfter(now()->addDay()) ? $expires : now()->addDay(), array_filter([
            'gpwRequest' => $request->id,
            // The page opens in the language of the contact the mail went to.
            'lang' => $locale !== null && $locale !== $request->locale ? $locale : null,
        ]));
    }

    /**
     * One mail to all chosen contact persons (one per language), the requester in copy of the first.
     * The contacts are those chosen for the request (with their e-mail address as it is now), or else
     * the addresses the request was first sent to.
     */
    public function mail(GpwRequest $request): bool
    {
        $request->loadMissing('project.workSite', 'requester');
        $project = $request->project;
        $requester = $request->requester;

        $recipients = WorkSiteContact::query()
            ->whereKey(filled($request->contact_ids) ? (array) $request->contact_ids : array_filter([$request->work_site_contact_id]))
            ->get()
            ->filter(fn (WorkSiteContact $contact): bool => filled($contact->email))
            ->map(fn (WorkSiteContact $contact): array => [$contact->email, $contact->fullName(), $contact->preferredLocale(), $contact->id]);

        if ($recipients->isEmpty()) {
            $recipients = collect(explode(',', $request->contact_email))->map(fn (string $email): array => [trim($email), $request->contact_name, $request->locale, null]);
        }

        // A new mailing: the keys of earlier mails that were not used stop working.
        GpwRequestKey::query()->where('gpw_request_id', $request->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        // Everyone it goes to, shown in every mail.
        $everyone = $recipients->map(fn (array $recipient): string => $recipient[1].' ('.$recipient[0].')')->implode(', ');

        try {
            // One mail with all recipients in it (per language, as a mail has one language), so they see who
            // else got it; its "Approve" and "Refuse" buttons carry the key of that mail.
            foreach ($recipients->groupBy(fn (array $recipient): string => $recipient[2])->values() as $index => $group) {
                $locale = $group->first()[2];
                $names = $group->map(fn (array $recipient): string => $recipient[1])->implode(', ');
                [$token] = GpwRequestKey::issue($request, $group->pluck(0)->implode(', '), $names, $locale, $group->count() === 1 ? $group->first()[3] : null);

                Mail::to($group->map(fn (array $recipient): array => ['email' => $recipient[0], 'name' => $recipient[1]])->all())
                    ->cc($index === 0 && $requester?->email ? [$requester->email] : [])
                    ->send(new SystemMail(MailTemplateKey::GpwRequest, $locale, [
                        'contact_name' => $names,
                        'recipients' => $everyone,
                        'project' => $project->reference,
                        'cow_code' => $project->workSite?->cow_code ?? $project->cow_code ?? '—',
                        'address' => $project->addressLine() ?? $project->workSite?->addressLine() ?? '—',
                        'description' => $project->short_description ?? '',
                        'period' => $request->period(),
                        'from' => $request->starts_on->format('d/m/Y'),
                        'until' => $request->ends_on->format('d/m/Y'),
                        'remarks' => $request->remarks ?? '—',
                        'requested_by' => $requester?->name ?? settings()->siteName(),
                        'requester_email' => $requester?->email ?? '',
                        'approve_url' => route('gpw.key.approve', $token),
                        'reject_url' => route('gpw.key.reject', $token),
                        'view_url' => self::decisionUrl($request, $locale),
                        'portal_url' => ClientPortal::loginUrl(),
                    ]));
            }
        } catch (Throwable $exception) {
            Log::warning('GPW request could not be mailed', ['gpw_request' => $request->id, 'error' => $exception->getMessage()]);

            return false;
        }

        $request->forceFill(['mailed_at' => now()])->save();

        return true;
    }
}
