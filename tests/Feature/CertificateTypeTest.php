<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\TrainingMatrix as TrainingMatrixPage;
use App\Filament\Admin\Resources\CertificateTypes\CertificateTypeResource;
use App\Filament\Admin\Resources\CertificateTypes\Pages\ManageCertificateTypes;
use App\Models\CertificateType;
use App\Models\Employee;
use App\Models\User;
use App\Support\TrainingMatrix;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CertificateTypeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_former_fixed_types_are_there_with_their_names_and_overview_settings(): void
    {
        $this->assertSame(24, CertificateType::query()->count());
        $this->assertSame(['VCA (basis)', 'VCA basis'], [CertificateType::forCode('vca')->getLabel('nl'), CertificateType::forCode('vca')->shortLabel('nl')]);
        $this->assertTrue(CertificateType::forCode('driving_licence')->isDrivingLicence());
        $this->assertFalse(CertificateType::forCode('school_degree')->canExpire());
        // No validity yet: they never expire until one is set.
        $this->assertNull(CertificateType::forCode('vca')->validity_months);
        $this->assertSame('Vervalt nooit', CertificateType::forCode('vca')->validityLabel());
        $this->assertSame(CertificateType::OVERVIEW_NEVER, CertificateType::forCode('school_degree')->overview);
        $this->assertFalse(CertificateType::forCode('ba4')->shows_validity);
    }

    #[Test]
    public function whoever_may_manage_the_types_adds_one_that_employees_can_then_get(): void
    {
        Filament::setCurrentPanel('app');
        $manager = User::factory()->withPermissions(['certificates' => ['view', 'create', 'types']])->inTeam()->create();
        $this->actingAs(User::factory()->withPermissions(['certificates' => ['view']])->inTeam()->create())
            ->get(CertificateTypeResource::getUrl('index', panel: 'app'))
            ->assertForbidden();

        $this->actingAs($manager)->get(TrainingMatrixPage::getUrl(panel: 'app'))->assertOk()->assertSee(CertificateTypeResource::getUrl('index', panel: 'app'));

        Livewire::test(ManageCertificateTypes::class)
            ->callAction('create', ['overview' => CertificateType::OVERVIEW_ALWAYS])
            ->assertHasActionErrors(['name_nl' => 'required_without_all']);
        Livewire::test(ManageCertificateTypes::class)
            ->callAction('create', ['name_nl' => 'Werken met asbest', 'name_fr' => 'Travaux amiante', 'short_nl' => 'Asbest', 'overview' => CertificateType::OVERVIEW_ALWAYS, 'validity_months' => 36, 'shows_validity' => true, 'is_active' => true, 'sort' => 5])
            ->assertHasNoActionErrors();

        $type = CertificateType::query()->where('code', 'werken_met_asbest')->sole();
        $this->assertSame(['Travaux amiante', 'Asbest'], [$type->getLabel('fr'), $type->shortLabel('nl')]);
        $this->assertArrayHasKey('werken_met_asbest', CertificateType::options());

        // A second type with the same name gets its own code.
        $this->assertSame('werken_met_asbest_2', CertificateType::factory()->create(['name_nl' => 'Werken met asbest'])->code);

        // A column in the overview, first because of its order; an employee holds it like any other certificate.
        $employee = Employee::factory()->for(User::factory())->create();
        $certificate = $employee->certificates()->create(['type' => 'werken_met_asbest', 'obtained_on' => '2026-03-31']);
        $this->assertSame(['2029-03-31', '3 jaar'], [$certificate->expires_on->toDateString(), $type->validityLabel()]);
        $matrix = app(TrainingMatrix::class)->build();
        $this->assertSame(['key' => 'werken_met_asbest', 'label' => 'Asbest', 'until' => true], $matrix['columns'][0]);
        $this->assertSame('Werken met asbest', $certificate->label());

        // In use: not deleted, only made inactive (then no longer offered, but kept for whoever has it).
        Livewire::test(ManageCertificateTypes::class)->assertActionHidden(TestAction::make('delete')->table($type));
        $type->update(['is_active' => false]);
        $this->assertArrayNotHasKey('werken_met_asbest', CertificateType::options());
        $this->assertArrayHasKey('werken_met_asbest', CertificateType::options('werken_met_asbest'));
        $this->assertSame('Werken met asbest', $certificate->fresh()->label());

        $unused = CertificateType::query()->where('code', 'werken_met_asbest_2')->sole();
        Livewire::test(ManageCertificateTypes::class)->callAction(TestAction::make('delete')->table($unused));
        $this->assertModelMissing($unused);
    }

    #[Test]
    public function a_type_only_shown_when_held_appears_once_somebody_has_it(): void
    {
        $type = CertificateType::factory()->create(['name_nl' => 'Duiken', 'overview' => CertificateType::OVERVIEW_HELD, 'shows_validity' => false]);
        $employee = Employee::factory()->for(User::factory())->create();

        $this->assertNotContains($type->code, array_column(app(TrainingMatrix::class)->build()['columns'], 'key'));

        $employee->certificates()->create(['type' => $type->code, 'obtained_on' => '2025-05-01']);
        $row = app(TrainingMatrix::class)->build()['rows'][0];
        $this->assertSame(['01/05/2025', 'since'], [$row['cells'][$type->code]['text'], $row['cells'][$type->code]['state']]);
    }
}
