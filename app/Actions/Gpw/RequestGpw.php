<?php

namespace App\Actions\Gpw;

use App\Enums\GpwStatus;
use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\GpwRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkSiteContact;
use App\Support\ClientPortal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Asks the contact person of the project's COW site for a GPW in a period: the request is stored and
 * mailed to the contact, in their language, with a copy to the requester. The mail links to a page
 * where the contact approves or refuses it; client portal users who see the project can do so too.
 */
class RequestGpw
{
    /** Whether the last request could be mailed. */
    public bool $mailFailed = false;

    /**
     * The contact persons a GPW request for this project goes to: the COW site's contact, or else the
     * contacts of its area — those with an e-mail address.
     *
     * @return Collection<int, WorkSiteContact>
     */
    public static function contactsFor(Project $project): Collection
    {
        return ($project->workSite?->effectiveContacts() ?? new Collection)->filter(fn (WorkSiteContact $contact): bool => filled($contact->email))->values();
    }

    /**
     * "Sophie Dubois (sophie@…, FR), Marc Peeters (marc@…, NL)", for the request window.
     */
    public static function recipientsText(Project $project): ?string
    {
        return self::contactsFor($project)->map(fn (WorkSiteContact $contact): string => $contact->fullName().' ('.$contact->email.', '.strtoupper($contact->preferredLocale()).')')->implode(', ') ?: null;
    }

    /**
     * @param  array{starts_on: string, ends_on: string, remarks?: string|null}  $data
     */
    public function handle(Project $project, User $actor, array $data): GpwRequest
    {
        abort_unless(Gate::forUser($actor)->allows('requestGpw', $project), 403);

        $contacts = self::contactsFor($project);
        $contact = $contacts->first();

        if ($contact === null) {
            throw ValidationException::withMessages(['starts_on' => __('erp.gpw.errors.no_contact')]);
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
     * One mail per contact person, each in their own language; the requester gets a copy of the first.
     * The contacts are those of the site now, or else the addresses the request was first sent to.
     */
    public function mail(GpwRequest $request): bool
    {
        $request->loadMissing('project.workSite', 'requester');
        $project = $request->project;
        $requester = $request->requester;

        $recipients = self::contactsFor($project)->map(fn (WorkSiteContact $contact): array => [$contact->email, $contact->fullName(), $contact->preferredLocale()]);

        if ($recipients->isEmpty()) {
            $recipients = collect(explode(',', $request->contact_email))->map(fn (string $email): array => [trim($email), $request->contact_name, $request->locale]);
        }

        try {
            foreach ($recipients->values() as $index => [$email, $name, $locale]) {
                Mail::to($email, $name)
                    ->cc($index === 0 && $requester?->email ? [$requester->email] : [])
                    ->send(new SystemMail(MailTemplateKey::GpwRequest, $locale, [
                        'contact_name' => $name,
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
                        'approve_url' => self::decisionUrl($request, $locale),
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
