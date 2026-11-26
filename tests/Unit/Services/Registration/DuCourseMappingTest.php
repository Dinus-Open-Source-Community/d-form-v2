<?php

namespace Tests\Unit\Services\Registration;

use App\Models\Form;
use App\Models\FormField;
use App\Services\Registration\FormAnswerFileStorage;
use App\Services\Registration\FormAnswerInputMapper;
use Illuminate\Support\Collection;
use App\Services\Upload\SafeUploadStorage;
use Tests\TestCase;

class DuCourseMappingTest extends TestCase
{
    public function test_bundle_course_values_are_independent(): void
    {
        $storage = new FormAnswerFileStorage(app(SafeUploadStorage::class));
        $mapper = new FormAnswerInputMapper($storage);
        $fields = new Collection([new FormField(['name' => 'du_course_uid', 'input_type' => 'selectInput', 'metadata' => []])]);
        $mapped = $mapper->map([
            'du_course_uid' => 'leader',
            'bundle__du_course_uid__0' => 'member',
        ], $fields, new Form(), 1);

        self::assertSame('leader', $mapped['leader']['du_course_uid']);
        self::assertSame('member', $mapped['members'][0]['du_course_uid']);
    }
}
