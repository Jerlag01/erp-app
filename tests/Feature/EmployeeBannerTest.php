<?php

namespace Tests\Feature;

use App\Filament\App\Pages\Announcement;
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
    public function employees_with_the_permission_edit_the_announcement_that_everyone_sees(): void
    {
        Filament::setCurrentPanel('app');

        // Without "announcements.update" the page is closed.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam()->create());
        $this->get(Announcement::getUrl())->assertForbidden();

        $editor = User::factory()->withPermissions(['announcements' => ['update']])->inTeam()->create();
        $this->actingAs($editor);
        $this->get(Announcement::getUrl())->assertOk();

        Livewire::test(Announcement::class)
            ->fillForm(['enabled' => true, 'color' => 'warning', 'message_nl' => ''])
            ->call('save')
            ->assertHasFormErrors(['message_nl' => 'required']);

        Livewire::test(Announcement::class)
            ->fillForm(['enabled' => true, 'color' => 'warning', 'message_nl' => 'Vrijdag kantoor gesloten', 'message_fr' => 'Bureau fermé vendredi'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($editor->name, settings()->get('banner.updated_by_name'));

        // On the employees' side, in their language (Dutch when there is no translation), before the idea box;
        // the edit link only for those who may edit it.
        $this->actingAs(User::factory()->create(['locale' => 'fr']))->get('/')->assertOk()->assertSee('Bureau fermé vendredi')
            ->assertSeeInOrder(['data-employee-banner', 'data-suggestion-link'], false)
            ->assertDontSee('aria-label="'.__('erp.announcements.edit', [], 'fr').'"', false);
        $this->actingAs(User::factory()->create(['locale' => 'en']))->get('/')->assertSee('Vrijdag kantoor gesloten');
        $this->actingAs($editor)->get('/')->assertSee('aria-label="'.__('erp.announcements.edit', [], $editor->preferredLocale() ?? 'nl').'"', false);

        // Not on the administrators' side; gone once switched off, or when the module is off.
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertDontSee('Vrijdag kantoor gesloten');
        $this->setModules(['announcements' => false]);
        $this->actingAs($editor)->get('/')->assertDontSee('Vrijdag kantoor gesloten');
        $this->get(Announcement::getUrl())->assertForbidden();
        $this->setModules(['announcements' => true]);
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
