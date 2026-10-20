<?php

namespace App\Models;

use App\Notifications\Client\ClientResetPassword;
use App\Support\ClientPortal;
use Database\Factories\ClientUserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A user of the client portal ("Global overview for Client"), e.g. a Proximus manager. Separate from
 * the ERP's users: they sign in on /client only and see, read-only, what their permissions allow —
 * never prices.
 */
#[Fillable(['name', 'email', 'company', 'password', 'locale', 'is_active', 'permissions', 'project_categories', 'last_login_at', 'welcome_sent_at'])]
#[Hidden(['password', 'remember_token'])]
class ClientUser extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<ClientUserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === ClientPortal::PANEL && $this->is_active;
    }

    /**
     * Whether the user has one of the portal's permissions (see ClientPortal::PERMISSIONS).
     */
    public function may(string $permission): bool
    {
        return in_array($permission, (array) $this->permissions, true);
    }

    /**
     * The project categories the user sees: those ticked for them, within the portal's categories.
     *
     * @return list<string>
     */
    public function projectCategories(): array
    {
        return array_values(array_intersect(ClientPortal::CATEGORIES, (array) $this->project_categories));
    }

    /**
     * The editable "client portal: new password" mail, in the user's language; its link always leads to
     * the client portal's own reset page, also when an administrator of the ERP sends it.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ClientResetPassword($token));
    }

    public function preferredLocale(): ?string
    {
        return in_array($this->locale, ['nl', 'fr', 'en'], true) ? $this->locale : null;
    }

    protected static function booted(): void
    {
        // Only known permissions and categories are stored, whatever the form sends.
        static::saving(function (self $user): void {
            $user->permissions = array_values(array_intersect(ClientPortal::PERMISSIONS, (array) $user->permissions));
            $user->project_categories = array_values(array_intersect(ClientPortal::CATEGORIES, (array) $user->project_categories));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'project_categories' => 'array',
            'last_login_at' => 'datetime',
            'welcome_sent_at' => 'datetime',
        ];
    }
}
