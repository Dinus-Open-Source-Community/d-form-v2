<?php

namespace Tests\Unit\Services;

use App\Services\Upload\SafeUploadStorage;
use App\Services\Upload\UploadStorageTarget;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SafeUploadStorageTest extends TestCase
{
    public function test_image_is_reencoded_before_public_storage(): void
    {
        Storage::fake('public');

        $png = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        $file = UploadedFile::fake()->createWithContent('avatar.png', $png."APPENDED-PAYLOAD");

        $path = app(SafeUploadStorage::class)->store($file, UploadStorageTarget::public('avatars/test'));
        $stored = Storage::disk('public')->get($path);

        self::assertNotSame('', $stored);
        self::assertStringNotContainsString('APPENDED-PAYLOAD', $stored);
    }
}
