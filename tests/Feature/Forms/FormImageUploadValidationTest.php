<?php

namespace Tests\Feature\Forms;

use App\Models\Event;
use App\Models\Form;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Validasi unggahan gambar form builder; key error `file` dipakai editor
 * Tiptap untuk menampilkan pesan, jadi key dan whitelist tipe tidak boleh berubah.
 */
class FormImageUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    private function adminEventForm(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $event = Event::factory()->create();
        $form = Form::factory()->create(['event_id' => $event->id]);

        return [$admin, $event, $form];
    }

    public function test_non_image_upload_is_rejected_on_file_key(): void
    {
        [$admin, $event, $form] = $this->adminEventForm();

        $this->actingAs($admin)
            ->postJson(route('dashboard.events.forms.images', ['event' => $event, 'form' => $form]), [
                'file' => UploadedFile::fake()->create('catatan.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_image_upload_is_stored_under_form_images_directory(): void
    {
        [$admin, $event, $form] = $this->adminEventForm();

        $this->actingAs($admin)
            ->postJson(route('dashboard.events.forms.images', ['event' => $event, 'form' => $form]), [
                'file' => UploadedFile::fake()->image('banner.png', 64, 64),
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertCount(1, Storage::disk('public')->files('form-images/'.$form->id));
    }
}
