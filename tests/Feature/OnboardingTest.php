<?php

namespace Tests\Feature;

use App\Filament\Pages\Onboarding;
use App\Filament\Resources\OnboardingDocuments\Pages\ManageOnboardingDocuments;
use App\Filament\Resources\OnboardingSignatures\OnboardingSignatureResource;
use App\Filament\Resources\OnboardingSignatures\Pages\ListOnboardingSignatures;
use App\Models\Employee;
use App\Models\OnboardingDocument;
use App\Models\OnboardingSignature;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Filament::setCurrentPanel('app');
    }

    protected function document(string $title, string $locale = 'nl'): OnboardingDocument
    {
        Storage::disk('local')->put("onboarding/documents/{$title}.pdf", '%PDF');

        return OnboardingDocument::query()->create(['title' => $title, 'path' => "onboarding/documents/{$title}.pdf", 'original_name' => "{$title}.pdf", 'locale' => $locale]);
    }

    /**
     * A new user who is in the employee register (only they onboard).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function newEmployee(array $attributes = [], ?string $motherTongue = null): User
    {
        $user = User::factory()->onboarding()->inTeam()->create($attributes);
        Employee::factory()->for($user)->create(['mother_tongue' => $motherTongue]);

        return $user;
    }

    protected function signature(): string
    {
        return 'data:image/png;base64,'.base64_encode((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    #[Test]
    public function documents_are_uploaded_several_at_once_with_a_language(): void
    {
        $this->actingAs(User::factory()->withPermissions(['onboarding' => ['manage']])->inTeam()->create());

        Livewire::test(ManageOnboardingDocuments::class)
            ->callAction('create', [
                'files' => [UploadedFile::fake()->create('Arbeidsreglement.pdf', 20, 'application/pdf'), UploadedFile::fake()->create('veiligheid_op_de_werf.pdf', 20, 'application/pdf')],
                'locale' => 'fr',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(['Arbeidsreglement', 'veiligheid op de werf'], OnboardingDocument::query()->orderBy('sort')->pluck('title')->all());
        $this->assertSame(['fr'], OnboardingDocument::query()->distinct()->pluck('locale')->all());
    }

    #[Test]
    public function a_new_user_first_opens_and_signs_every_document_in_their_language(): void
    {
        $rules = $this->document('Arbeidsreglement');
        $safety = $this->document('Veiligheid');
        $this->document('Règlement de travail', 'fr');
        $user = $this->newEmployee(['locale' => 'nl']);
        $this->actingAs($user);

        // Every page leads to the onboarding first.
        $this->get('/')->assertRedirect(Onboarding::getUrl(panel: 'app'));
        $this->get(Onboarding::getUrl(panel: 'app'))->assertOk()->assertDontSee("mountAction('sign', {&quot;", false)->assertSee('Arbeidsreglement')->assertDontSee('Règlement de travail')->assertSee('data-onboarding-required', false);

        // Signing needs the document opened first.
        Livewire::test(Onboarding::class)
            ->callAction('sign', ['signer_name' => 'Jan Nieuw', 'signature' => $this->signature()], ['document' => $rules->id])
            ->assertNotified(__('erp.onboarding.open_first'));
        $this->assertFalse((bool) OnboardingSignature::query()->whereNotNull('signed_at')->exists());

        $this->get(route('onboarding.document', $rules))->assertOk();
        Livewire::test(Onboarding::class)->callAction('sign', ['signer_name' => 'Jan Nieuw', 'signature' => $this->signature()], ['document' => $rules->id]);
        $this->assertNull($user->refresh()->onboarding_completed_at);
        $this->get('/')->assertRedirect();

        // Opening it from the page asks to sign it at once.
        Livewire::test(Onboarding::class)
            ->call('opened', $safety->id)
            ->setActionData(['signer_name' => 'Jan Nieuw', 'signature' => $this->signature()])
            ->callMountedAction()
            ->assertNotified(__('erp.onboarding.completed'));

        $this->assertNotNull($user->refresh()->onboarding_completed_at);
        $signature = OnboardingSignature::query()->where('onboarding_document_id', $safety->id)->sole();
        $this->assertSame('Jan Nieuw', $signature->signer_name);
        $this->assertNotNull($signature->signed_at);
        Storage::disk('local')->assertExists($signature->signature_path);
        $this->get('/')->assertOk();
    }

    #[Test]
    public function existing_users_count_as_onboarded_and_others_documents_stay_closed(): void
    {
        $own = $this->document('Arbeidsreglement');
        $existing = User::factory()->inTeam()->create();
        $this->actingAs($existing)->get('/')->assertOk();

        // Another language's document is not theirs to open.
        $french = $this->document('Règlement', 'fr');
        $this->get(route('onboarding.document', $french))->assertForbidden();
        $this->get(route('onboarding.document', $own))->assertOk();
    }

    #[Test]
    public function signatures_are_seen_and_exported_with_the_right_permission(): void
    {
        $document = $this->document('Arbeidsreglement');
        $user = User::factory()->onboarding()->inTeam()->create(['name' => 'Jan Nieuw']);
        $this->actingAs($user)->get(route('onboarding.document', $document));
        $signature = OnboardingSignature::query()->sole();
        $signature->sign('Jan Nieuw', $this->signature());

        $this->actingAs(User::factory()->inTeam()->create());
        $this->get(OnboardingSignatureResource::getUrl('index'))->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(['onboarding' => ['view_signatures', 'generate_pdf']])->inTeam()->create());
        Livewire::test(ListOnboardingSignatures::class)->assertCanSeeTableRecords([$signature])->assertSee('Jan Nieuw');
        $this->get(route('onboarding.signatures-pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function the_documents_follow_the_mother_tongue_then_the_platform_language_then_dutch(): void
    {
        $this->document('Arbeidsreglement');
        $this->document('Règlement de travail', 'fr');
        $user = $this->newEmployee(['locale' => 'nl'], 'fr');
        $this->actingAs($user);

        // A French-speaking employee using the platform in Dutch gets the French documents.
        $this->get(Onboarding::getUrl(panel: 'app'))->assertSee('Règlement de travail')->assertDontSee('Arbeidsreglement');

        // No documents in their language (yet): those of the platform language, else Dutch.
        $english = $this->newEmployee(['locale' => 'en'], 'de');
        $this->actingAs($english)->get(Onboarding::getUrl(panel: 'app'))->assertSee('Arbeidsreglement');
    }

    #[Test]
    public function opening_a_document_asks_to_sign_it_at_once(): void
    {
        $rules = $this->document('Arbeidsreglement');
        $this->actingAs($this->newEmployee(['locale' => 'nl']));

        Livewire::test(Onboarding::class)
            ->call('opened', $rules->id)
            ->assertActionMounted('sign')
            ->setActionData(['signer_name' => 'Jan Nieuw', 'signature' => $this->signature()])
            ->callMountedAction()
            ->assertNotified(__('erp.onboarding.completed'));

        // Signed: opening it again to read it back does not ask again.
        Livewire::test(Onboarding::class)->call('opened', $rules->id)->assertActionNotMounted('sign');
    }

    #[Test]
    public function administrators_and_logins_outside_the_employee_register_are_completed_at_once(): void
    {
        $this->document('Arbeidsreglement');

        // A login without an employee record (e.g. a cleaner): no onboarding, completed.
        $external = User::factory()->onboarding()->inTeam()->create();
        $this->actingAs($external)->get('/')->assertOk();
        $this->assertNotNull($external->refresh()->onboarding_completed_at);

        // An administrator ("Beheerder"), also when in the register.
        $admin = User::factory()->admin()->onboarding()->create();
        Employee::factory()->for($admin)->create();
        $this->actingAs($admin)->get('/')->assertOk();
        $this->assertNotNull($admin->refresh()->onboarding_completed_at);

        // The external is hired: once in the register, they onboard after all.
        Employee::factory()->for($external)->create();
        $this->assertNull($external->refresh()->onboarding_completed_at);
        $this->actingAs($external)->get('/')->assertRedirect(Onboarding::getUrl(panel: 'app'));
    }
}
