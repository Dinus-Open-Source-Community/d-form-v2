<?php

namespace Tests\Feature\Forms;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormPurpose;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormSuccessContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
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

    public function test_store_persists_success_content(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.store', ['event' => $event], false), [
                'title' => 'Registration',
                'description' => 'Form description',
                'success_content' => '<p>Join the group: <a href="https://example.com/group">link</a></p>',
                'closed_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'visible_for' => ['public'],
                'banner_url' => null,
                'banner_caption' => null,
                'metadata' => [
                    'purpose' => 'registration',
                    'registration_mode' => 'single',
                ],
                'fields' => [
                    [
                        'id' => (string) Str::uuid(),
                        'label' => 'Name',
                        'type' => 'input',
                        'name' => 'full_name',
                        'order' => 1,
                        'metadata' => ['type' => 'text', 'rules' => []],
                        'is_append' => false,
                    ],
                ],
            ])
            ->assertRedirect();

        $form = Form::query()->where('event_id', $event->id)->first();
        $this->assertNotNull($form);
        $this->assertStringContainsString('https://example.com/group', (string) $form->success_content);
    }

    public function test_update_persists_success_content(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'success_content' => null,
            'metadata' => ['purpose' => 'registration', 'registration_mode' => 'single'],
        ]);
        $field = FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'a',
            'input_type' => 'input',
            'label' => 'A',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => []],
        ]);

        $this->actingAs($admin)
            ->put(route('dashboard.events.forms.update', ['event' => $event, 'form' => $form], false), [
                'title' => $form->title,
                'description' => $form->description,
                'success_content' => '<p>Step 1: wait for review</p>',
                'closed_at' => $form->closed_at->format('Y-m-d H:i:s'),
                'visible_for' => $form->visible_for->map(fn ($e) => $e->value)->values()->all(),
                'banner_url' => $form->banner_url,
                'banner_caption' => $form->banner_caption,
                'metadata' => [
                    'purpose' => 'registration',
                    'registration_mode' => 'single',
                ],
                'fields' => [
                    [
                        'id' => $field->id,
                        'label' => $field->label,
                        'type' => 'input',
                        'name' => $field->name,
                        'order' => 1,
                        'metadata' => ['type' => 'text', 'rules' => []],
                        'is_append' => false,
                    ],
                ],
            ])
            ->assertRedirect();

        $form->refresh();
        $this->assertStringContainsString('Step 1', (string) $form->success_content);
    }

    public function test_member_registration_submit_redirects_to_success_page_with_content(): void
    {
        $member = $this->member();
        $event = $this->openEvent();
        $html = '<p>Join WhatsApp: <a href="https://wa.me/123">here</a></p>';
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
            'success_content' => $html,
            'metadata' => [
                'purpose' => FormPurpose::Registration->value,
                'registration_mode' => 'single',
            ],
        ]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'input_type' => 'input',
            'name' => 'full_name',
            'label' => 'Full Name',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => ['required' => true]],
        ]);

        $this->actingAs($member)
            ->post(route('dashboard.forms.submission', ['event' => $event, 'form' => $form], false), [
                'full_name' => 'Peserta',
            ])
            ->assertRedirect(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form]));

        $this->actingAs($member)
            ->get(route('dashboard.events.forms.submitted', ['event' => $event, 'form' => $form], false))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                ->component('Dashboard/Events/Forms/SubmitSuccess')
                ->where('form.success_content', $html)
                ->where('isRegistrationForm', true)
            );
    }

    public function test_registration_page_ignores_other_purpose_form_answers(): void
    {
        $member = $this->member();
        $event = $this->openEvent();

        $registration = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'success_content' => '<p>Reg next steps</p>',
            'metadata' => ['purpose' => FormPurpose::Registration->value],
        ]);
        $other = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Feedback',
            'success_content' => '<p>Feedback thanks</p>',
            'metadata' => [
                'purpose' => FormPurpose::Other->value,
                'requires_form_id' => null,
            ],
        ]);

        FormAnswer::query()->create([
            'form_id' => $registration->id,
            'user_id' => $member->id,
            'answers' => ['full_name' => 'A'],
        ]);

        // Newer other-form answer must not take over registration status page.
        FormAnswer::query()->create([
            'form_id' => $other->id,
            'user_id' => $member->id,
            'answers' => ['feedback' => 'Nice'],
            'created_at' => now()->addMinute(),
        ]);

        $this->actingAs($member)
            ->get(route('dashboard.user.events.registration', ['event_segment' => $event->slug], false))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                ->where('form.id', $registration->id)
                ->where('form.success_content', '<p>Reg next steps</p>')
            );
    }
}
