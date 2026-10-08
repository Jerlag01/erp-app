<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'is_admin', 'is_guest', 'permissions'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Whether this role grants a permission such as "vehicles.update".
     * Administrator roles are granted everything.
     */
    public function allows(string $permission): bool
    {
        if ($this->is_admin) {
            return true;
        }

        [$area, $ability] = array_pad(explode('.', $permission, 2), 2, null);

        return in_array($ability, $this->permissions[$area] ?? [], true);
    }

    /**
     * The role non-administrators fall back to while they are not in any team.
     */
    public static function guest(): ?self
    {
        return once(fn (): ?self => static::where('is_guest', true)->first());
    }

    /**
     * Only known permissions are stored, whatever the form sends. The guest role never has any.
     */
    protected static function booted(): void
    {
        static::saving(function (self $role): void {
            $role->permissions = $role->is_admin || $role->is_guest ? [] : Permissions::sanitize($role->permissions ?? []);
        });
    }

    public function permissionCount(): int
    {
        return $this->is_admin
            ? collect(Permissions::all())->flatten()->count()
            : collect($this->permissions ?? [])->flatten()->count();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'is_guest' => 'boolean',
            'permissions' => 'array',
        ];
    }
}
