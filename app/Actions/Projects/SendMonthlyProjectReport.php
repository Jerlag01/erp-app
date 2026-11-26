<?php

namespace App\Actions\Projects;

use App\Enums\MailTemplateKey;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Mail\SystemMail;
use App\Models\Project;
use App\Models\User;
use App\Support\MailTemplates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * The monthly project overview by e-mail: every open project (number, COW code, description, status) and
 * the interventions past their due date. The administrators get all of them, each project leader the
 * projects they lead. Open: not archived, not invoiced and not closed.
 */
class SendMonthlyProjectReport
{
    /**
     * Mails the overview; returns how many mails were sent.
     */
    public function handle(): int
    {
        $sent = 0;
        $users = User::query()->active()->whereNotNull('email')->with('role')->get();
        $admins = $users->filter(fn (User $user): bool => $user->isAdmin());

        foreach ($admins as $admin) {
            $sent += $this->send($admin, $this->openProjects(), all: true) ? 1 : 0;
        }

        // The project leaders (who are not administrators): their own projects, when they have open ones.
        foreach ($users->diff($admins) as $leader) {
            $projects = $this->openProjects()->whereHas('leaders', fn (Builder $query) => $query->whereKey($leader->id));

            if ((clone $projects)->exists()) {
                $sent += $this->send($leader, $projects, all: false) ? 1 : 0;
            }
        }

        return $sent;
    }

    /**
     * @return Builder<Project>
     */
    public function openProjects(): Builder
    {
        return Project::query()
            ->whereNull('archived_at')
            ->whereNotIn('status', [ProjectStatus::Invoiced->value, ProjectStatus::Closed->value]);
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function send(User $user, Builder $query, bool $all): bool
    {
        $locale = $user->preferredLocale() ?? config('app.locale');
        $projects = (clone $query)->with(['category', 'workSite'])->orderBy('reference')->get();
        $overdue = $projects->filter(fn (Project $project): bool => $project->isInterventionOverdue())->sortBy('intervention_due_on')->values();

        try {
            Mail::to($user->email, $user->name)->send(new SystemMail(MailTemplateKey::MonthlyProjectReport, $locale, [
                'name' => $user->name,
                'month' => ucfirst(now()->locale($locale)->subMonthNoOverflow()->isoFormat('MMMM YYYY')),
                'scope' => __($all ? 'erp.projects.monthly_report.all' : 'erp.projects.monthly_report.own', [], $locale),
                'open_count' => (string) $projects->count(),
                'overdue_count' => (string) $overdue->count(),
                'projects' => $this->table($projects, $locale, overdue: false),
                'overdue' => $this->table($overdue, $locale, overdue: true),
                'url' => ProjectResource::getUrl('index', panel: 'app'),
            ]));
        } catch (Throwable $exception) {
            Log::warning('Monthly project report could not be mailed', ['user' => $user->id, 'error' => $exception->getMessage()]);

            return false;
        }

        return true;
    }

    /**
     * A Markdown table (escaped), or "none".
     *
     * @param  Collection<int, Project>|\Illuminate\Support\Collection<int, Project>  $projects
     */
    protected function table(iterable $projects, string $locale, bool $overdue): HtmlString
    {
        $projects = collect($projects);

        if ($projects->isEmpty()) {
            return new HtmlString(MailTemplates::escape(__('erp.projects.monthly_report.none', [], $locale)));
        }

        $headers = [__('erp.projects.reference', [], $locale), __('erp.fields.cow_code', [], $locale), __('erp.fields.description', [], $locale), $overdue ? __('erp.interventions.due_on', [], $locale) : __('erp.fields.status', [], $locale)];
        $cell = fn (?string $value): string => filled($value) ? str_replace(["\r", "\n"], ' ', MailTemplates::escape($value)) : '—';
        $rows = $projects->map(fn (Project $project): string => '| '.implode(' | ', [
            $cell($project->reference),
            $cell($project->workSite?->cow_code ?? $project->cow_code),
            $cell($project->short_description ?? $project->subtitle()),
            $overdue ? $cell($project->intervention_due_on?->format('d/m/Y')) : $cell(__('erp.enums.project_status.'.$project->status->value, [], $locale)),
        ]).' |');

        return new HtmlString(implode("\n", ['| '.implode(' | ', array_map($cell, $headers)).' |', '|'.str_repeat(' --- |', count($headers)), ...$rows]));
    }
}
