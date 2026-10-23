<?php

namespace Tests\Feature;

use App\Actions\RespondToSuggestion;
use App\Enums\MailTemplateKey;
use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Filament\Admin\Resources\Suggestions\Pages\ListSuggestions;
use App\Filament\Admin\Resources\Suggestions\Pages\ViewSuggestion;
use App\Filament\App\Pages\MySuggestions;
use App\Filament\App\Pages\SubmitSuggestion;
use App\Mail\SystemMail;
use App\Models\Suggestion;
use App\Models\SuggestionResponse;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SuggestionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_idea_box_link_is_in_the_top_bar_for_every_employee(): void
    {
        $guest = User::factory()->create();

        $this->actingAs($guest)->get('/')->assertOk()
            ->assertSee('data-suggestion-link', escape: false)
            ->assertSee(SubmitSuggestion::getUrl(panel: 'app'));
        $this->actingAs($guest)->get('/idea-box')->assertOk();
    }

    #[Test]
    public function the_form_does_not_ask_for_name_or_time_but_both_are_stored(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->inTeam()->create(['name' => 'Eva Van den Broeck']);

        $this->actingAs($employee);
        Filament::setCurrentPanel('app');
        $before = now()->subSecond();

        Livewire::test(SubmitSuggestion::class)
            ->assertFormFieldDoesNotExist('first_name')
            ->assertFormFieldDoesNotExist('last_name')
            ->assertFormFieldDoesNotExist('submitted_at')
            ->assertDontSee('Eva Van den Broeck')
            ->fillForm([
                'type' => 'complaint',
                'description' => 'De verwarming in de kantine werkt niet.',
                'may_be_public' => true,
                'photos' => [UploadedFile::fake()->image('radiator.jpg')],
            ])
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertNotified(__('erp.suggestions.thanks'));

        $suggestion = Suggestion::sole();
        $this->assertTrue($suggestion->user->is($employee));
        $this->assertSame('Eva', $suggestion->first_name);
        $this->assertSame('Van den Broeck', $suggestion->last_name);
        $this->assertTrue($suggestion->submitted_at->between($before, now()->addSecond()));
        $this->assertSame(SuggestionType::Complaint, $suggestion->type);
        $this->assertTrue($suggestion->may_be_public);
        $this->assertSame(SuggestionStatus::New, $suggestion->status);
        $this->assertCount(1, $suggestion->photos);
        Storage::disk('local')->assertExists($suggestion->photos[0]);

        $this->assertCount(1, $admin->notifications);
    }

    #[Test]
    public function svg_files_are_refused_and_a_description_is_required(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('app');

        Livewire::test(SubmitSuggestion::class)
            ->fillForm(['type' => 'idea', 'photos' => [UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml')]])
            ->call('submit')
            ->assertHasFormErrors(['description' => 'required', 'photos']);

        $this->assertSame(0, Suggestion::count());
    }

    #[Test]
    public function administrators_see_a_separate_list_per_type(): void
    {
        $idea = Suggestion::factory()->create(['type' => SuggestionType::Idea]);
        $complaint = Suggestion::factory()->create(['type' => SuggestionType::Complaint]);
        $other = Suggestion::factory()->create(['type' => SuggestionType::Other]);

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        $this->get('/admin/idea-box')->assertOk();

        Livewire::test(ListSuggestions::class)
            ->assertCanSeeTableRecords([$idea])
            ->assertCanNotSeeTableRecords([$complaint, $other])
            ->set('activeTab', 'complaint')
            ->assertCanSeeTableRecords([$complaint])
            ->assertCanNotSeeTableRecords([$idea, $other])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$idea, $complaint, $other]);

        $this->get("/admin/idea-box/{$complaint->id}")->assertOk()->assertSee($complaint->first_name);
    }

    #[Test]
    public function administrators_record_the_follow_up(): void
    {
        $admin = User::factory()->admin()->create();
        $suggestion = Suggestion::factory()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewSuggestion::class, ['record' => $suggestion->getRouteKey()])
            ->callAction('handle', ['status' => 'done'])
            ->assertHasNoActionErrors();

        $suggestion->refresh();
        $this->assertSame(SuggestionStatus::Done, $suggestion->status);
        $this->assertTrue($suggestion->handler->is($admin));
    }

    #[Test]
    public function photos_and_pdfs_are_for_administrators_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('suggestions/a.jpg', UploadedFile::fake()->image('a.jpg', 300, 200)->getContent());
        $submitter = User::factory()->create();
        $suggestions = Suggestion::factory()->count(2)->for($submitter)->create(['photos' => ['suggestions/a.jpg']]);
        $photo = route('suggestions.photo', ['suggestion' => $suggestions[0], 'index' => 0]);
        $pdf = route('suggestions.pdf', ['suggestions' => $suggestions->modelKeys()]);

        // The submitter sees their own photos (in the conversation), not the PDF; colleagues see nothing.
        $this->actingAs($submitter)->get($photo)->assertOk();
        $this->actingAs($submitter)->get($pdf)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($photo)->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get($photo)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $response = $this->actingAs($admin)->get($pdf)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        $employee = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->setModules(['suggestions' => false]);

        $this->actingAs($employee)->get('/')->assertOk()->assertDontSee('data-suggestion-link', escape: false);
        $this->actingAs($employee)->get('/idea-box')->assertForbidden();
        $this->actingAs($admin)->get('/admin/idea-box')->assertForbidden();
    }

    #[Test]
    public function administrators_and_the_submitter_reply_to_each_other_with_photos(): void
    {
        Storage::fake('local');
        Mail::fake();
        $submitter = User::factory()->create(['locale' => 'fr', 'email' => 'piet@power.example', 'name' => 'Piet Peeters']);
        $admin = User::factory()->admin()->create(['email' => 'chef@power.example', 'name' => 'Ann Admin']);
        $suggestion = Suggestion::factory()->for($submitter)->create(['type' => SuggestionType::Complaint, 'status' => SuggestionStatus::New]);

        // An administrator replies with a photo: the submitter is told in the platform and by mail, in French.
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Livewire::test(ViewSuggestion::class, ['record' => $suggestion->getRouteKey()])
            ->callAction('respond', ['body' => 'We bekijken het deze week.', 'photos' => [UploadedFile::fake()->image('plan.jpg')]])
            ->assertHasNoActionErrors();

        $reply = SuggestionResponse::query()->sole();
        $this->assertTrue($reply->from_admin);
        $this->assertCount(1, $reply->photos);
        $this->assertSame(SuggestionStatus::InProgress, $suggestion->fresh()->status);
        $this->assertSame(1, $submitter->notifications()->count());
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('piet@power.example')
            && $mail->key === MailTemplateKey::SuggestionResponse
            && str_starts_with($mail->rendered['subject'], 'Nouvelle réponse')
            && str_contains($mail->rendered['html'], 'We bekijken het deze week.')
            && str_contains($mail->rendered['html'], '/idea-box/mine?suggestion='.$suggestion->id));

        // The submitter reads it on "My submissions" and replies; the administrators are told.
        $this->actingAs($submitter);
        Filament::setCurrentPanel('app');
        $this->get(MySuggestions::getUrl(['suggestion' => $suggestion->id]))->assertOk()->assertSee('We bekijken het deze week.')->assertSee('Ann Admin');
        $this->get(route('suggestions.response-photo', ['response' => $reply, 'index' => 0]))->assertOk();

        Livewire::withQueryParams(['suggestion' => $suggestion->id])
            ->test(MySuggestions::class)
            ->fillForm(['body' => 'Merci, la machine est toujours en panne.'])
            ->call('reply')
            ->assertHasNoFormErrors();

        $this->assertSame(2, $suggestion->responses()->count());
        $this->assertFalse($suggestion->responses()->get()->last()->from_admin);
        $this->assertSame(1, $admin->notifications()->count());
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('chef@power.example') && str_contains($mail->rendered['html'], '/admin/idea-box/'.$suggestion->id));

        // Both see the whole conversation on the administration side.
        $this->actingAs($admin)->get("/admin/idea-box/{$suggestion->id}")->assertOk()->assertSee('Merci, la machine est toujours en panne.');

        // A colleague can neither read nor reply.
        $colleague = User::factory()->create();
        $this->actingAs($colleague);
        Livewire::withQueryParams(['suggestion' => $suggestion->id])->test(MySuggestions::class)->assertDontSee('We bekijken het deze week.');
        $this->get(route('suggestions.response-photo', ['response' => $reply, 'index' => 0]))->assertForbidden();
        $this->expectException(HttpException::class);
        app(RespondToSuggestion::class)->handle($suggestion, $colleague, ['body' => 'Ik ook']);
    }
}
