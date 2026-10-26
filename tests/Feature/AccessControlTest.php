<?php

namespace Tests\Feature;

use App\Filament\Resources\AccessBadges\AccessBadgeResource;
use App\Filament\Resources\AccessBadges\Pages\ManageAccessBadges;
use App\Filament\Resources\SecurityTags\Pages\ManageSecurityTags;
use App\Models\AccessBadge;
use App\Models\Employee;
use App\Models\SecurityTag;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    /**
     * @param  list<string>  $abilities
     */
    protected function manager(array $abilities = ['view', 'create', 'update', 'generate_pdf']): User
    {
        return User::factory()->withPermissions(['access_control' => $abilities])->inTeam()->create();
    }

    #[Test]
    public function badges_and_keys_are_added_one_per_employee_with_their_digits(): void
    {
        $this->actingAs($this->manager());
        $jan = Employee::factory()->create(['first_name' => 'Jan', 'last_name' => 'Peeters']);
        $piet = Employee::factory()->create(['first_name' => 'Piet', 'last_name' => 'Janssens']);

        Livewire::test(ManageAccessBadges::class)
            ->callAction('create', ['employee_id' => $jan->id, 'badge_number' => '1234567', 'key_number' => '0042', 'has_clad' => true, 'clearance' => 'Zone A en B'])
            ->assertHasNoActionErrors();
        $this->assertSame(['1234567', '0042', true], [AccessBadge::sole()->badge_number, AccessBadge::sole()->key_number, AccessBadge::sole()->has_clad]);

        // Wrong lengths, a number of someone else, a second entry for the same employee, or neither number.
        Livewire::test(ManageAccessBadges::class)->callAction('create', ['employee_id' => $piet->id, 'badge_number' => '123456', 'key_number' => '12345'])->assertHasActionErrors(['badge_number', 'key_number']);
        Livewire::test(ManageAccessBadges::class)->callAction('create', ['employee_id' => $piet->id, 'badge_number' => '1234567'])->assertHasActionErrors(['badge_number']);
        Livewire::test(ManageAccessBadges::class)->callAction('create', ['employee_id' => $jan->id, 'badge_number' => '7654321'])->assertHasActionErrors(['employee_id']);
        Livewire::test(ManageAccessBadges::class)->callAction('create', ['employee_id' => $piet->id])->assertHasActionErrors(['badge_number']);

        // Changed later.
        Livewire::test(ManageAccessBadges::class)
            ->callAction(TestAction::make('edit')->table(AccessBadge::sole()), ['key_number' => '0043'])
            ->assertHasNoActionErrors();
        $this->assertSame('0043', AccessBadge::sole()->key_number);
    }

    #[Test]
    public function an_employee_who_left_shows_as_deactivated_and_only_the_delete_right_removes_entries(): void
    {
        $this->actingAs($this->manager());
        $badge = AccessBadge::query()->create(['employee_id' => Employee::factory()->create()->id, 'badge_number' => '1111111']);
        $leaver = AccessBadge::query()->create(['employee_id' => Employee::factory()->create(['left_on' => '2026-09-30'])->id, 'badge_number' => '2222222']);

        Livewire::test(ManageAccessBadges::class)
            ->assertCanSeeTableRecords([$badge])
            ->assertCanNotSeeTableRecords([$leaver])
            ->set('activeTab', 'deactivated')
            ->assertCanSeeTableRecords([$leaver])
            ->assertTableColumnStateSet('status', __('erp.access.deactivated'), $leaver)
            ->assertActionHidden(TestAction::make('delete')->table($leaver));

        $this->actingAs($this->manager(['view', 'delete']));
        Livewire::test(ManageAccessBadges::class)->callAction(TestAction::make('delete')->table($badge));
        $this->assertModelMissing($badge);

        $this->actingAs(User::factory()->inTeam()->create());
        $this->get(AccessBadgeResource::getUrl('index'))->assertForbidden();
    }

    #[Test]
    public function security_tags_have_two_digits_and_their_access(): void
    {
        $this->actingAs($this->manager());
        $employee = Employee::factory()->create(['first_name' => 'An', 'last_name' => 'Claes']);

        Livewire::test(ManageSecurityTags::class)->callAction('create', ['employee_id' => $employee->id, 'tag_number' => '123'])->assertHasActionErrors(['tag_number']);
        Livewire::test(ManageSecurityTags::class)
            ->callAction('create', ['employee_id' => $employee->id, 'tag_number' => '07', 'system_name' => 'CLAES A', 'access_warehouse' => true, 'access_office' => false, 'door_keys' => true])
            ->assertHasNoActionErrors();

        $tag = SecurityTag::sole();
        $this->assertSame(['07', 'CLAES A', true, false, true], [$tag->tag_number, $tag->system_name, $tag->access_warehouse, $tag->access_office, $tag->door_keys]);
    }

    #[Test]
    public function both_lists_are_exported_to_pdf(): void
    {
        $this->actingAs($this->manager());
        AccessBadge::query()->create(['employee_id' => Employee::factory()->create(['first_name' => 'Jan', 'last_name' => 'Peeters'])->id, 'badge_number' => '1234567', 'has_clad' => true]);
        SecurityTag::query()->create(['employee_id' => Employee::factory()->create()->id, 'tag_number' => '07']);

        $this->get(route('access.badges-pdf', ['tab' => 'all']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('access.tags-pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->manager(['view']));
        $this->get(route('access.badges-pdf'))->assertForbidden();
    }

    #[Test]
    public function tags_can_be_unassigned_belong_to_a_user_without_employee_record_or_be_lost(): void
    {
        $this->actingAs($this->manager());
        $employee = Employee::factory()->create();
        $cleaner = User::factory()->create(['name' => 'Poetsdienst Mia']);

        // A user with a login who is not in the employee register.
        Livewire::test(ManageSecurityTags::class)
            ->mountAction('create')
            ->fillForm(['holder' => 'user'])
            ->assertFormFieldVisible('user_id')
            ->assertFormFieldHidden('employee_id');
        Livewire::test(ManageSecurityTags::class)
            ->callAction('create', ['holder' => 'user', 'user_id' => $cleaner->id, 'tag_number' => '21'])
            ->assertHasNoActionErrors();
        $userTag = SecurityTag::query()->where('tag_number', '21')->sole();
        $this->assertSame([null, $cleaner->id, 'Poetsdienst Mia'], [$userTag->employee_id, $userTag->user_id, $userTag->holderName()]);
        $this->assertSame(SecurityTag::STATUS_ACTIVE, $userTag->status());

        // One tag per user.
        Livewire::test(ManageSecurityTags::class)
            ->callAction('create', ['holder' => 'user', 'user_id' => $cleaner->id, 'tag_number' => '22'])
            ->assertHasActionErrors(['user_id']);

        // In stock: nobody.
        Livewire::test(ManageSecurityTags::class)
            ->callAction('create', ['holder' => 'none', 'tag_number' => '30'])
            ->assertHasNoActionErrors();
        $spare = SecurityTag::query()->where('tag_number', '30')->sole();
        $this->assertSame(SecurityTag::STATUS_UNASSIGNED, $spare->status());

        // Handing the user's tag to an employee clears the user.
        Livewire::test(ManageSecurityTags::class)
            ->callTableAction('edit', $userTag, ['holder' => 'employee', 'employee_id' => $employee->id])
            ->assertHasNoTableActionErrors();
        $this->assertSame([$employee->id, null], [$userTag->fresh()->employee_id, $userTag->fresh()->user_id]);

        // Lost: from the list, with the date.
        Livewire::test(ManageSecurityTags::class)->set('activeTab', 'unassigned')->call('updateTableColumnState', 'is_lost', (string) $spare->getKey(), true);
        $this->assertTrue($spare->fresh()->is_lost);
        $this->assertNotNull($spare->fresh()->lost_at);

        Livewire::test(ManageSecurityTags::class)
            ->set('activeTab', 'lost')->assertCanSeeTableRecords([$spare])->assertCanNotSeeTableRecords([$userTag])
            ->set('activeTab', 'unassigned')->assertCanNotSeeTableRecords([$spare, $userTag])
            ->set('activeTab', 'active')->assertCanSeeTableRecords([$userTag])->assertCanNotSeeTableRecords([$spare]);

        $this->get(route('access.tags-pdf', ['tab' => 'lost']))->assertOk();
    }
}
