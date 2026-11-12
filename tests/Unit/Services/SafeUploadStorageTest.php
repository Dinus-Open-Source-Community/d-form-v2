<?php

namespace Tests\Unit\Services;

use App\Enums\UploadPurpose;
use App\Services\Upload\SafeUploadStorage;
use App\Services\Upload\UploadStorageTarget;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

        $path = app(SafeUploadStorage::class)->store(
            $file,
            UploadStorageTarget::public('avatars/test'),
            UploadPurpose::Image,
        );
        $stored = Storage::disk('public')->get($path);

        self::assertNotSame('', $stored);
        self::assertStringNotContainsString('APPENDED-PAYLOAD', $stored);
    }

    public function test_non_image_is_stored_with_a_generated_name_and_the_sniffed_extension(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('My CV (final).pdf', "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF");

        $path = app(SafeUploadStorage::class)->store(
            $file,
            UploadStorageTarget::local('recruitment/test'),
            UploadPurpose::RecruitmentCv,
        );

        self::assertMatchesRegularExpression('#^recruitment/test/[0-9a-f]{32}\.pdf$#', $path);
        self::assertStringNotContainsString('My CV', $path);
        self::assertStringStartsWith('%PDF-', (string) Storage::disk('local')->get($path));
    }

    public function test_content_that_does_not_match_the_purpose_is_rejected_before_storage(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('cv.pdf', 'plain text, not a pdf');

        $this->expectException(ValidationException::class);

        app(SafeUploadStorage::class)->store(
            $file,
            UploadStorageTarget::local('recruitment/test'),
            UploadPurpose::RecruitmentCv,
        );
    }
}
