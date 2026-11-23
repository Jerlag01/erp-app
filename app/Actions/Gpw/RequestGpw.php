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
use App\Support\GpwSites;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Asks contact persons for a GPW in a period: the requester picks who it goes to — any contact person
 * with an e-mail address (the site's own contacts are suggested, then those of its area), also people
 * without a client portal login, who decide with the buttons in the mail. The request is stored with them
 * and mailed to each, in their language, with a copy to the requester. The mail links to a page
 * where the contact approves or refuses it; client portal users who see the project can do so too.
 * The requester also picks the employees who will do the work; the contact person named in the mail is
 * always a project leader (the chosen one when the project has several), in copy with the requester.
 * Other projects can be added to the same request ("extra_projects", each with its own period): each gets
 * its own GPW request, all in one batch and one mail, so the client is not sent a mail per project.
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
        $project->loadMissing('category', 'workSite');

        // All COW codes of the project (an intervention on several, a battery move's origin and destination).
        $sites = GpwSites::workSites($project)->load('contacts', 'area.contacts', 'area.interventionContacts');
        $contacts = new Collection;

        foreach ($sites as $site) {
            $contacts = $contacts->merge($project->isIntervention() ? ($site->area?->interventionContacts ?? new Collection) : $site->allContacts());
        }

        return $contacts->filter(fn (WorkSiteContact $contact): bool => filled($contact->email))->unique('id')->values();
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
     * @param  array{starts_on?: string, ends_on?: string, remarks?: string|null, contact_ids: list<int|string>, employee_ids?: list<int|string>, contact_user_id?: int|string|null, work_site_ids?: list<int|string>, split?: bool, sites?: list<array{work_site_id: int|string, starts_on: string, ends_on: string}>}  $data  contact_ids: the people chosen in the request window; for a project with several COW codes the COW codes in one period (work_site_ids) or each in its own (split + sites)
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

        $period = GpwSites::resolve($project, $data);
        $leader = self::contactLeader($project, $data['contact_user_id'] ?? null);
        $employees = self::employeeIds($data['employee_ids'] ?? []);
        $extras = $this->extraProjects($project, $actor, (array) ($data['extra_projects'] ?? []));
        $batch = $extras !== [] ? (string) Str::ulid() : null;

        $request = DB::transaction(function () use ($project, $actor, $contact, $contacts, $period, $data, $leader, $employees, $extras, $batch): GpwRequest {
            $create = fn (Project $project, array $period): GpwRequest => tap(GpwRequest::query()->create([
                'project_id' => $project->id,
                'batch_id' => $batch,
                'requested_by' => $actor->id,
                'contact_user_id' => $leader?->id,
                'work_site_contact_id' => $contact->id,
                'contact_ids' => $contacts->modelKeys(),
                'employee_ids' => $employees !== [] ? $employees : null,
                'contact_name' => mb_substr($contacts->map(fn (WorkSiteContact $contact): string => $contact->fullName())->implode(', '), 0, 500),
                'contact_email' => mb_substr($contacts->pluck('email')->implode(', '), 0, 1000),
                'locale' => $contact->preferredLocale(),
                'starts_on' => $period['starts_on']->toDateString(),
                'ends_on' => $period['ends_on']->toDateString(),
                'remarks' => filled($data['remarks'] ?? null) ? trim((string) $data['remarks']) : null,
                'status' => GpwStatus::Pending,
            ]), fn (GpwRequest $request) => GpwSites::store($request, $period['sites']));

            $request = $create($project, $period);

            foreach ($extras as [$extra, $extraPeriod]) {
                $create($extra, $extraPeriod);
            }

            return $request;
        });

        $this->mailFailed = ! $this->mail($request);

        return $request;
    }

    /**
     * The other projects asked in the same mail, each with its period (all its COW codes in it): projects the
     * requester may ask a GPW for, each once and not the project itself.
     *
     * @param  array<int, mixed>  $rows  [project_id, starts_on, ends_on]
     * @return list<array{0: Project, 1: array{starts_on: CarbonImmutable, ends_on: CarbonImmutable, sites: list<array<string, mixed>>}}>
     */
    protected function extraProjects(Project $project, User $actor, array $rows): array
    {
        $extras = [];
        $seen = [$project->id => true];

        foreach (array_values($rows) as $index => $row) {
            $extra = Project::query()->with('category', 'workSite')->find((int) ($row['project_id'] ?? 0));

            if ($extra === null || isset($seen[$extra->id]) || ! Gate::forUser($actor)->allows('requestGpw', $extra)) {
                throw ValidationException::withMessages(["extra_projects.{$index}.project_id" => __('erp.gpw.batch.errors.project')]);
            }

            $seen[$extra->id] = true;
            $period = ['starts_on' => $row['starts_on'] ?? null, 'ends_on' => $row['ends_on'] ?? null];

            // A project with several COW codes: all of them in this period.
            if (($options = GpwSites::options($extra)) !== []) {
                $period['work_site_ids'] = array_keys($options);
            }

            try {
                $extras[] = [$extra, GpwSites::resolve($extra, $period)];
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["extra_projects.{$index}.".(in_array($key, ['starts_on', 'ends_on'], true) ? $key : 'starts_on') => $messages])->all());
            }
        }

        return $extras;
    }

    /**
     * Projects that can be added to a request: open ones the user may ask a GPW for, newest first.
     *
     * @return array<int, string>
     */
    public static function extraProjectOptions(Project $project, User $user, string $search = ''): array
    {
        $term = '%'.addcslashes(trim($search), '%_\\').'%';

        return Project::query()
            ->visibleTo($user)
            ->whereKeyNot($project->id)
            ->whereNull('archived_at')
            ->when(trim($search) !== '', fn ($query) => $query->where(fn ($query) => $query->where('reference', 'like', $term)->orWhere('cow_code', 'like', $term)->orWhere('short_description', 'like', $term)->orWhereHas('workSite', fn ($query) => $query->where('cow_code', 'like', $term))))
            ->with('category', 'workSite')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(fn (Project $candidate): bool => Gate::forUser($user)->allows('requestGpw', $candidate))
            ->take(50)
            ->mapWithKeys(fn (Project $candidate): array => [$candidate->id => trim($candidate->reference.' '.($candidate->subtitle() ? '— '.$candidate->subtitle() : ''))])
            ->all();
    }

    /**
     * The project leaders, "Jan Peeters" by id, to choose the contact person from.
     *
     * @return array<int, string>
     */
    public static function leaderOptions(Project $project): array
    {
        return $project->leaders()->orderBy('name')->pluck('users.name', 'users.id')->all();
    }

    /**
     * Active employees to choose who will do the work, "Jan Peeters" by id.
     *
     * @return array<int, string>
     */
    public static function employeeOptions(): array
    {
        return User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * The contact person in the mail is always a project leader: the chosen one when there are several,
     * else the only one. Without project leaders, the requester stays the contact (null).
     */
    public static function contactLeader(Project $project, int|string|null $chosen): ?User
    {
        $leaders = $project->leaders()->orderBy('name')->get();

        if ($leaders->count() > 1 && filled($chosen) && ($leader = $leaders->firstWhere('id', (int) $chosen)) !== null) {
            return $leader;
        }

        if ($leaders->count() > 1) {
            throw ValidationException::withMessages(['contact_user_id' => __('erp.gpw.errors.no_contact_leader')]);
        }

        return $leaders->first();
    }

    /**
     * The chosen employees that exist, in the order chosen.
     *
     * @param  list<int|string>|mixed  $ids
     * @return list<int>
     */
    public static function employeeIds(mixed $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', (array) $ids)));
        $existing = User::query()->whereKey($ids)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        return array_values(array_intersect($ids, $existing));
    }

    /**
     * The link in the mail to the contact: signed, valid until the end of the requested period.
     */
    public static function decisionUrl(GpwRequest $request, ?string $locale = null): string
    {
        $expires = CarbonImmutable::parse($request->batch()->max(fn (GpwRequest $request): string => $request->ends_on->toDateString()))->endOfDay();

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
        // Asked together with other projects: one mail for all of them still waiting, led by the first.
        $batch = $request->isBatched() ? $request->batch()->filter(fn (GpwRequest $item): bool => $item->isPending() || $item->is($request))->values() : collect([$request]);
        $request = $batch->first();
        $request->loadMissing('project.workSite', 'requester', 'contactUser');
        $project = $request->project;
        $requester = $request->requester;
        // Our contact person for the client: the project leader (else the requester).
        $contactPerson = $request->contactPerson();
        $contactLine = $contactPerson !== null
            ? $contactPerson->name.' ('.collect([$contactPerson->email, $contactPerson->phone])->filter()->implode(', ').')'
            : settings()->siteName();
        $copy = collect([$requester?->email, $contactPerson?->email])->filter()->unique()->values()->all();

        $recipients = WorkSiteContact::query()
            ->whereKey(filled($request->contact_ids) ? (array) $request->contact_ids : array_filter([$request->work_site_contact_id]))
            ->get()
            ->filter(fn (WorkSiteContact $contact): bool => filled($contact->email))
            ->map(fn (WorkSiteContact $contact): array => [$contact->email, $contact->fullName(), $contact->preferredLocale(), $contact->id]);

        if ($recipients->isEmpty()) {
            $recipients = collect(explode(',', $request->contact_email))->map(fn (string $email): array => [trim($email), $request->contact_name, $request->locale, null]);
        }

        // A new mailing: the keys of earlier mails that were not used stop working.
        GpwRequestKey::query()->whereIn('gpw_request_id', $batch->pluck('id')->all())->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        // Several COW codes: the address of each.
        $request->loadMissing('sites.workSite');
        $address = $request->sites->count() > 1
            ? $request->sites->map(fn ($site): string => trim(($site->cow_code ?? '—').' '.($site->workSite?->addressLine() ?? '')))->unique()->implode(' · ')
            : ($project->addressLine() ?? $project->workSite?->addressLine() ?? '—');

        // Several projects: each with its COW code(s), address and own period, in one mail.
        $several = $batch->count() > 1;

        if ($several) {
            $address = $batch->map(fn (GpwRequest $item): string => trim($item->project->reference.' '.($item->cowCodes() ?? '').': '.($item->project->addressLine() ?? $item->project->workSite?->addressLine() ?? '—')))->implode(' · ');
        }

        // Everyone it goes to, shown in every mail.
        $everyone = $recipients->map(fn (array $recipient): string => $recipient[1].' ('.$recipient[0].')')->implode(', ');

        try {
            // One mail with all recipients in it (per language, as a mail has one language), so they see who
            // else got it; its "Approve" and "Refuse" buttons carry the key of that mail.
            foreach ($recipients->groupBy(fn (array $recipient): string => $recipient[2])->values() as $index => $group) {
                $locale = $group->first()[2];
                $names = $group->map(fn (array $recipient): string => $recipient[1])->implode(', ');
                [$token] = GpwRequestKey::issue($request, $group->pluck(0)->implode(', '), $names, $locale, $group->count() === 1 ? $group->first()[3] : null, $batch->max(fn (GpwRequest $item): string => $item->ends_on->toDateString()));

                Mail::to($group->map(fn (array $recipient): array => ['email' => $recipient[0], 'name' => $recipient[1]])->all())
                    ->cc($index === 0 ? $copy : [])
                    ->send(new SystemMail(MailTemplateKey::GpwRequest, $locale, [
                        'contact_name' => $names,
                        'recipients' => $everyone,
                        'project' => $several ? $batch->map(fn (GpwRequest $item): string => $item->project->reference)->implode(', ') : $project->reference,
                        'cow_code' => $several ? ($batch->map(fn (GpwRequest $item): ?string => $item->cowCodes())->filter()->unique()->implode(', ') ?: '—') : ($request->cowCodes() ?? '—'),
                        'address' => $address,
                        'description' => $several ? $batch->map(fn (GpwRequest $item): ?string => filled($item->project->short_description) ? $item->project->reference.': '.$item->project->short_description : null)->filter()->implode(' · ') : ($project->short_description ?? ''),
                        // Several projects: each with its own period.
                        'period' => $several ? $batch->map(fn (GpwRequest $item): string => $item->project->reference.': '.$item->period())->implode(' · ') : $request->period(),
                        'from' => $batch->min(fn (GpwRequest $item): string => $item->starts_on->toDateString()) ? CarbonImmutable::parse($batch->min(fn (GpwRequest $item): string => $item->starts_on->toDateString()))->format('d/m/Y') : '',
                        'until' => CarbonImmutable::parse($batch->max(fn (GpwRequest $item): string => $item->ends_on->toDateString()))->format('d/m/Y'),
                        'remarks' => $request->remarks ?? '—',
                        // The contact person is always the project leader, also in templates that still say "requested by".
                        'requested_by' => $contactPerson?->name ?? settings()->siteName(),
                        'requester_email' => $contactPerson?->email ?? '',
                        'contact_person' => $contactLine,
                        'contact_person_email' => $contactPerson?->email ?? '',
                        'contact_person_phone' => $contactPerson?->phone ?? '',
                        'employees' => $request->employeeNames() ?? '—',
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

        GpwRequest::query()->whereKey($batch->pluck('id')->all())->update(['mailed_at' => now()]);
        $request->refresh();

        return true;
    }
}
