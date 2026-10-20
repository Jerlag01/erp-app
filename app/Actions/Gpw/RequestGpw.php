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
     * The contact person a GPW request for this project goes to: the COW site's contact (or its area's).
     */
    public static function contactFor(Project $project): ?WorkSiteContact
    {
        return $project->workSite?->effectiveContact();
    }

    /**
     * @param  array{starts_on: string, ends_on: string, remarks?: string|null}  $data
     */
    public function handle(Project $project, User $actor, array $data): GpwRequest
    {
        abort_unless(Gate::forUser($actor)->allows('requestGpw', $project), 403);

        $contact = self::contactFor($project);

        if ($contact === null || blank($contact->email)) {
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
            'contact_name' => $contact->fullName(),
            'contact_email' => $contact->email,
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
    public static function decisionUrl(GpwRequest $request): string
    {
        $expires = CarbonImmutable::parse($request->ends_on)->endOfDay();

        return URL::temporarySignedRoute('gpw.decide', $expires->isAfter(now()->addDay()) ? $expires : now()->addDay(), ['gpwRequest' => $request->id]);
    }

    public function mail(GpwRequest $request): bool
    {
        $request->loadMissing('project.workSite', 'requester');
        $project = $request->project;
        $requester = $request->requester;
        $locale = $request->locale;

        try {
            Mail::to($request->contact_email, $request->contact_name)
                ->cc($requester?->email ? [$requester->email] : [])
                ->send(new SystemMail(MailTemplateKey::GpwRequest, $locale, [
                    'contact_name' => $request->contact_name,
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
                    'approve_url' => self::decisionUrl($request),
                    'portal_url' => ClientPortal::loginUrl(),
                ]));
        } catch (Throwable $exception) {
            Log::warning('GPW request could not be mailed', ['gpw_request' => $request->id, 'error' => $exception->getMessage()]);

            return false;
        }

        $request->forceFill(['mailed_at' => now()])->save();

        return true;
    }
}
