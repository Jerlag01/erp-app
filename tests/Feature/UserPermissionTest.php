<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\Role;
use App\Models\Router;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Role $technician;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
        $this->technician = Role::factory()->create(['name' => 'Technieker', 'permissions' => ['vehicles' => ['view', 'update'], 'waste' => ['view']]]);
    }

    #[Test]
    public function user_permissions_overrule_the_role_per_area(): void
    {
        $user = User::factory()->inTeam()->create(['role_id' => $this->technician->id]);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertSchemaStateSet(['own_permissions.vehicles' => ['view', 'update'], 'own_permissions.waste' => ['view']])
            ->fillForm([
                'own_permissions.vehicles' => ['view'],
                'own_permissions.routers' => ['view', 'secrets'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame(['vehicles' => ['view'], 'routers' => ['view', 'secrets']], $user->permissions, 'Only what differs from the role is stored.');
        $this->assertFalse($user->hasPermission('vehicles.update'), 'Taken away for this user.');
        $this->assertTrue($user->hasPermission('waste.view'), 'Other areas follow the role.');
        $this->assertTrue($user->hasPermission('routers.secrets'), 'Also the 4G confidential data.');

        // A role change afterwards still reaches the areas that follow the role.
        $this->technician->update(['permissions' => ['vehicles' => ['view', 'update'], 'waste' => ['view', 'create']]]);
        $this->assertTrue($user->fresh()->hasPermission('waste.create'));
        $this->assertFalse($user->fresh()->hasPermission('vehicles.update'));

        // Back to the role.
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['own_permissions.vehicles' => ['view', 'update'], 'own_permissions.routers' => []])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($user->fresh()->permissions);
    }

    #[Test]
    public function administrators_keep_everything_but_explicit_permissions_can_be_set_per_user(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertFalse($admin->hasPermission('routers.secrets'));

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertFormFieldVisible('own_permissions.routers')
            ->assertFormFieldHidden('own_permissions.vehicles')
            ->fillForm(['own_permissions.routers' => ['secrets']])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();
        $this->assertTrue($admin->hasPermission('routers.secrets'));
        $this->assertTrue($admin->hasPermission('vehicles.delete'), 'The administrator role overrules everything else.');

        $router = Router::factory()->for(WorkSite::factory()->create())->create(['password' => 'Only-For-Me']);
        Filament::setCurrentPanel('app');
        $this->actingAs($admin)->get("/routers/{$router->id}")->assertOk()->assertSee('Only-For-Me');
        $this->actingAs(User::factory()->admin()->create())->get("/routers/{$router->id}")->assertOk()->assertDontSee('Only-For-Me');
    }

    #[Test]
    public function individual_permissions_also_work_without_a_team_and_unknown_ones_are_dropped(): void
    {
        $user = User::factory()->create(['role_id' => $this->technician->id, 'permissions' => ['waste' => ['view', 'hack'], 'nonsense' => ['view']]]);

        $this->assertSame(['waste' => ['view']], $user->permissions);
        $this->assertTrue($user->isGuest());
        $this->assertTrue($user->hasPermission('waste.view'), 'Given to this user personally.');
        $this->assertFalse($user->hasPermission('vehicles.view'), 'The role does not count without a team.');
    }

    #[Test]
    public function a_new_user_starts_with_the_permissions_of_the_role(): void
    {
        Notification::fake();
        Mail::fake();

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Nieuwe Technieker', 'email' => 'nieuw@example.com', 'send_welcome_mail' => true])
            ->fillForm(['role_id' => $this->technician->id])
            ->assertSchemaStateSet(['own_permissions.vehicles' => ['view', 'update']])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(User::query()->where('email', 'nieuw@example.com')->value('permissions'));
    }

    #[Test]
    public function every_permission_has_a_label(): void
    {
        foreach (['nl', 'en', 'fr'] as $locale) {
            foreach (Permissions::all() as $area => $abilities) {
                $this->assertNotSame("erp.permissions.areas.{$area}", __("erp.permissions.areas.{$area}", locale: $locale), "{$locale}: area {$area}");

                foreach ($abilities as $ability) {
                    $this->assertNotSame("erp.permissions.abilities.{$ability}", __("erp.permissions.abilities.{$ability}", locale: $locale), "{$locale}: {$ability}");
                }
            }
        }
    }
}
