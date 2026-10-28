<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * "Own permissions" of a user: the role's permissions, ticked off per area, which can be changed for this
 * user alone. Only the areas that differ from the role are stored; they overrule the role (also to take a
 * right away). For a user with an administrator role only the explicit permissions can be set (such as the
 * 4G router credentials): administrators have everything else anyway.
 */
class UserPermissions
{
    public const STATE = 'own_permissions';

    /**
     * The areas and abilities that can be set for a user with this role.
     *
     * @return array<string, list<string>>
     */
    public static function areas(?Role $role): array
    {
        return $role?->is_admin ? array_merge_recursive(Permissions::explicit(), Permissions::userOnly()) : Permissions::all();
    }

    /**
     * What the role grants in an area.
     *
     * @return list<string>
     */
    public static function roleAbilities(?Role $role, string $area): array
    {
        return array_values(array_intersect(self::areas($role)[$area] ?? [], (array) ($role?->permissions[$area] ?? [])));
    }

    /**
     * The ticked abilities per area for the form: the user's own where set, else the role's.
     *
     * @param  array<string, list<string>>|null  $own
     * @return array<string, list<string>>
     */
    public static function state(?Role $role, ?array $own): array
    {
        $state = [];

        foreach (self::areas($role) as $area => $abilities) {
            $state[$area] = array_key_exists($area, (array) $own)
                ? array_values(array_intersect($abilities, (array) $own[$area]))
                : self::roleAbilities($role, $area);
        }

        return $state;
    }

    /**
     * The areas where the form differs from the role, as stored on the user (null when none).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, list<string>>|null
     */
    public static function overrides(array $state, ?Role $role): ?array
    {
        $overrides = [];

        foreach (self::areas($role) as $area => $abilities) {
            $ticked = array_values(array_intersect($abilities, (array) ($state[$area] ?? [])));
            $fromRole = self::roleAbilities($role, $area);
            sort($ticked);
            sort($fromRole);

            if ($ticked !== $fromRole) {
                $overrides[$area] = $ticked;
            }
        }

        return $overrides === [] ? null : $overrides;
    }

    /**
     * When the role changes, the areas that differed from the old role keep their ticks; the rest follows
     * the new role.
     */
    public static function switchRole(Set $set, Get $get, mixed $oldRoleId, mixed $newRoleId): void
    {
        $old = Role::find($oldRoleId);
        $new = Role::find($newRoleId);
        $overrides = self::overrides((array) $get(self::STATE), $old) ?? [];

        $set(self::STATE, self::state($new, $overrides));
    }

    public static function section(): Section
    {
        $role = fn (Get $get): ?Role => Role::find($get('role_id'));

        return Section::make(__('erp.users.own_permissions'))
            ->description(fn (Get $get): string => $role($get)?->is_admin ? __('erp.users.own_permissions_admin_help') : __('erp.users.own_permissions_help'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->collapsible()
            ->collapsed(fn (?User $record): bool => ! $record?->hasOwnPermissions())
            ->headerActions([
                Action::make('resetPermissions')
                    ->label(__('erp.users.reset_permissions'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->size('sm')
                    ->action(fn (Set $set, Get $get) => $set(self::STATE, self::state($role($get), null))),
            ])
            ->schema([
                Grid::make(['md' => 2, 'xl' => 4])->schema(
                    collect(Permissions::all())
                        ->map(fn (array $abilities, string $area) => CheckboxList::make(self::STATE.'.'.$area)
                            ->label(__("erp.permissions.areas.{$area}"))
                            ->options(fn (Get $get): array => collect(self::areas($role($get))[$area] ?? [])
                                ->mapWithKeys(fn (string $ability): array => [$ability => __("erp.permissions.abilities.{$ability}")])
                                ->all())
                            ->visible(fn (Get $get): bool => array_key_exists($area, self::areas($role($get))))
                            ->helperText(function (Get $get, ?array $state) use ($area, $role): ?string {
                                $ticked = array_values((array) $state);
                                $fromRole = self::roleAbilities($role($get), $area);
                                sort($ticked);
                                sort($fromRole);

                                return $ticked !== $fromRole ? __('erp.users.differs_from_role') : null;
                            })
                            ->live()
                            ->bulkToggleable())
                        ->values()
                        ->all()
                ),
            ]);
    }
}
