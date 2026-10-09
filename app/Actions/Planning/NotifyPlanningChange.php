<?php

namespace App\Actions\Planning;

use App\Enums\MailTemplateKey;
use App\Filament\Pages\Planning as PlanningPage;
use App\Mail\SystemMail;
use App\Models\PlanningItem;
use App\Models\User;
use App\Support\MailTemplates;
use App\Support\Planning;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Tells an employee that items were added to, changed in or removed from their planning: always in
 * the platform (unless they made the change themselves) and, when asked, by e-mail to them with a
 * copy to the addresses from Settings > Planning.
 */
class NotifyPlanningChange
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    /**
     * @param  list<PlanningItem>  $items  the new state (or the removed items)
     * @param  list<PlanningItem>  $previous  the state before a change
     * @return bool false when the e-mail could not be sent
     */
    public function handle(User $employee, string $event, array $items, ?User $actor, bool $mail, array $previous = []): bool
    {
        if ($items === []) {
            return true;
        }

        $locale = $employee->preferredLocale() ?? config('app.locale');
        $date = $items[0]->date->toDateString();

        if ($actor?->id !== $employee->id && $employee->is_active) {
            $this->notifyInApp($employee, $event, $items, $previous, $locale, $date);
        }

        return ! $mail || $this->mail($employee, $event, $items, $previous, $actor, $locale, $date);
    }

    /**
     * @param  list<PlanningItem>  $items
     * @param  list<PlanningItem>  $previous
     */
    protected function notifyInApp(User $employee, string $event, array $items, array $previous, string $locale, string $date): void
    {
        $lines = array_map(fn (PlanningItem $item): string => e($item->summary($locale)), array_slice($items, 0, 8));

        if (count($items) > 8) {
            $lines[] = e(__('erp.planning.notifications.more', ['count' => count($items) - 8], $locale));
        }

        if ($event === self::UPDATED && $previous !== []) {
            $lines[] = '<s>'.e($previous[0]->summary($locale)).'</s>';
        }

        $notification = Notification::make()
            ->title(__("erp.planning.notifications.{$event}_title", [], $locale))
            ->body(implode('<br>', $lines))
            ->icon($event === self::DELETED ? 'heroicon-o-calendar' : 'heroicon-o-calendar-days')
            ->actions([
                Action::make('open')
                    ->label(__('erp.planning.notifications.open', [], $locale))
                    ->url(PlanningPage::getUrl(['date' => $date], panel: 'app')),
            ]);

        match ($event) {
            self::CREATED => $notification->success(),
            self::UPDATED => $notification->warning(),
            default => $notification->danger(),
        };

        $notification->sendToDatabase($employee);
    }

    /**
     * @param  list<PlanningItem>  $items
     * @param  list<PlanningItem>  $previous
     */
    protected function mail(User $employee, string $event, array $items, array $previous, ?User $actor, string $locale, string $date): bool
    {
        $key = match ($event) {
            self::CREATED => MailTemplateKey::PlanningCreated,
            self::UPDATED => MailTemplateKey::PlanningUpdated,
            default => MailTemplateKey::PlanningDeleted,
        };

        try {
            Mail::to($employee->email, $employee->name)
                ->cc(array_values(array_diff(app(Planning::class)->notifyAddresses(), [strtolower($employee->email)])))
                ->send(new SystemMail($key, $locale, [
                    'name' => $employee->name,
                    'changed_by' => $actor?->name ?? settings()->siteName(),
                    'items' => $this->markdownList($items, $locale),
                    'previous' => $this->markdownList($previous, $locale),
                    'planning_url' => PlanningPage::getUrl(['date' => $date], panel: 'app'),
                ]));
        } catch (Throwable $exception) {
            Log::warning('Planning mail could not be sent', ['user' => $employee->id, 'event' => $event, 'error' => $exception->getMessage()]);

            return false;
        }

        return true;
    }

    /**
     * One bullet per item, with the warehouse description and tasks under it.
     *
     * @param  list<PlanningItem>  $items
     */
    public function markdownList(array $items, string $locale): HtmlString
    {
        $lines = [];

        foreach ($items as $item) {
            $date = $item->date->locale($locale)->translatedFormat('D d/m/Y');
            $topic = trim($item->topic().(filled($subtitle = $item->subtitle()) ? " ({$subtitle})" : ''));
            $lines[] = '- **'.MailTemplates::escape("{$date} {$item->timeRange()}").'** · '.MailTemplates::escape($topic);

            foreach (array_filter([$item->description, $item->notes]) as $text) {
                $lines[] = '  '.MailTemplates::escape(preg_replace('/\s+/', ' ', (string) $text));
            }

            foreach ($item->taskList() as $task) {
                $lines[] = '    - '.MailTemplates::escape($task['text']);
            }
        }

        return new HtmlString($lines === [] ? '-' : implode("\n", $lines));
    }
}
