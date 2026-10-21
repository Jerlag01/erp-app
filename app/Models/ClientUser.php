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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

/**
 * A user of the client portal ("Global overview for Client"), e.g. a Proximus manager. Separate from
 * the ERP's users: they sign in on /client only and see, read-only, what their permissions allow —
 * never prices.
 */
#[Fillable(['name', 'email', 'company', 'work_site_contact_id', 'password', 'locale', 'is_active', 'permissions', 'project_categories', 'last_login_at', 'welcome_sent_at'])]
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
     * The contact person (of COW sites or an area) this user is: they see the projects at those sites.
     *
     * @return BelongsTo<WorkSiteContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WorkSiteContact::class, 'work_site_contact_id');
    }

    /**
     * Projects assigned to this user by hand.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'client_user_project')->withTimestamps();
    }

    /**
     * Whether the user also sees the projects, sites and equipment of other clients.
     */
    public function seesAllProjects(): bool
    {
        return $this->may(ClientPortal::ALL_PROJECTS);
    }

    /**
     * Ids of the COW sites whose contact person is this user's contact: the site's own contact, or the
     * area's contacts for a site without one (as WorkSite::effectiveContacts()).
     *
     * @return Builder<WorkSite>
     */
    public function contactWorkSiteIds(): Builder
    {
        $contactId = $this->work_site_contact_id;

        return WorkSite::query()
            ->select('work_sites.id')
            ->when($contactId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where(fn (Builder $query) => $query
                ->where('work_site_contact_id', $contactId)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('work_site_contact_id')
                    ->whereHas('area.contacts', fn (Builder $query) => $query->whereKey($contactId))));
    }

    /**
     * Ids of the projects this user owns: assigned to them, or at their contact's COW sites.
     *
     * @return Builder<Project>
     */
    public function ownProjectIds(): Builder
    {
        return Project::query()
            ->select('projects.id')
            ->where(fn (Builder $query) => $query
                ->whereIn('projects.id', DB::table('client_user_project')->select('project_id')->where('client_user_id', $this->getKey()))
                ->orWhereIn('projects.work_site_id', $this->contactWorkSiteIds()));
    }

    /**
     * Limits a project query to what this user may see: their categories, and their own projects
     * unless they may see all.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function restrictProjects(Builder $query): Builder
    {
        return $query
            ->whereHas('category', fn (Builder $query) => $query->whereIn('code', $this->projectCategories()))
            ->when(! $this->seesAllProjects(), fn (Builder $query) => $query->whereIn('projects.id', $this->ownProjectIds()));
    }

    /**
     * Limits a query of things at a COW site (routers, charging stations) to this user's sites: those
     * of their contact and of their own projects, unless they may see all. Things without a site but
     * with a project (charging stations) follow that project.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function restrictToOwnSites(Builder $query, bool $orOwnProject = false): Builder
    {
        if ($this->seesAllProjects()) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $projectSites = Project::query()->select('work_site_id')->whereNotNull('work_site_id')->whereIn('projects.id', $this->ownProjectIds());

        return $query->where(fn (Builder $query) => $query
            ->whereIn("{$table}.work_site_id", $this->contactWorkSiteIds())
            ->orWhereIn("{$table}.work_site_id", $projectSites)
            ->when($orOwnProject, fn (Builder $query) => $query->orWhereIn("{$table}.project_id", $this->ownProjectIds())));
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
