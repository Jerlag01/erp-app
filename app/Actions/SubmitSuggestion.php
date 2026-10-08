<?php

namespace App\Actions;

use App\Enums\SuggestionType;
use App\Filament\Admin\Resources\Suggestions\SuggestionResource;
use App\Models\Suggestion;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Saves an entry in the idea/complaint box. First and last name and the date and time are
 * added by the system from the signed-in account; the form does not ask for them.
 */
class SubmitSuggestion
{
    /**
     * @param  array{type: SuggestionType|string, may_be_public?: bool, description: string, photos?: array<int, string>}  $data
     */
    public function handle(User $user, array $data): Suggestion
    {
        [$firstName, $lastName] = static::splitName($user->name);

        $suggestion = Suggestion::create([
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'submitter_name' => $user->name,
            'submitted_at' => now(),
            'type' => $data['type'] instanceof SuggestionType ? $data['type'] : SuggestionType::from($data['type']),
            'may_be_public' => (bool) ($data['may_be_public'] ?? false),
            'description' => $data['description'],
            'photos' => array_values((array) ($data['photos'] ?? [])),
        ]);

        $this->notifyAdministrators($suggestion);

        return $suggestion;
    }

    /**
     * "Jan van den Berg" → ["Jan", "van den Berg"]: accounts have one name field.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [$name];

        return [$parts[0], $parts[1] ?? null];
    }

    protected function notifyAdministrators(Suggestion $suggestion): void
    {
        $admins = User::query()->active()->whereHas('role', fn ($query) => $query->where('is_admin', true))->get();

        foreach ($admins as $admin) {
            $locale = $admin->preferredLocale();

            Notification::make()
                ->title(__('erp.suggestions.notification_title', ['type' => __('erp.enums.suggestion_type.'.$suggestion->type->value, [], $locale)], $locale))
                ->body(str($suggestion->description)->limit(120)->toString())
                ->icon('heroicon-o-light-bulb')
                ->info()
                ->actions([
                    Action::make('open')
                        ->label(__('erp.incidents.open', [], $locale))
                        ->url(SuggestionResource::getUrl('view', ['record' => $suggestion], panel: 'admin')),
                ])
                ->sendToDatabase($admin);
        }
    }
}
