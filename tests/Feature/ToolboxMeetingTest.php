<?php

namespace Tests\Feature;

use App\Filament\Pages\ToolboxOverview;
use App\Filament\Resources\ToolboxMeetings\Pages\CreateToolboxMeeting;
use App\Filament\Resources\ToolboxMeetings\Pages\ListToolboxMeetings;
use App\Filament\Resources\ToolboxMeetings\Pages\ViewToolboxMeeting;
use App\Models\Employee;
use App\Models\Team;
use App\Models\ToolboxMeeting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolboxMeetingTest extends TestCase
{
    use RefreshDatabase;

    protected User $employee;

    protected ?Team $team = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Filament::setCurrentPanel('app');
        $this->employee = $this->employee('Kevin Rouzée');
    }

    protected function employee(string $name, array $toolbox = []): User
    {
        $user = User::factory()->withPermissions($toolbox === [] ? [] : ['toolbox' => $toolbox])->inTeam($this->team ??= Team::factory()->create())->create(['name' => $name]);
        Employee::factory()->for($user)->create();

        return $user;
    }

    protected function signature(): string
    {
        $image = imagecreatetruecolor(40, 20);
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    protected function meeting(array $attributes = []): ToolboxMeeting
    {
        Storage::disk('local')->put('toolbox/files/heffen.pdf', '%PDF-1.4 test');

        return ToolboxMeeting::factory()->published()->create([
            'attachments' => ['toolbox/files/heffen.pdf'],
            'attachment_names' => ['toolbox/files/heffen.pdf' => 'Heffen en tillen.pdf'],
            ...$attributes,
        ]);
    }

    #[Test]
    public function a_meeting_is_prepared_as_draft_and_published_to_every_employee(): void
    {
        $preparer = $this->employee('Rudy Cools', ['create', 'update']);
        $this->actingAs($preparer);

        Livewire::test(CreateToolboxMeeting::class)
            ->fillForm([
                'title' => 'Heffen en tillen',
                'meeting_month' => '2026-09-17',
                'location' => 'NV SA Power Installation',
                'instructors' => ['Rudy Cools', 'Alain Dubois'],
                'subject_nl' => 'Heffen en tillen',
                'subject_fr' => 'Levage de charges lourdes',
                'body' => '<p>Buig door je knieën.</p>',
                'attachments' => [UploadedFile::fake()->create('heffen.pptx', 200, 'application/vnd.openxmlformats-officedocument.presentationml.presentation')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $meeting = ToolboxMeeting::query()->sole();
        $this->assertSame('2026-09-01', $meeting->meeting_month->toDateString());
        $this->assertSame('Rudy Cools & Alain Dubois', $meeting->instructorNames());
        $this->assertSame(['heffen.pptx'], array_values($meeting->attachment_names));
        $this->assertFalse($meeting->isPublished());

        // A draft is not shown to employees.
        $this->actingAs($this->employee);
        Livewire::test(ListToolboxMeetings::class)->assertCanNotSeeTableRecords([$meeting]);
        $this->get(route('filament.app.resources.toolbox-meetings.view', $meeting))->assertNotFound();

        $this->actingAs($preparer);
        Livewire::test(ViewToolboxMeeting::class, ['record' => $meeting->getRouteKey()])->callAction('publish');

        $this->assertTrue($meeting->fresh()->isPublished());
        $this->assertCount(1, $this->employee->notifications);
        $this->assertCount(0, $preparer->notifications, 'Who publishes is not told.');

        $this->actingAs($this->employee);
        Livewire::test(ListToolboxMeetings::class)->assertCanSeeTableRecords([$meeting]);
        Livewire::test(CreateToolboxMeeting::class)->assertForbidden();
    }

    #[Test]
    public function an_employee_signs_only_after_reading_the_whole_text_and_every_attachment(): void
    {
        $meeting = $this->meeting();
        $this->actingAs($this->employee);

        $page = Livewire::test(ViewToolboxMeeting::class, ['record' => $meeting->getRouteKey()])
            ->assertSee('Veilig tillen')
            ->assertActionVisible('sign')
            ->assertActionDisabled('sign');

        // Scrolled to the end of the text, but the attachment is not opened yet.
        $page->call('markTextRead')->assertActionDisabled('sign');

        $page->call('download', 0)->assertFileDownloaded('Heffen en tillen.pdf');
        $page->assertActionEnabled('sign')
            ->callAction('sign', ['signer_name' => 'Kevin Rouzée', 'signature' => $this->signature()])
            ->assertHasNoActionErrors();

        $participant = $meeting->participants()->sole();
        $this->assertTrue($participant->isSigned());
        $this->assertSame('Kevin Rouzée', $participant->signer_name);
        Storage::disk('local')->assertExists($participant->signature_path);
        $this->assertStringStartsWith('data:image/png;base64,', $participant->signatureDataUri());

        Livewire::test(ViewToolboxMeeting::class, ['record' => $meeting->getRouteKey()])->assertActionHidden('sign');
    }

    #[Test]
    public function a_meeting_with_only_attachments_or_only_text_needs_just_that(): void
    {
        $this->actingAs($this->employee);

        $attachmentsOnly = $this->meeting(['body' => null]);
        Livewire::test(ViewToolboxMeeting::class, ['record' => $attachmentsOnly->getRouteKey()])
            ->call('download', 0)
            ->assertActionEnabled('sign');

        $textOnly = ToolboxMeeting::factory()->published()->create();
        Livewire::test(ViewToolboxMeeting::class, ['record' => $textOnly->getRouteKey()])
            ->assertActionDisabled('sign')
            ->call('markTextRead')
            ->assertActionEnabled('sign');
    }

    #[Test]
    public function the_attendance_list_pdf_and_the_overview_show_who_signed(): void
    {
        $meeting = $this->meeting();
        $this->employee('Bruno Garray');
        $participant = $meeting->participantFor($this->employee);
        $participant->update(['text_read_at' => now(), 'downloaded' => ['toolbox/files/heffen.pdf']]);
        $participant->sign('Kevin Rouzée', $this->signature());

        $manager = $this->employee('Laura Khalaf', ['view_signatures', 'generate_pdf']);
        $this->actingAs($manager);

        $this->get(route('toolbox.pdf', $meeting))->assertOk()->assertHeader('content-type', 'application/pdf');
        // In the reader's language; the subject stays in Dutch and French as entered.
        $html = view('toolbox.pdf', ['meeting' => $meeting, 'rows' => collect()])->render();
        $this->assertStringContainsString('September 2026', $html);
        $this->assertStringContainsString(__('erp.pdf.toolbox.instructions_by', [], 'nl'), $html);
        app()->setLocale('fr');
        $french = view('toolbox.pdf', ['meeting' => $meeting, 'rows' => collect()])->render();
        app()->setLocale('nl');
        $this->assertStringContainsString('Septembre 2026', $french);
        $this->assertStringContainsString('Instructions par', $french);
        $this->assertStringNotContainsString('Instructies door', $french);
        $this->assertStringContainsString('Rudy Cools &amp; Alain Dubois', $html);
        $this->assertStringContainsString('Levage de charges lourdes', $html);

        Livewire::test(ToolboxOverview::class)
            ->set('year', 2026)
            ->assertSee('Heffen en tillen')
            ->assertSee('Kevin Rouzée')
            ->assertSee('Bruno Garray')
            ->assertSeeHtml('class="yes"');

        Livewire::test(ViewToolboxMeeting::class, ['record' => $meeting->getRouteKey()])
            ->assertSee(__('erp.toolbox.signed_count', ['count' => 1, 'total' => 3]));

        // Employees see neither.
        $this->actingAs($this->employee);
        $this->get(route('toolbox.pdf', $meeting))->assertForbidden();
        $this->assertFalse(ToolboxOverview::canAccess());
    }

    #[Test]
    public function nothing_shows_while_the_module_is_off(): void
    {
        $meeting = $this->meeting();
        $this->setModules(['toolbox' => false]);
        $this->actingAs($this->employee);

        $this->get(route('filament.app.resources.toolbox-meetings.index'))->assertForbidden();
        $this->get(route('filament.app.resources.toolbox-meetings.view', $meeting))->assertForbidden();
    }

    #[Test]
    public function every_page_opens_in_both_panels(): void
    {
        $meeting = $this->meeting();
        $this->actingAs(User::factory()->admin()->create());

        foreach (['app', 'admin'] as $panel) {
            $this->get(route("filament.{$panel}.resources.toolbox-meetings.index"))->assertOk();
            $this->get(route("filament.{$panel}.resources.toolbox-meetings.create"))->assertOk();
            $this->get(route("filament.{$panel}.resources.toolbox-meetings.view", $meeting))->assertOk();
            $this->get(route("filament.{$panel}.resources.toolbox-meetings.edit", $meeting))->assertOk();
            $this->get(route("filament.{$panel}.pages.toolbox-overview"))->assertOk();
        }
    }
}
