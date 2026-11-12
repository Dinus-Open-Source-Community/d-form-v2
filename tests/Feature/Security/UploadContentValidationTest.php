<?php

namespace Tests\Feature\Security;

use App\Enums\Recruitment\ApplicationStage;
use App\Enums\UploadPurpose;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastDatasetController;
use App\Models\EmailDataset;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDocument;
use App\Models\User;
use App\Rules\SafeUploadFile;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Validasi unggahan berbasis isi (bukan nama/ekstensi klien) untuk setiap
 * purpose, plus jalur sajian berkas non-gambar.
 */
class UploadContentValidationTest extends TestCase
{
    use RefreshDatabase;

    private const DATASET_IMPORT_PATH = '/__test/broadcast-datasets';

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
        // Route broadcast hanya didaftarkan saat flag aktif (dibaca ketika file
        // route dimuat), jadi flag harus menyala sebelum route diakses.
        config(['features.broadcast' => true]);
    }

    private function pngBytes(): string
    {
        return (string) base64_decode(self::PNG_BASE64, true);
    }

    private function pdfBytes(string $body = '1 0 obj\n<<>>\nendobj\n'): string
    {
        return "%PDF-1.7\n".$body."%%EOF";
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    private function avatarUpload(UploadedFile $file): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->member())
            ->post(route('dashboard.profile.avatar.update'), ['avatar' => $file]);
    }

    /**
     * @param  list<string>  $lines
     */
    private function csv(string ...$lines): string
    {
        return implode("\n", $lines)."\n";
    }

    // -------------------------------------------------------------------------
    // Gambar (endpoint avatar memakai UploadPurpose::Image)
    // -------------------------------------------------------------------------

    public function test_image_with_correct_content_is_accepted_and_named_from_the_sniffed_type(): void
    {
        $response = $this->avatarUpload(
            UploadedFile::fake()->createWithContent('avatar.png', $this->pngBytes())
        );

        $response->assertRedirect();

        $files = Storage::disk('public')->allFiles();
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^avatars/[0-9a-f-]+/[0-9a-f]{32}\.png$#', $files[0]);
        $this->assertStringNotContainsString('avatar.png', $files[0]);
    }

    public function test_renamed_executable_is_rejected_by_content(): void
    {
        $this->avatarUpload(
            UploadedFile::fake()->createWithContent('photo.png', "MZ\x90\x00".str_repeat("\x00", 128))
        )->assertRedirect()->assertSessionHasErrors('avatar');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_extension_and_content_mismatch_is_rejected(): void
    {
        // PNG asli tetapi bernama .jpg (klien mengaku image/jpeg).
        $response = $this->avatarUpload(
            UploadedFile::fake()->createWithContent('photo.jpg', $this->pngBytes())
        );

        $response->assertRedirect()->assertSessionHasErrors('avatar');
        $this->assertStringContainsString('does not match its content', (string) session('errors')->first('avatar'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_svg_named_as_png_is_rejected(): void
    {
        $svg = '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';

        $this->avatarUpload(UploadedFile::fake()->createWithContent('logo.png', $svg))
            ->assertRedirect()
            ->assertSessionHasErrors('avatar');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_html_or_php_payload_named_as_png_is_rejected(): void
    {
        $html = '<html><body><script>alert(1)</script></body></html>';

        $this->avatarUpload(UploadedFile::fake()->createWithContent('photo.png', $html))
            ->assertRedirect()
            ->assertSessionHasErrors('avatar');

        $this->avatarUpload(UploadedFile::fake()->createWithContent('photo.png', "<?php echo 'x'; ?>"))
            ->assertRedirect()
            ->assertSessionHasErrors('avatar');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_double_extension_with_php_segment_is_rejected(): void
    {
        $this->avatarUpload(
            UploadedFile::fake()->createWithContent('shell.php.png', $this->pngBytes())
        )->assertRedirect()->assertSessionHasErrors('avatar');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_oversize_file_is_rejected_before_it_is_written(): void
    {
        $maxKb = (int) config('events.forms.upload_max_size_kb', 5120);
        $payload = $this->pngBytes().str_repeat('x', ($maxKb + 1) * 1024);

        $response = $this->avatarUpload(UploadedFile::fake()->createWithContent('big.png', $payload));

        $response->assertRedirect()->assertSessionHasErrors('avatar');
        $this->assertStringContainsString('5 MB', (string) session('errors')->first('avatar'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_image_with_excessive_dimensions_is_rejected_before_decoding(): void
    {
        $maxDimension = (int) config('events.forms.upload_max_dimension', 8000);
        // Header 20.000 px: jauh di atas cap, tetapi hanya 20.000 piksel total,
        // jadi test ini ringan — penolakan terjadi dari header, bukan dari decode.
        $wide = UploadedFile::fake()->image('wide.png', $maxDimension + 12_000, 2);

        $response = $this->avatarUpload($wide);

        $response->assertRedirect()->assertSessionHasErrors('avatar');
        $this->assertStringContainsString('dimensions', (string) session('errors')->first('avatar'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // -------------------------------------------------------------------------
    // Purpose non-gambar: rule yang sama dipakai FormRequest
    // -------------------------------------------------------------------------

    public function test_cv_purpose_accepts_pdf_and_rejects_png_or_fake_pdf(): void
    {
        $this->assertFalse($this->validateFile(
            UploadedFile::fake()->createWithContent('cv.pdf', $this->pdfBytes()),
            UploadPurpose::RecruitmentCv,
        )->fails());

        $this->assertTrue($this->validateFile(
            UploadedFile::fake()->createWithContent('cv.pdf', $this->pngBytes()),
            UploadPurpose::RecruitmentCv,
        )->fails());

        $this->assertTrue($this->validateFile(
            UploadedFile::fake()->createWithContent('cv.pdf', 'plain text pretending to be a pdf'),
            UploadPurpose::RecruitmentCv,
        )->fails());
    }

    public function test_pdf_requires_header_and_eof_markers(): void
    {
        $withoutEof = $this->validateFile(
            UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n"),
            UploadPurpose::RecruitmentCv,
        );
        $this->assertTrue($withoutEof->fails());
        $this->assertStringContainsString('PDF is incomplete', (string) $withoutEof->errors()->first('file'));

        $javascript = $this->validateFile(
            UploadedFile::fake()->createWithContent(
                'cv.pdf',
                "%PDF-1.7\n1 0 obj\n<< /OpenAction << /S /JavaScript /JS (alert(1)) >> >>\nendobj\n%%EOF",
            ),
            UploadPurpose::RecruitmentCv,
        );
        $this->assertTrue($javascript->fails());
        $this->assertStringContainsString('active content', (string) $javascript->errors()->first('file'));
    }

    public function test_default_document_policy_accepts_safe_docx_and_rejects_zip(): void
    {
        $docx = $this->officeDocument('docx', false, false);
        $this->assertFalse($this->validateFile($docx, UploadPurpose::FormAnswerFile)->fails());

        $zip = $this->zipUpload('archive.zip', ['payload.txt' => 'safe']);
        $this->assertTrue($this->validateFile($zip, UploadPurpose::FormAnswerFile)->fails());
    }

    public function test_ooxml_macro_traversal_and_malformed_packages_are_rejected(): void
    {
        $macro = $this->officeDocument('docx', true, false);
        self::assertTrue($this->validateFile($macro, UploadPurpose::FormAnswerFile)->fails());

        $traversal = $this->officeDocument('docx', false, true);
        self::assertTrue($this->validateFile($traversal, UploadPurpose::FormAnswerFile)->fails());

        $malformed = UploadedFile::fake()->createWithContent('broken.docx', "PK\x03\x04not-a-zip");
        self::assertTrue($this->validateFile($malformed, UploadPurpose::FormAnswerFile)->fails());
    }

    public function test_ooxml_expanded_size_is_capped_before_reading_entries(): void
    {
        config(['events.forms.upload_max_expanded_size_kb' => 1]);
        $expanded = $this->zipUpload('large.docx', [
            '[Content_Types].xml' => str_repeat('x', 700),
            'word/document.xml' => str_repeat('x', 700),
        ]);

        self::assertTrue($this->validateFile($expanded, UploadPurpose::FormAnswerFile)->fails());
    }

    public function test_ooxml_rejects_malformed_required_xml_inside_a_valid_zip(): void
    {
        $malformedXml = $this->zipUpload('broken.xml.docx', [
            '[Content_Types].xml' => '<Types></Types>',
            'word/document.xml' => '<document>',
        ]);

        self::assertTrue($this->validateFile($malformedXml, UploadPurpose::FormAnswerFile)->fails());
    }

    public function test_ooxml_rejects_symlink_entries_using_zip_external_attributes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'upload-symlink-');
        self::assertIsString($path);
        $zip = new \ZipArchive();
        self::assertSame(true, $zip->open($path, \ZipArchive::CREATE));
        $zip->addFromString('[Content_Types].xml', '<Types></Types>');
        $zip->addFromString('word/document.xml', '<document />');
        $zip->addFromString('word/link', 'target');
        self::assertTrue($zip->setExternalAttributesName(
            'word/link',
            \ZipArchive::OPSYS_UNIX,
            (0120000 | 0777) << 16,
        ));
        $zip->close();
        $contents = file_get_contents($path);
        unlink($path);

        $symlink = UploadedFile::fake()->createWithContent('symlink.docx', (string) $contents);

        self::assertTrue($this->validateFile($symlink, UploadPurpose::FormAnswerFile)->fails());
    }

    public function test_broadcast_attachment_purpose_rejects_gif_and_accepts_png_pdf(): void
    {
        $this->assertFalse($this->validateFile(
            UploadedFile::fake()->createWithContent('document.pdf', $this->pdfBytes()),
            UploadPurpose::BroadcastAttachment,
        )->fails());

        $this->assertFalse($this->validateFile(
            UploadedFile::fake()->createWithContent('image.png', $this->pngBytes()),
            UploadPurpose::BroadcastAttachment,
        )->fails());

        $gif = UploadedFile::fake()->image('anim.gif', 8, 8);
        $this->assertTrue($this->validateFile($gif, UploadPurpose::BroadcastAttachment)->fails());
    }

    public function test_dataset_csv_purpose_rejects_binary_nul_and_oversize(): void
    {
        $this->assertFalse($this->validateFile(
            UploadedFile::fake()->createWithContent('dataset.csv', $this->csv('name,email', 'A,a@example.test')),
            UploadPurpose::DatasetCsv,
        )->fails());

        $withNul = UploadedFile::fake()->createWithContent('dataset.csv', "name,email\nA,a@example.test\n\x00");
        $this->assertTrue($this->validateFile($withNul, UploadPurpose::DatasetCsv)->fails());

        $maxKb = (int) config('events.forms.dataset_csv_max_size_kb', 2048);
        $oversize = UploadedFile::fake()->createWithContent('dataset.csv', str_repeat("a@example.test\n", (int) ceil(($maxKb * 1024) / 15) + 10));
        $validator = $this->validateFile($oversize, UploadPurpose::DatasetCsv);
        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('2 MB', (string) $validator->errors()->first('file'));
    }

    public function test_csv_renamed_as_pdf_is_rejected(): void
    {
        $validator = $this->validateFile(
            UploadedFile::fake()->createWithContent('data.pdf', $this->csv('name,email', 'A,a@example.test')),
            UploadPurpose::DatasetCsv,
        );

        $this->assertTrue($validator->fails());
    }

    // -------------------------------------------------------------------------
    // Dataset import: baris dibatasi, NUL ditolak
    // -------------------------------------------------------------------------

    public function test_dataset_import_stops_at_the_configured_row_cap(): void
    {
        config(['events.forms.dataset_csv_max_rows' => 2]);
        $this->registerDatasetImportRoute();

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $file = UploadedFile::fake()->createWithContent('dataset.csv', $this->csv(
            'name,email',
            'A,a@example.test',
            'B,b@example.test',
            'C,c@example.test',
            'D,d@example.test',
        ));

        $this->actingAs($admin)
            ->post(self::DATASET_IMPORT_PATH, ['name' => 'Capped dataset', 'csv_file' => $file])
            ->assertRedirect();

        $dataset = EmailDataset::query()->where('name', 'Capped dataset')->firstOrFail();
        $this->assertSame(2, $dataset->recipients()->count());
    }

    public function test_dataset_import_rejects_a_csv_containing_nul_bytes(): void
    {
        $this->registerDatasetImportRoute();

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $file = UploadedFile::fake()->createWithContent('dataset.csv', "name,email\nA,a@example.test\n\x00");

        $this->actingAs($admin)
            ->post(self::DATASET_IMPORT_PATH, ['name' => 'Binary dataset', 'csv_file' => $file])
            ->assertSessionHasErrors('csv_file');

        $this->assertSame(0, EmailDataset::query()->where('name', 'Binary dataset')->count());
    }

    // -------------------------------------------------------------------------
    // Penyajian berkas non-gambar
    // -------------------------------------------------------------------------

    public function test_recruitment_document_download_is_attachment_nosniff(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $application = RecruitmentApplication::factory()->create(['stage' => ApplicationStage::Submitted]);
        $path = 'recruitment/'.$application->recruitment_period_id.'/'.$application->id.'/cv.pdf';
        Storage::disk('local')->put($path, $this->pdfBytes());

        RecruitmentDocument::query()->create([
            'recruitment_application_id' => $application->id,
            'cv_path' => $path,
            'cv_original_name' => 'cv.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 32,
            'portfolio_type' => 'none',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.recruitment.applications.documents.download', [
                'application' => $application->id,
                'type' => 'cv',
            ]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Route broadcast hanya terdaftar saat flag menyala waktu file route dimuat;
     * test ini mendaftarkan ulang satu endpoint agar bisa lewat HTTP + FormRequest.
     */
    private function registerDatasetImportRoute(): void
    {
        Route::middleware('web')->post(self::DATASET_IMPORT_PATH, [BroadcastDatasetController::class, 'store']);
    }

    private function validateFile(UploadedFile $file, UploadPurpose $purpose): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make(
            ['file' => $file],
            ['file' => SafeUploadFile::rules(true, $purpose)],
        );
    }

    /** @param array<string, string> $entries */
    private function zipUpload(string $name, array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload-test-');
        self::assertIsString($path);
        $zip = new \ZipArchive();
        self::assertSame(true, $zip->open($path, \ZipArchive::CREATE));
        foreach ($entries as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }
        $zip->close();
        $contents = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent($name, (string) $contents);
    }

    private function officeDocument(string $extension, bool $macro, bool $traversal): UploadedFile
    {
        $required = match ($extension) {
            'xlsx' => 'xl/workbook.xml',
            'pptx' => 'ppt/presentation.xml',
            default => 'word/document.xml',
        };
        $entries = [
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>',
            $required => '<document />',
        ];
        if ($macro) {
            $entries['word/vbaProject.bin'] = 'macro';
        }
        if ($traversal) {
            $entries['../escape.txt'] = 'escape';
        }

        return $this->zipUpload("document.{$extension}", $entries);
    }
}
