<?php

namespace Tests\Feature\Security;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stored XSS: HTML organizer disanitasi saat disimpan (event, form, field builder).
 */
class StoredXssSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /** @return array<string, mixed> */
    private function eventPayload(string $description): array
    {
        return [
            'title' => 'Event Uji XSS',
            'location' => 'Campus Hall',
            'description' => $description,
            'registration_start' => now()->startOfDay()->toDateTimeString(),
            'registration_end' => now()->addDays(3)->endOfDay()->toDateTimeString(),
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'quota' => 50,
            'price' => '0',
            'session' => 'general',
            'category' => 'rkt',
        ];
    }

    /** @return array<string, mixed> */
    private function formPayload(Form $form, string $successContent): array
    {
        return [
            'title' => $form->title,
            'description' => $form->description,
            'success_content' => $successContent,
            'closed_at' => $form->closed_at?->format('Y-m-d H:i:s') ?? now()->addDays(10)->format('Y-m-d H:i:s'),
            'visible_for' => $form->visible_for?->map(fn ($e) => $e->value)->values()->all() ?? ['public'],
            'banner_url' => $form->banner_url,
            'banner_caption' => $form->banner_caption,
            'metadata' => ['purpose' => 'registration', 'registration_mode' => 'single'],
            'fields' => [],
        ];
    }

    public function test_event_description_dibersihkan_dari_script_iframe_dan_handler(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->patch(route('dashboard.events.update', $event), $this->eventPayload(
                '<script>alert(1)</script><iframe src="https://evil.test"></iframe>'
                .'<p onclick="alert(2)">ok</p>',
            ))
            ->assertRedirect(route('dashboard.events.show', $event));

        $stored = (string) $event->fresh()?->description;

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('alert(1)', $stored);
        $this->assertStringNotContainsString('<iframe', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertSame('<p>ok</p>', $stored);
    }

    public function test_event_description_format_rich_text_dipertahankan(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $html = '<h2>Agenda</h2><p><strong>Wajib</strong> <em>hadir</em></p>'
            .'<ul><li>Pagi</li></ul><p><a href="https://example.com/daftar">Daftar</a></p>';
        $expected = '<h2>Agenda</h2><p><strong>Wajib</strong> <em>hadir</em></p>'
            .'<ul><li>Pagi</li></ul><p><a href="https://example.com/daftar" rel="noopener noreferrer">Daftar</a></p>';

        $this->actingAs($admin)
            ->patch(route('dashboard.events.update', $event), $this->eventPayload($html))
            ->assertRedirect(route('dashboard.events.show', $event));

        $this->assertSame($expected, (string) $event->fresh()?->description);
    }

    public function test_event_description_plain_text_tidak_berubah(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['created_by' => $admin->id]);
        $plain = 'Deskripsi acara biasa tanpa HTML.';

        $this->actingAs($admin)
            ->patch(route('dashboard.events.update', $event), $this->eventPayload($plain))
            ->assertRedirect(route('dashboard.events.show', $event));

        $this->assertSame($plain, (string) $event->fresh()?->description);
    }

    public function test_form_success_content_dibersihkan_dari_script_dan_javascript_url(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $form = $this->registrationForm($event);

        $this->actingAs($admin)
            ->put(route('dashboard.events.forms.update', ['event' => $event, 'form' => $form]), $this->formPayload(
                $form,
                '<script>alert(1)</script><p>ok</p>'
                .'<p><a href="javascript:alert(2)">klik</a></p>',
            ))
            ->assertRedirect();

        $stored = (string) $form->fresh()?->success_content;

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringContainsString('<p>ok</p>', $stored);
        $this->assertStringContainsString('<a>klik</a>', $stored);
    }

    public function test_form_success_content_plain_text_tidak_berubah(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $form = $this->registrationForm($event);
        $plain = 'Langkah 1: tunggu review.';

        $this->actingAs($admin)
            ->put(route('dashboard.events.forms.update', ['event' => $event, 'form' => $form]), $this->formPayload($form, $plain))
            ->assertRedirect();

        $this->assertSame($plain, (string) $form->fresh()?->success_content);
    }

    public function test_field_description_dan_paragraf_builder_dibersihkan(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $form = $this->registrationForm($event);

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form]), [
                'fields' => [
                    [
                        'label' => 'Nama',
                        'type' => 'input',
                        'name' => 'nama_xss',
                        'description' => '<script>alert(1)</script><p>ok</p>',
                        'order' => 1,
                        'metadata' => ['type' => 'text', 'rules' => []],
                    ],
                    [
                        'label' => 'Paragraf',
                        'type' => 'textarea',
                        'name' => 'paragraf_xss',
                        'order' => 2,
                        'metadata' => [
                            'builderType' => 'paragraph',
                            'content' => '<img src="https://example.com/a.png" onerror="alert(2)"><p>isi</p>',
                            'rules' => [],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $field = FormField::query()->where('form_id', $form->id)->where('name', 'nama_xss')->firstOrFail();
        $paragraph = FormField::query()->where('form_id', $form->id)->where('name', 'paragraf_xss')->firstOrFail();

        $this->assertStringNotContainsString('<script', (string) $field->description);
        $this->assertStringNotContainsString('alert(1)', (string) $field->description);
        $this->assertSame('<p>ok</p>', (string) $field->description);

        $content = (string) ($paragraph->metadata['content'] ?? '');
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertSame('<img src="https://example.com/a.png"><p>isi</p>', $content);
    }

    public function test_field_description_plain_text_tidak_berubah(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $form = $this->registrationForm($event);
        $plain = 'Isi sesuai KTP & jangan disingkat.';

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.fields', ['event' => $event, 'form' => $form]), [
                'fields' => [
                    [
                        'label' => 'Nama',
                        'type' => 'input',
                        'name' => 'nama_biasa',
                        'description' => $plain,
                        'order' => 1,
                        'metadata' => ['type' => 'text', 'rules' => []],
                    ],
                ],
            ])
            ->assertRedirect();

        $field = FormField::query()->where('form_id', $form->id)->where('name', 'nama_biasa')->firstOrFail();

        $this->assertSame($plain, (string) $field->description);
    }

    private function registrationForm(Event $event): Form
    {
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'success_content' => null,
            'metadata' => ['purpose' => 'registration', 'registration_mode' => 'single'],
        ]);

        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => Str::random(8),
            'input_type' => 'input',
            'label' => 'Existing',
            'order' => 1,
            'metadata' => ['type' => 'text', 'rules' => []],
        ]);

        return $form;
    }
}
