<?php

namespace Tests\Feature;

use App\Actions\GenerateOrderReference;
use App\Filament\Resources\OrderReferences\Pages\ManageOrderReferences;
use App\Models\OrderReference;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderReferenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function initials_come_from_the_first_and_last_name_unless_set(): void
    {
        $this->assertSame('JL', User::factory()->make(['name' => 'Jeroen Lagaet'])->referenceInitials());
        $this->assertSame('JB', User::factory()->make(['name' => 'Jan van den Berg'])->referenceInitials());
        $this->assertSame('EV', User::factory()->make(['name' => 'Émile Vandersteen'])->referenceInitials());
        $this->assertSame('JVB', User::factory()->make(['name' => 'Jan van den Berg', 'initials' => 'jvb'])->referenceInitials());
    }

    #[Test]
    public function the_plus_one_button_builds_the_reference_from_the_counter_date_and_initials(): void
    {
        Carbon::setTestNow('2026-10-07 10:00:00');
        $user = User::factory()->withPermissions(['order_references' => ['view', 'create']])->inTeam()->create(['name' => 'Jeroen Lagaet']);
        app(GenerateOrderReference::class)->setCounter(2026, 465);

        $this->actingAs($user);
        Filament::setCurrentPanel('app');

        Livewire::test(ManageOrderReferences::class)
            ->callAction('next')
            ->assertNotified('466/1026/JL');

        $this->assertSame('467/1026/JL', app(GenerateOrderReference::class)->handle($user)->reference);
        $this->assertTrue(OrderReference::where('reference', '466/1026/JL')->first()->user->is($user));
    }

    #[Test]
    public function the_month_follows_the_date_and_the_number_restarts_each_year(): void
    {
        $user = User::factory()->create(['name' => 'Jeroen Lagaet']);
        $generate = fn () => app(GenerateOrderReference::class)->handle($user)->reference;

        Carbon::setTestNow('2026-11-30 16:00:00');
        $this->assertSame('001/1126/JL', $generate());
        Carbon::setTestNow('2026-12-01 08:00:00');
        $this->assertSame('002/1226/JL', $generate());
        Carbon::setTestNow('2027-01-04 08:00:00');
        $this->assertSame('001/0127/JL', $generate());
    }

    #[Test]
    public function only_the_latest_reference_can_be_withdrawn_and_its_number_is_reused(): void
    {
        Carbon::setTestNow('2026-10-07 10:00:00');
        $user = User::factory()->withPermissions(['order_references' => ['view', 'create', 'delete']])->inTeam()->create(['name' => 'Jeroen Lagaet']);
        $first = app(GenerateOrderReference::class)->handle($user);
        $second = app(GenerateOrderReference::class)->handle($user);

        $this->assertFalse($user->can('delete', $first));
        $this->assertTrue($user->can('delete', $second));

        app(GenerateOrderReference::class)->withdraw($second);

        $this->assertSame('002/1026/JL', app(GenerateOrderReference::class)->handle($user)->reference);
    }

    #[Test]
    public function the_counter_cannot_be_set_below_a_number_already_used(): void
    {
        Carbon::setTestNow('2026-10-07 10:00:00');
        $user = User::factory()->create(['name' => 'Jeroen Lagaet']);
        app(GenerateOrderReference::class)->setCounter(2026, 10);
        app(GenerateOrderReference::class)->handle($user);

        app(GenerateOrderReference::class)->setCounter(2026, 3);

        $this->assertSame('012/1026/JL', app(GenerateOrderReference::class)->handle($user)->reference);
    }

    #[Test]
    public function access_follows_the_permissions_and_only_admins_set_the_counter(): void
    {
        $viewer = User::factory()->withPermissions(['order_references' => ['view']])->inTeam()->create();
        $taker = User::factory()->withPermissions(['order_references' => ['view', 'create']])->inTeam()->create();

        $this->actingAs(User::factory()->inTeam()->create())->get('/order-references')->assertForbidden();
        $this->actingAs($viewer)->get('/order-references')->assertOk();
        Filament::setCurrentPanel('app');

        Livewire::actingAs($viewer)->test(ManageOrderReferences::class)->assertActionHidden('next');
        Livewire::actingAs($taker)->test(ManageOrderReferences::class)
            ->assertActionVisible('next')
            ->assertActionHidden('setCounter');
        Livewire::actingAs(User::factory()->admin()->create())->test(ManageOrderReferences::class)->assertActionVisible('setCounter');
    }

    #[Test]
    public function details_can_be_edited_but_not_the_reference(): void
    {
        $user = User::factory()->withPermissions(['order_references' => ['view', 'update']])->inTeam()->create();
        $reference = OrderReference::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel('app');

        Livewire::test(ManageOrderReferences::class)
            ->callTableAction('edit', $reference, ['supplier' => 'Rexel', 'project' => 'Werf Luik', 'reference' => 'HACKED'])
            ->assertHasNoTableActionErrors();

        $reference->refresh();
        $this->assertSame('Rexel', $reference->supplier);
        $this->assertNotSame('HACKED', $reference->reference);
    }
}
