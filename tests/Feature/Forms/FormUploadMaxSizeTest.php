<?php

namespace Tests\Feature\Forms;

use App\Enums\EventStatus;
use App\Enums\EventFormVisibility;
use App\Enums\FormPurpose;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormUploadMaxSizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    private function openEvent(): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
            'quota' => 100,
            'registered_count' => 0,
        ]);
    }

    private function openForm(Event $event): Form
    {
        return Form::factory()->create([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
            'metadata' => [
                'purpose' => FormPurpose::Registration->value,
                'registration_mode' => 'single',
            ],
        ]);
    }

    private function fileField(Form $form, ?int $maxSizeKb): FormField
    {
        $rules = ['required' => true, 'mimes' => 'pdf'];
        if ($maxSizeKb !== null) {
            $rules['max_size'] = $maxSizeKb;
        }

        return FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'fileUpload',
            'name' => 'cv',
            'label' => 'CV',
            'order' => 1,
            'metadata' => ['rules' => $rules],
        ]);
    }

    /** Builds a structurally valid PDF padded to the requested kilobyte size. */
    private function pdfOfSize(string $name, int $kilobytes): UploadedFile
    {
        $header = "%PDF-1.7\n";
        $footer = "\n%%EOF";
        $padding = max(0, ($kilobytes * 1024) - strlen($header) - strlen($footer));

        return UploadedFile::fake()->createWithContent($name, $header.str_repeat('x', $padding).$footer);
    }

    private function submitPath(Event $event, Form $form): string
    {
        return route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false);
    }

    private function fillPath(Event $event, Form $form): string
    {
        return route('dashboard.events.forms.fill', ['event' => $event, 'form' => $form], false);
    }

    public function test_file_below_field_limit_is_accepted(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $member = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $this->fileField($form, 2);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['cv' => $this->pdfOfSize('cv.pdf', 1)])
            ->assertSessionHasNoErrors();

        $answer = FormAnswer::query()->where('form_id', $form->id)->where('user_id', $member->id)->firstOrFail();
        $this->assertIsString($answer->answers['cv']);
        Storage::disk('local')->assertExists($answer->answers['cv']);
    }

    public function test_file_above_field_limit_is_rejected_with_limit_in_message(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $member = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $this->fileField($form, 2);

        $response = $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['cv' => $this->pdfOfSize('cv.pdf', 6)]);

        $response->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('cv');
        $this->assertStringContainsString('2 KB', (string) session('errors')->first('cv'));
        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }

    public function test_field_limit_above_global_is_clamped_to_global(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $member = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $this->fileField($form, 10240);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['cv' => $this->pdfOfSize('cv.pdf', 6)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('form_answers', ['form_id' => $form->id, 'user_id' => $member->id]);
    }

    public function test_field_without_max_size_uses_global_limit(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $member = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $this->fileField($form, null);

        $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['cv' => $this->pdfOfSize('cv.pdf', 6)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('form_answers', ['form_id' => $form->id, 'user_id' => $member->id]);
    }

    public function test_field_limit_is_clamped_to_lowered_global_config(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['events.forms.upload_max_size_kb' => 4]);

        $member = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $this->fileField($form, 10);

        $response = $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['cv' => $this->pdfOfSize('cv.pdf', 6)]);

        $response->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('cv');
        $this->assertStringContainsString('4 KB', (string) session('errors')->first('cv'));
        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }

    public function test_field_limit_above_default_global_is_rejected_at_global_boundary(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['events.forms.upload_max_size_kb' => 5120]);

        $member = $this->member();
        $event = $this->openEvent();
        $form = $this->openForm($event);
        $this->fileField($form, 10240);

        $response = $this->actingAs($member)
            ->post($this->submitPath($event, $form), ['cv' => $this->pdfOfSize('cv.pdf', 6144)]);

        $response->assertRedirect($this->fillPath($event, $form))
            ->assertSessionHasErrors('cv');
        $this->assertStringContainsString('5 MB', (string) session('errors')->first('cv'));
        $this->assertDatabaseMissing('form_answers', ['form_id' => $form->id]);
    }
}
