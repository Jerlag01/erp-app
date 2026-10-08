<?php

namespace Tests\Feature;

use App\Actions\It\CheckoutItem;
use App\Actions\It\CheckoutLicenseSeat;
use App\Filament\Resources\ItLicenses\Pages\EditItLicense;
use App\Filament\Resources\ItLicenses\Pages\ViewItLicense;
use App\Filament\Resources\ItLicenses\RelationManagers\SeatsRelationManager;
use App\Models\ItAsset;
use App\Models\ItItem;
use App\Models\ItLicense;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItLicenseAndItemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_license_keeps_one_row_per_seat(): void
    {
        $license = ItLicense::factory()->create(['seats' => 3]);
        $this->assertSame(3, $license->licenseSeats()->count());

        $license->update(['seats' => 5]);
        $this->assertSame(5, $license->licenseSeats()->count());

        app(CheckoutLicenseSeat::class)->handle($license, User::factory()->create());
        $license->update(['seats' => 1]);
        $this->assertSame(1, $license->licenseSeats()->count(), 'The seat in use is kept.');
        $this->assertSame(0, $license->freeSeats());
    }

    #[Test]
    public function seats_go_to_users_or_assets_until_they_run_out(): void
    {
        $license = ItLicense::factory()->create(['seats' => 2]);
        app(CheckoutLicenseSeat::class)->handle($license, User::factory()->create());
        $seat = app(CheckoutLicenseSeat::class)->handle($license, ItAsset::factory()->create());

        $this->expectException(ValidationException::class);
        app(CheckoutLicenseSeat::class)->handle($license, User::factory()->create());
    }

    #[Test]
    public function seats_of_a_non_reassignable_license_stay_assigned(): void
    {
        $license = ItLicense::factory()->create(['seats' => 1, 'reassignable' => false]);
        $seat = app(CheckoutLicenseSeat::class)->handle($license, User::factory()->create());

        $this->expectException(ValidationException::class);
        app(CheckoutLicenseSeat::class)->checkin($seat);
    }

    #[Test]
    public function product_keys_are_encrypted_and_need_their_own_permission(): void
    {
        $license = ItLicense::factory()->create(['product_key' => 'ABCDE-12345']);
        $this->assertNotSame('ABCDE-12345', DB::table('it_licenses')->where('id', $license->id)->value('product_key'));

        $viewer = User::factory()->withPermissions(['it_licenses' => ['view', 'update']])->create();
        $this->setModules(['teams' => false]);
        $this->actingAs($viewer)->get("/it-licenses/{$license->id}")->assertOk()->assertSee($license->name)->assertDontSee('ABCDE-12345');

        Filament::setCurrentPanel('app');
        Livewire::actingAs($viewer)
            ->test(EditItLicense::class, ['record' => $license->getRouteKey()])
            ->assertFormFieldHidden('product_key')
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('ABCDE-12345', $license->fresh()->product_key);

        $keyHolder = User::factory()->withPermissions(['it_licenses' => ['view', 'view_keys']])->create();
        $this->actingAs($keyHolder)->get("/it-licenses/{$license->id}")->assertSee('ABCDE-12345');
    }

    #[Test]
    public function accessories_come_back_and_consumables_are_used_up(): void
    {
        $user = User::factory()->create();
        $docks = ItItem::factory()->create(['quantity' => 3]);
        $toner = ItItem::factory()->consumable()->create(['quantity' => 5]);

        $loan = app(CheckoutItem::class)->handle($docks, $user, 2);
        $this->assertSame(1, $docks->available());
        app(CheckoutItem::class)->checkin($loan);
        $this->assertSame(3, $docks->available());

        app(CheckoutItem::class)->handle($toner, $user, 2);
        $this->assertSame(3, $toner->fresh()->available());
        $this->assertSame(3, $toner->fresh()->quantity);

        $this->expectException(ValidationException::class);
        app(CheckoutItem::class)->handle($toner->fresh(), $user, 4);
    }

    #[Test]
    public function components_go_into_assets_only(): void
    {
        $ram = ItItem::factory()->component()->create(['quantity' => 4]);
        $laptop = ItAsset::factory()->create();

        app(CheckoutItem::class)->handle($ram, $laptop, 2);
        $this->assertSame(2, (int) $laptop->components()->sum('quantity'));

        $this->expectException(ValidationException::class);
        app(CheckoutItem::class)->handle($ram, User::factory()->create());
    }

    #[Test]
    public function a_seat_is_checked_out_from_the_license_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');
        $license = ItLicense::factory()->create(['seats' => 2]);
        $user = User::factory()->create();

        Livewire::test(SeatsRelationManager::class, ['ownerRecord' => $license, 'pageClass' => ViewItLicense::class])
            ->callTableAction('checkoutSeat', data: ['target_type' => 'user', 'user_id' => $user->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $license->freeSeats());
    }
}
