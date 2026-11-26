<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Models\EmailBroadcast;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Validasi unggahan inline image editor broadcast.
 * Modul broadcast nonaktif secara default (`FEATURE_BROADCAST=false`); test env
 * menyalakannya lewat `phpunit.xml` agar route ikut terdaftar saat boot.
 */
class BroadcastInlineImageValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    private function draftBroadcast(): EmailBroadcast
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin);

        return EmailBroadcast::query()->create([
            'name' => 'Test inline image',
            'scheduled_at' => now()->addDay(),
            'delay_min' => 0,
            'delay_max' => 0,
            'status' => EmailBroadcastStatus::Draft,
            'created_by' => $admin->id,
        ]);
    }

    public function test_non_image_upload_is_rejected_on_file_key(): void
    {
        $broadcast = $this->draftBroadcast();

        $this->postJson(route('dashboard.broadcasts.inline-image.upload', $broadcast), [
            'file' => UploadedFile::fake()->create('catatan.pdf', 10, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_image_upload_returns_stored_path_and_url(): void
    {
        $broadcast = $this->draftBroadcast();

        $this->postJson(route('dashboard.broadcasts.inline-image.upload', $broadcast), [
            'file' => UploadedFile::fake()->image('inline.webp', 32, 32),
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    /**
     * Broadcast non-draft dijawab 422 lebih dulu, bukan error validasi `file`;
     * urutan ini dipertahankan saat validasi pindah ke Form Request.
     */
    public function test_non_draft_broadcast_is_rejected_before_file_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $broadcast = EmailBroadcast::query()->create([
            'name' => 'Sudah terjadwal',
            'scheduled_at' => now()->addDay(),
            'delay_min' => 0,
            'delay_max' => 0,
            'status' => EmailBroadcastStatus::Scheduled,
            'created_by' => $admin->id,
        ]);

        $this->postJson(route('dashboard.broadcasts.inline-image.upload', $broadcast))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Gambar hanya dapat diunggah saat draft.');
    }
}
