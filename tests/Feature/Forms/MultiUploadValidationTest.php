<?php

namespace Tests\Feature\Forms;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Services\Form\RulesBuilder;
use App\Services\Registration\FormAnswerInputMapper;
use App\Services\Registration\FormAnswerFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Mockery;
use Illuminate\Contracts\Filesystem\Filesystem;

final class MultiUploadValidationTest extends TestCase
{
    private function pdf(string $name = 'document.pdf', int $sizeKb = 1): UploadedFile
    {
        $header = "%PDF-1.7\n";
        $footer = "\n%%EOF";
        $padding = max(0, ($sizeKb * 1024) - strlen($header) - strlen($footer));

        return UploadedFile::fake()->createWithContent($name, $header.str_repeat('x', $padding).$footer);
    }

    /** @return array<string, mixed> */
    private function multipleRules(): array
    {
        $field = FormField::factory()->make([
            'input_type' => 'fileUpload',
            'name' => 'documents',
            'metadata' => [
                'builderType' => 'file_upload',
                'multiple' => true,
                'allowed_mime_types' => ['application/pdf'],
                'rules' => ['required' => true, 'mimes' => 'pdf'],
            ],
        ]);

        return RulesBuilder::build(RulesBuilder::extractRulesFromFields(collect([$field])));
    }

    public function test_file_upload_builder_defaults_to_safe_document_formats(): void
    {
        $field = FormField::factory()->make([
            'input_type' => 'fileUpload',
            'name' => 'documents',
            'metadata' => ['builderType' => 'file_upload', 'rules' => ['required' => true]],
        ]);

        $rules = RulesBuilder::build(RulesBuilder::extractRulesFromFields(collect([$field])));
        $valid = Validator::make(['documents' => $this->pdf()], $rules);
        $blocked = Validator::make(
            ['documents' => UploadedFile::fake()->createWithContent('old.doc', 'legacy')],
            $rules,
        );

        self::assertFalse($valid->fails());
        self::assertTrue($blocked->fails());
    }

    public function test_multiple_uploads_are_arrays_with_per_file_safe_validation(): void
    {
        $rules = $this->multipleRules();
        $validator = Validator::make([
            'documents' => [$this->pdf('one.pdf'), UploadedFile::fake()->createWithContent('two.pdf', 'not a pdf')],
        ], $rules);

        self::assertTrue($validator->fails());
        self::assertNotEmpty($validator->errors()->get('documents.1'));
    }

    public function test_multiple_uploads_are_limited_to_five_files(): void
    {
        $rules = $this->multipleRules();
        $files = array_map(fn (int $index): UploadedFile => $this->pdf("{$index}.pdf"), range(1, 6));

        $validator = Validator::make(['documents' => $files], $rules);

        self::assertTrue($validator->fails());
        self::assertNotEmpty($validator->errors()->get('documents'));
    }

    public function test_multiple_uploads_are_limited_to_twenty_megabytes_in_total(): void
    {
        $rules = $this->multipleRules();
        $files = array_map(fn (int $index): UploadedFile => $this->pdf("{$index}.pdf", $index === 5 ? 4097 : 4096), range(1, 5));

        $validator = Validator::make(['documents' => $files], $rules);

        self::assertTrue($validator->fails());
        self::assertNotEmpty($validator->errors()->get('documents'));
    }

    public function test_store_many_returns_private_paths_and_cleans_every_path_when_storage_fails(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->twice()->andReturn(true, false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $form = new Form();
        $form->id = 'form-id';
        $storage = app(FormAnswerFileStorage::class);

        $this->expectException(\RuntimeException::class);

        try {
            $storage->storeMany([$this->pdf('one.pdf'), $this->pdf('two.pdf')], $form);
        } catch (ValidationException $exception) {
            self::fail($exception->getMessage());
        }
    }

    public function test_store_many_preserves_original_failure_when_cleanup_also_fails_and_continues(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->times(3)->andReturn(true, true, false);
        $disk->shouldReceive('delete')->twice()->andReturnUsing(
            static function (string $path): never {
                throw new \RuntimeException('cleanup failed for '.$path);
            },
        );
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $form = new Form();
        $form->id = 'form-id';
        $storage = app(FormAnswerFileStorage::class);

        $this->expectExceptionMessage('Uploaded file could not be stored.');

        $storage->storeMany([$this->pdf('one.pdf'), $this->pdf('two.pdf'), $this->pdf('three.pdf')], $form);
    }

    public function test_single_upload_mapping_remains_a_string_for_legacy_answers(): void
    {
        Storage::fake('local');
        $form = new Form();
        $form->id = 'form-id';
        $field = FormField::factory()->make([
            'form_id' => 'form-id',
            'input_type' => 'fileUpload',
            'name' => 'cv',
            'metadata' => ['multiple' => false, 'rules' => ['required' => true, 'mimes' => 'pdf']],
        ]);

        $answers = app(FormAnswerInputMapper::class)->map(
            ['cv' => $this->pdf('cv.pdf')],
            collect([$field]),
            $form,
        )['leader'];

        self::assertIsString($answers['cv']);
        Storage::disk('local')->assertExists($answers['cv']);
    }

    public function test_mapper_keeps_multiple_answers_as_private_path_arrays_and_serializes_each_path(): void
    {
        Storage::fake('local');
        $form = new Form();
        $form->id = 'form-id';
        $field = FormField::factory()->make([
            'form_id' => 'form-id',
            'input_type' => 'fileUpload',
            'name' => 'documents',
            'metadata' => ['multiple' => true, 'rules' => ['required' => true, 'mimes' => 'pdf']],
        ]);

        $answers = app(FormAnswerInputMapper::class)->map(
            ['documents' => [$this->pdf('one.pdf'), $this->pdf('two.pdf')]],
            collect([$field]),
            $form,
        )['leader'];

        self::assertIsArray($answers['documents']);
        self::assertCount(2, $answers['documents']);
        foreach ($answers['documents'] as $path) {
            self::assertIsString($path);
            Storage::disk('local')->assertExists($path);
        }

        $answer = new FormAnswer(['form_id' => 'form-id']);
        $answer->id = 'answer-id';
        $answer->form_id = 'form-id';
        $answer->answers = $answers;
        $serialized = app(FormAnswerFileStorage::class)->answerUrls($answer, $answers, ['documents']);
        self::assertIsArray($serialized['documents']);
        self::assertCount(2, $serialized['documents']);
        self::assertStringContainsString('/form-answers/answer-id/files/documents/', $serialized['documents'][0]);
    }
}
