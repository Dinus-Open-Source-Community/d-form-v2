<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Models\EmailBroadcast;
use App\Models\User;
use App\Services\Broadcasting\BroadcastInlineImageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * BE-P3-006: a storage failure while migrating an inline image must be logged
 * with a correlation id and must never look like a successful migration.
 */
class BroadcastInlineImageStorageFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_failure_is_logged_and_reported_as_skipped(): void
    {
        $broadcast = $this->draftBroadcast();

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        Log::spy();

        $html = '<p><img src="data:image/png;base64,'.$this->pngBase64().'"></p>';

        $result = app(BroadcastInlineImageService::class)->migrateHtml($broadcast, $html);

        $this->assertSame(0, $result['migrated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('data:image/png;base64,', $result['html']);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($broadcast): bool {
                return $message === 'broadcast_inline_image.storage_failed'
                    && is_string($context['correlation_id'] ?? null)
                    && Str::isUuid($context['correlation_id'])
                    && ($context['broadcast_id'] ?? null) === (string) $broadcast->id
                    && ($context['disk'] ?? null) === 'public'
                    && ($context['exception_class'] ?? null) === \RuntimeException::class
                    && str_contains((string) ($context['message'] ?? ''), '[message redacted]');
            });
    }

    /** A real PNG encoded by GD so the service reaches the storage call. */
    private function pngBase64(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image, null, 6);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return base64_encode($binary);
    }

    private function draftBroadcast(): EmailBroadcast
    {
        $admin = User::factory()->create();

        return EmailBroadcast::query()->create([
            'name' => 'Storage failure',
            'scheduled_at' => now()->addDay(),
            'delay_min' => 0,
            'delay_max' => 0,
            'status' => EmailBroadcastStatus::Draft,
            'created_by' => $admin->id,
        ]);
    }
}
