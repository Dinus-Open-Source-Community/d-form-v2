<?php

namespace Tests\Unit\Rules;

use App\Rules\SafeUploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class SafeUploadFileTest extends TestCase
{
    public function test_valid_png_content_is_accepted(): void
    {
        $file = UploadedFile::fake()->createWithContent('image.png', (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));

        $validator = Validator::make(['file' => $file], ['file' => SafeUploadFile::rules(true, ['jpg', 'png'])]);

        self::assertFalse($validator->fails());
    }

    public function test_invalid_image_payload_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('image.jpg', 'not-an-image');

        $validator = Validator::make(['file' => $file], ['file' => SafeUploadFile::rules(true, ['jpg', 'png'])]);

        self::assertTrue($validator->fails());
    }

    public function test_truncated_image_payload_is_rejected(): void
    {
        $png = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        $file = UploadedFile::fake()->createWithContent('image.png', substr($png, 0, -2));

        $validator = Validator::make(['file' => $file], ['file' => SafeUploadFile::rules(true, ['png'])]);

        self::assertTrue($validator->fails());
    }

    public function test_file_larger_than_five_megabytes_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('document.pdf', "%PDF-\n".str_repeat('x', 5 * 1024 * 1024));

        $validator = Validator::make(['file' => $file], ['file' => SafeUploadFile::rules(true, ['pdf'])]);

        self::assertTrue($validator->fails());
    }

    public function test_path_like_filename_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('image:stream.png', (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));

        $validator = Validator::make(['file' => $file], ['file' => SafeUploadFile::rules(true, ['png'])]);

        self::assertTrue($validator->fails());
    }

    public function test_pdf_without_eof_marker_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n");

        $validator = Validator::make(['file' => $file], ['file' => SafeUploadFile::rules(true, ['pdf'])]);

        self::assertTrue($validator->fails());
    }

    public function test_pdf_with_active_javascript_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'document.pdf',
            "%PDF-1.7\n1 0 obj\n<< /OpenAction << /S /JavaScript /JS (app.alert(1)) >> >>\nendobj\n%%EOF",
        );

        $validator = Validator::make(['file' => $file], ['file' => SafeUploadFile::rules(true, ['pdf'])]);

        self::assertTrue($validator->fails());
    }

    public function test_valid_pdf_structure_is_accepted(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'document.pdf',
            "%PDF-1.7\n1 0 obj\n<<>>\nendobj\nstartxref\n9\n%%EOF",
        );

        $validator = Validator::make(['file' => $file], ['file' => SafeUploadFile::rules(true, ['pdf'])]);

        self::assertFalse($validator->fails());
    }
}
