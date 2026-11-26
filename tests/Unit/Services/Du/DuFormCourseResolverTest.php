<?php

namespace Tests\Unit\Services\Du;

use App\Models\DuCourse;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Du\DuFormCourseResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class DuFormCourseResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggest_returns_only_one_exact_active_course(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Kelas A', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-c', 'title' => 'Kelas C', 'is_active' => false]);

        $resolver = app(DuFormCourseResolver::class);
        $field = FormField::factory()->create(['form_id' => Form::factory()->create()->id]);

        $this->assertSame('course-a', $resolver->suggest($field, 'course-a')?->uid);
        $this->assertNull($resolver->suggest($field, 'Kelas A'));
        $this->assertNull($resolver->suggest($field, 'course-c'));
        $this->assertNull($resolver->suggest($field, 'Kelas Tidak Ada'));
    }

    public function test_enabled_uses_the_field_flag_and_the_system_purpose(): void
    {
        $resolver = app(DuFormCourseResolver::class);

        $registration = Form::factory()->create(['metadata' => ['purpose' => 'registration']]);
        FormField::factory()->create([
            'form_id' => $registration->id,
            'name' => 'class',
            'metadata' => ['du_redeem_enabled' => false],
        ]);
        $this->assertFalse($resolver->enabled($registration));

        $flagged = Form::factory()->create(['metadata' => ['purpose' => 'registration']]);
        FormField::factory()->create([
            'form_id' => $flagged->id,
            'name' => 'class',
            'metadata' => ['du_redeem_enabled' => true],
        ]);
        $this->assertTrue($resolver->enabled($flagged));

        $flagged->load('formFields');
        $this->assertTrue($resolver->enabled($flagged));

        $dKelas = Form::factory()->create(['metadata' => ['purpose' => 'd_kelas']]);
        $this->assertTrue($resolver->enabled($dKelas));
    }

    public function test_class_field_picks_the_lowest_order_flagged_field(): void
    {
        $form = Form::factory()->create(['metadata' => ['purpose' => 'registration']]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'class_secondary',
            'order' => 5,
            'metadata' => ['du_redeem_enabled' => true],
        ]);
        $lowest = FormField::factory()->create([
            'form_id' => $form->id,
            'name' => 'class_primary',
            'order' => 2,
            'metadata' => ['du_redeem_enabled' => true],
        ]);

        $this->assertSame($lowest->id, app(DuFormCourseResolver::class)->classField($form)?->id);
    }

    public function test_active_suggestions_prefers_an_exact_uid_and_nulls_ambiguous_titles(): void
    {
        $courses = new Collection([
            new DuCourse(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]),
            new DuCourse(['uid' => 'course-b', 'title' => 'Kelas A', 'is_active' => true]),
            new DuCourse(['uid' => 'course-c', 'title' => 'Kelas C', 'is_active' => true]),
            new DuCourse(['uid' => 'Kelas C', 'title' => 'Other', 'is_active' => true]),
        ]);

        $index = app(DuFormCourseResolver::class)->activeSuggestions($courses);

        $this->assertSame('course-a', $index['course-a']?->uid);
        $this->assertNull($index['Kelas A']);
        $this->assertSame('course-c', $index['course-c']?->uid);
        // UID menang atas judul yang kebetulan sama.
        $this->assertSame('Kelas C', $index['Kelas C']?->uid);
        // Judul unik tetap dapat di-resolve ke course pemiliknya.
        $this->assertSame('Kelas C', $index['Other']?->uid);
    }

    public function test_active_suggestions_indexes_whatever_catalog_the_caller_passes(): void
    {
        $inactive = new DuCourse(['uid' => 'course-off', 'title' => 'Old', 'is_active' => false]);

        $index = app(DuFormCourseResolver::class)->activeSuggestions(new Collection([$inactive]));

        // Metode tidak memfilter is_active; pemanggil yang menentukan katalog aktif vs penuh.
        $this->assertSame($inactive, $index['course-off']);
        $this->assertSame($inactive, $index['Old']);
        $this->assertFalse($index['course-off']?->is_active);
    }

    public function test_mapping_for_drops_malformed_entries(): void
    {
        $field = FormField::factory()->create([
            'form_id' => Form::factory()->create()->id,
            'metadata' => [
                'du_option_map' => [
                    'AI Engineer' => ['course_uid' => 'course-ai', 'course_title' => 'AI Engineering'],
                    'No uid' => ['course_title' => 'Missing uid'],
                    'Non string uid' => ['course_uid' => 42],
                    2025 => ['course_uid' => 'course-2025', 'course_title' => 'Class 2025'],
                ],
            ],
        ]);

        $mapping = app(DuFormCourseResolver::class)->mappingFor($field);

        // JSON decoding turns the numeric option key into an int; the entry must survive, not be dropped.
        $this->assertSame(['AI Engineer', 2025], array_keys($mapping));
        $this->assertSame(['course_uid' => 'course-ai', 'course_title' => 'AI Engineering'], $mapping['AI Engineer']);
        $this->assertSame(['course_uid' => 'course-2025', 'course_title' => 'Class 2025'], $mapping[2025]);
        $this->assertArrayNotHasKey('No uid', $mapping);
        $this->assertArrayNotHasKey('Non string uid', $mapping);
    }
}
