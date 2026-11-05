<?php

namespace Tests\Feature;

use App\Actions\It\CheckinAsset;
use App\Actions\It\CheckoutAsset;
use App\Actions\It\ItDeliveryReports;
use App\Filament\Admin\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Admin\Resources\Employees\RelationManagers\SignedDocumentsRelationManager;
use App\Filament\Pages\DeliveryReports;
use App\Filament\Resources\ItAssets\Pages\ViewItAsset;
use App\Models\Employee;
use App\Models\ItAsset;
use App\Models\ItDeliveryReport;
use App\Models\ItStatusLabel;
use App\Models\SignedDocument;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItDeliveryReportTest extends TestCase
{
    use RefreshDatabase;

    /** A 1×1 PNG as a drawn signature. */
    protected const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Filament::setCurrentPanel('app');
    }

    protected function employee(string $locale = 'fr'): User
    {
        $team = Team::query()->where('name', 'Ploeg Antwerpen')->first() ?? Team::factory()->create(['name' => 'Ploeg Antwerpen']);
        $user = User::factory()->inTeam($team)->create(['name' => 'Eva Peeters', 'locale' => $locale, 'job_title' => 'Technieker']);
        Employee::factory()->for($user)->create(['first_name' => 'Eva', 'last_name' => 'Peeters']);

        return $user;
    }

    #[Test]
    public function checking_out_to_an_employee_asks_them_to_sign_the_delivery_report(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Jeroen IT']);
        $employee = $this->employee();
        $asset = ItAsset::factory()->create(['asset_tag' => 'IT-0042', 'serial' => 'SN-123']);

        Livewire::actingAs($admin)
            ->test(ViewItAsset::class, ['record' => $asset->getRouteKey()])
            ->callAction('checkout', [
                'target_type' => 'user',
                'user_id' => $employee->id,
                'delivery_report' => true,
                'report' => ['imei' => '356938035643809', 'phone_number' => '+32 470 12 34 56', 'sim' => 'Proximus', 'accessories' => 'Lader, kabel, hoesje', 'condition' => 'refurbished', 'old_device' => 'Samsung A52', 'old_wiped' => '1'],
                'company_signature' => self::SIGNATURE,
            ])
            ->assertHasNoActionErrors();

        $report = ItDeliveryReport::query()->sole();
        $this->assertTrue($report->user->is($employee));
        $this->assertSame(['Technieker', 'Ploeg Antwerpen', 'SN-123', '356938035643809', 'refurbished'], [$report->detail('job_title'), $report->detail('site'), $report->detail('serial'), $report->detail('imei'), $report->detail('condition')]);
        $this->assertSame('Jeroen IT', $report->company_signer_name);
        $this->assertNotNull($report->company_signature_path);
        $this->assertFalse($report->isSigned());

        // The employee is told in their own language, with a link to sign.
        $notification = $employee->notifications()->sole();
        $this->assertSame(__('erp.it.delivery_report.notification_title', [], 'fr'), $notification->data['title']);

        // The PDF is in French (the employee's language), whoever opens it.
        $this->actingAs($admin)->get(route('it.delivery-report', $report))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', app(ItDeliveryReports::class)->render($report));
        $html = $this->html($report);
        $this->assertStringContainsString('ACCUSÉ DE RÉCEPTION', $html);
        $this->assertStringContainsString('Reconditionné', $html);
        $this->assertStringContainsString('356938035643809', $html);
    }

    #[Test]
    public function the_report_is_in_the_language_of_the_employee(): void
    {
        foreach (['nl' => 'ONTVANGSTBEWIJS', 'fr' => 'ACCUSÉ DE RÉCEPTION', 'en' => 'ACKNOWLEDGEMENT OF RECEIPT'] as $locale => $title) {
            $report = app(ItDeliveryReports::class)->issue(ItAsset::factory()->create(), $this->employee($locale), ['device' => 'iPhone 15', 'condition' => 'new']);
            $html = $this->html($report);

            $this->assertStringContainsString($title, $html);
            $this->assertStringContainsString(e(__('erp.it.delivery_report.terms.3', [], $locale)), $html);
        }

        // The app language stays what it was.
        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    #[Test]
    public function the_employee_signs_on_the_platform_and_it_goes_to_their_signed_documents(): void
    {
        $issuer = User::factory()->admin()->create();
        $employee = $this->employee('nl');
        $report = app(ItDeliveryReports::class)->issue(ItAsset::factory()->create(['asset_tag' => 'IT-0007']), $employee, ['device' => 'Dell Latitude 5440'], $issuer);

        $this->actingAs($employee)->get(DeliveryReports::getUrl())->assertOk()->assertSee('Dell Latitude 5440 (IT-0007)');
        $this->assertSame('1', DeliveryReports::getNavigationBadge());

        Livewire::actingAs($employee)
            ->test(DeliveryReports::class)
            ->callAction('sign', ['signer_name' => 'Eva Peeters', 'signature' => self::SIGNATURE, 'approved' => false], ['report' => $report->id])
            ->assertHasActionErrors(['approved']);
        Livewire::actingAs($employee)
            ->test(DeliveryReports::class)
            ->callAction('sign', ['signer_name' => 'Eva Peeters', 'signature' => self::SIGNATURE, 'approved' => true], ['report' => $report->id])
            ->assertHasNoActionErrors();

        $this->assertTrue($report->refresh()->isSigned());
        $document = SignedDocument::query()->sole();
        $this->assertSame(['Ontvangstbewijs Dell Latitude 5440 (IT-0007)', SignedDocument::KIND_IT_DELIVERY], [$document->title, $document->kind]);
        $this->assertTrue($document->source->is($report));
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($document->path));
        $this->assertNull(DeliveryReports::getNavigationBadge());
        $this->assertStringContainsString('gelezen en goedgekeurd', $this->html($report));

        // The person who handed it over is told.
        $this->assertSame(1, $issuer->notifications()->count());

        // The employee and administrators open it; other colleagues do not.
        $this->actingAs($employee)->get(route('signed-documents.download', $document))->assertOk();
        $this->actingAs($issuer)->get(route('signed-documents.download', $document))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('signed-documents.download', $document))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('it.delivery-report', $report))->assertForbidden();

        // In the employee register: the tab "Signed documents".
        Filament::setCurrentPanel('admin');
        Livewire::actingAs($issuer)
            ->test(SignedDocumentsRelationManager::class, ['ownerRecord' => $employee->employee, 'pageClass' => ViewEmployee::class])
            ->assertCanSeeTableRecords([$document]);
    }

    #[Test]
    public function the_employee_signs_in_the_app(): void
    {
        $employee = $this->employee('en');
        $report = app(ItDeliveryReports::class)->issue(ItAsset::factory()->create(), $employee, ['device' => 'Samsung S24']);
        $other = app(ItDeliveryReports::class)->issue(ItAsset::factory()->create(), $this->employee(), ['device' => 'iPad']);
        $auth = $this->mobileAuth($employee);

        $this->getJson('/api/v1/me', $auth)->assertOk()->assertJsonPath('data.features.delivery_reports', true)->assertJsonPath('data.features.open_delivery_reports', 1);
        $this->getJson('/api/v1/delivery-reports', $auth)->assertOk()->assertJsonCount(1, 'data.to_sign')->assertJsonPath('data.to_sign.0.device', 'Samsung S24 ('.$report->asset->asset_tag.')');

        $url = $this->postJson("/api/v1/delivery-reports/{$report->id}/link", [], $auth)->assertOk()->json('url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->postJson("/api/v1/delivery-reports/{$other->id}/link", [], $auth)->assertNotFound();

        $drawing = ['name' => 'Eva Peeters', 'width' => 300, 'height' => 120, 'strokes' => [[[10, 40], [60, 20], [120, 60]]]];
        $this->postJson("/api/v1/delivery-reports/{$report->id}/sign", $drawing, $auth)->assertUnprocessable()->assertJsonValidationErrors('approved');
        $this->postJson("/api/v1/delivery-reports/{$report->id}/sign", [...$drawing, 'approved' => true], $auth)->assertOk();
        $this->postJson("/api/v1/delivery-reports/{$report->id}/sign", [...$drawing, 'approved' => true], $auth)->assertConflict();

        $document = SignedDocument::query()->sole();
        $this->getJson('/api/v1/delivery-reports', $auth)->assertJsonCount(0, 'data.to_sign')->assertJsonPath('data.signed.0.title', 'Acknowledgement of receipt Samsung S24 ('.$report->asset->asset_tag.')');
        $this->get($this->postJson("/api/v1/signed-documents/{$document->id}/link", [], $auth)->assertOk()->json('url'))->assertOk();
    }

    #[Test]
    public function no_report_without_asking_or_for_a_location_and_taking_back_drops_an_unsigned_report(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = $this->employee();
        $asset = ItAsset::factory()->create();

        Livewire::actingAs($admin)
            ->test(ViewItAsset::class, ['record' => $asset->getRouteKey()])
            ->callAction('checkout', ['target_type' => 'user', 'user_id' => $employee->id, 'delivery_report' => false])
            ->assertHasNoActionErrors();
        $this->assertSame(0, ItDeliveryReport::query()->count());

        Livewire::actingAs($admin)
            ->test(ViewItAsset::class, ['record' => $asset->getRouteKey()])
            ->callAction('checkin', ['it_status_label_id' => $asset->it_status_label_id])
            ->callAction('checkout', ['target_type' => 'user', 'user_id' => $employee->id, 'delivery_report' => true])
            ->assertHasNoActionErrors();
        $this->assertSame(1, ItDeliveryReport::query()->count());

        Livewire::actingAs($admin)
            ->test(ViewItAsset::class, ['record' => $asset->fresh()->getRouteKey()])
            ->callAction('checkin', ['it_status_label_id' => ItStatusLabel::factory()->create()->id])
            ->assertHasNoActionErrors();
        $this->assertSame(0, ItDeliveryReport::query()->count());

        // Taken back after signing: the signed report stays.
        $signed = app(ItDeliveryReports::class)->issue($asset, $employee, ['device' => 'Laptop']);
        app(ItDeliveryReports::class)->sign($signed, 'Eva', self::SIGNATURE);
        app(CheckoutAsset::class)->handle($asset->fresh(), $employee);
        app(CheckinAsset::class)->handle($asset->fresh());
        app(ItDeliveryReports::class)->cancelFor($asset);
        $this->assertTrue(ItDeliveryReport::query()->sole()->isSigned());
    }

    protected function html(ItDeliveryReport $report): string
    {
        $previous = app()->getLocale();
        app()->setLocale($report->locale());

        try {
            return view('it.delivery-report-pdf', [
                'report' => $report->fresh(),
                'company' => 'Power Installation NV',
                'employeeSignature' => null,
                'companySignature' => null,
            ])->render();
        } finally {
            app()->setLocale($previous);
        }
    }
}
