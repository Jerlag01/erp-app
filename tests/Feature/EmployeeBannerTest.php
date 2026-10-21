<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Settings;
use App\Models\User;
use App\Support\ProjectProgress;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeBannerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function administrators_set_a_message_that_employees_see_in_the_top_bar(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(Settings::class)
            ->fillForm(['banner.enabled' => true, 'banner.color' => 'warning', 'banner.message_nl' => ''])
            ->call('save')
            ->assertHasFormErrors(['banner.message_nl' => 'required']);

        Livewire::test(Settings::class)
            ->fillForm(['banner.enabled' => true, 'banner.color' => 'warning', 'banner.message_nl' => 'Vrijdag kantoor gesloten', 'banner.message_fr' => 'Bureau fermé vendredi'])
            ->call('save')
            ->assertHasNoFormErrors();

        // On the employees' side, in their language (Dutch when there is no translation), before the idea box.
        $this->actingAs(User::factory()->create(['locale' => 'fr']))->get('/')->assertOk()->assertSee('Bureau fermé vendredi')->assertSeeInOrder(['data-employee-banner', 'data-suggestion-link'], false);
        $this->actingAs(User::factory()->create(['locale' => 'en']))->get('/')->assertSee('Vrijdag kantoor gesloten');

        // Not on the administrators' side, and gone once switched off.
        $this->actingAs($admin)->get('/admin')->assertDontSee('Vrijdag kantoor gesloten');
        settings()->save('banner', [...settings('banner'), 'enabled' => false]);
        $this->actingAs(User::factory()->create())->get('/')->assertDontSee('Vrijdag kantoor gesloten');
    }

    #[Test]
    public function the_follow_up_steps_name_cabling_and_every_commissioning_partner(): void
    {
        $this->assertSame('cabling', ProjectProgress::STEPS[7]);
        $this->assertSame('Câblage', __('erp.project_progress.steps.cabling', [], 'fr'));
        $this->assertSame('Bekabeling', __('erp.project_progress.steps.cabling', [], 'nl'));
        $this->assertStringContainsString('ALSB', __('erp.project_progress.steps.commissioning', [], 'nl'));
        $this->assertStringContainsString('Automation KB', __('erp.project_progress.steps.commissioning', [], 'en'));
    }
}
