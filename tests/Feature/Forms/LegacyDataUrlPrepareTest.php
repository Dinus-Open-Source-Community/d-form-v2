<?php

namespace Tests\Feature\Forms;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reproduksi: autosave mati (422 "bannerUrl must be an uploaded file path...")
 * untuk form yang bannernya masih `data:` URL legacy di DB.
 *
 * `prepareForValidation` harus mengkonversi `data:` URL menjadi path storage
 * SEBELUM after-validation berjalan — auto-convert di controller tidak pernah
 * tercapai karena validasi gagal duluan.
 */
class LegacyDataUrlPrepareTest extends TestCase
{
    use RefreshDatabase;

    private const PIXEL_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    public function test_fields_save_converts_legacy_data_url_banner_to_storage_path(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        $bannerId = (string) Str::uuid();

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.fields', [
                'event' => $event->id,
                'form' => $form->id,
            ]), [
                'fields' => [
                    [
                        'id' => $bannerId,
                        'label' => 'Form banner',
                        'description' => null,
                        'name' => 'form_banner',
                        'type' => 'fileUpload',
                        'order' => 1,
                        'metadata' => [
                            'builderType' => 'banner',
                            'bannerUrl' => self::PIXEL_PNG,
                            'formBanner' => true,
                        ],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertValid();

        $row = FormField::query()
            ->where('form_id', $form->id)
            ->where('name', 'form_banner')
            ->firstOrFail();

        $rawMetadata = $row->metadata;
        $metadata = $rawMetadata instanceof \Illuminate\Support\Collection
            ? $rawMetadata->all()
            : (array) $rawMetadata;
        $stored = (string) ($metadata['bannerUrl'] ?? '');

        $this->assertStringStartsWith('form-images/'.$form->id.'/', $stored);
        $this->assertStringNotContainsString('data:', $stored);
        Storage::disk('public')->assertExists($stored);
    }

    public function test_legacy_data_url_is_reencoded_before_storage(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);
        $binary = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        $dataUrl = 'data:image/png;base64,'.base64_encode($binary.'APPENDED-PAYLOAD');

        $this->actingAs($admin)
            ->post(route('dashboard.events.forms.fields', [
                'event' => $event->id,
                'form' => $form->id,
            ]), [
                'fields' => [[
                    'id' => (string) Str::uuid(),
                    'label' => 'Form banner',
                    'description' => null,
                    'name' => 'form_banner',
                    'type' => 'fileUpload',
                    'order' => 1,
                    'metadata' => [
                        'builderType' => 'banner',
                        'bannerUrl' => $dataUrl,
                        'formBanner' => true,
                    ],
                ]],
            ])
            ->assertRedirect()
            ->assertValid();

        $row = FormField::query()->where('form_id', $form->id)->where('name', 'form_banner')->firstOrFail();
        $rawMetadata = $row->metadata;
        $metadata = $rawMetadata instanceof \Illuminate\Support\Collection
            ? $rawMetadata->all()
            : (array) $rawMetadata;
        $stored = (string) ($metadata['bannerUrl'] ?? '');

        self::assertStringStartsWith('form-images/'.$form->id.'/', $stored);
        self::assertStringNotContainsString('data:', $stored);
        Storage::disk('public')->assertExists($stored);
        self::assertStringNotContainsString('APPENDED-PAYLOAD', Storage::disk('public')->get($stored));
    }
}
