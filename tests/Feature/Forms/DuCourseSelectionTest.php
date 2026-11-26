<?php

namespace Tests\Feature\Forms;

use App\Models\DuCourse;
use App\Models\FormField;
use App\Models\User;
use App\Services\Du\DuCourseFieldService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DuCourseSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_list_endpoint_returns_only_active_cached_courses(): void
    {
        Http::fake();
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Beta', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Alpha', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-c', 'title' => 'Gamma', 'is_active' => false]);

        $this->actingAs($this->admin())
            ->getJson('/admin/du/courses')
            ->assertOk()
            ->assertExactJson([
                'courses' => [
                    ['uid' => 'course-a', 'title' => 'Alpha'],
                    ['uid' => 'course-b', 'title' => 'Beta'],
                ],
            ]);

        // Endpoint baca hanya menyentuh cache lokal, bukan API DU.
        Http::assertNothingSent();
    }

    public function test_guest_and_member_cannot_read_the_catalog(): void
    {
        $this->getJson('/admin/du/courses')->assertUnauthorized();

        $member = User::factory()->create();
        $member->assignRole('member');
        $this->actingAs($member)->getJson('/admin/du/courses')->assertRedirect();
    }

    public function test_apply_options_filters_active_courses_by_the_selected_uids(): void
    {
        $this->seedActiveCourses();

        $field = app(DuCourseFieldService::class)->applyOptions(
            $this->courseField(['selected_course_uids' => ['course-a', 'course-c']]),
        );

        $this->assertSame(['course-a', 'course-c'], $this->optionValues($field));
    }

    public function test_apply_options_shows_all_active_courses_when_selection_is_absent(): void
    {
        $this->seedActiveCourses();

        $field = app(DuCourseFieldService::class)->applyOptions($this->courseField());

        $this->assertSame(['course-a', 'course-b', 'course-c'], $this->optionValues($field));
    }

    public function test_apply_options_shows_no_course_when_selection_is_an_empty_array(): void
    {
        $this->seedActiveCourses();

        $field = app(DuCourseFieldService::class)->applyOptions(
            $this->courseField(['selected_course_uids' => []]),
        );

        $this->assertSame([], $this->optionValues($field));
    }

    public function test_option_labels_fall_back_to_legacy_rules_csv(): void
    {
        $field = new FormField([
            'name' => 'class',
            'metadata' => ['rules' => ['in' => 'AI Engineer, DevOps']],
        ]);

        $this->assertSame(
            ['AI Engineer', 'DevOps'],
            app(DuCourseFieldService::class)->optionLabels($field),
        );
    }

    public function test_option_labels_fall_back_to_legacy_options(): void
    {
        $field = new FormField([
            'name' => 'class',
            'metadata' => ['options' => 'Data Science,Cyber Security'],
        ]);

        $this->assertSame(
            ['Data Science', 'Cyber Security'],
            app(DuCourseFieldService::class)->optionLabels($field),
        );
    }

    public function test_option_labels_union_keeps_every_source_when_they_disagree(): void
    {
        $field = new FormField([
            'name' => 'class',
            'metadata' => [
                'optionChoices' => [
                    ['label' => 'AI Engineer'],
                    ['label' => 'DevOps'],
                ],
                'rules' => ['in' => 'DevOps,Data Science'],
                'options' => 'Cyber Security',
            ],
        ]);

        $this->assertSame(
            ['AI Engineer', 'DevOps', 'Data Science', 'Cyber Security'],
            app(DuCourseFieldService::class)->optionLabels($field),
        );
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function seedActiveCourses(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Alpha', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-b', 'title' => 'Beta', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-c', 'title' => 'Gamma', 'is_active' => true]);
        DuCourse::factory()->create(['uid' => 'course-z', 'title' => 'Zeta', 'is_active' => false]);
    }

    /** @param  array<string, mixed>  $metadata */
    private function courseField(array $metadata = []): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'select',
            'label' => 'Kelas',
            'description' => null,
            'name' => 'du_course_uid',
            'order' => 0,
            'metadata' => array_merge([
                'system' => true,
                'locked' => true,
                'options_source' => 'du_catalog',
                'rules' => ['required' => true],
            ], $metadata),
            'is_append' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function optionValues(array $field): array
    {
        $choices = $field['metadata']['optionChoices'] ?? [];

        return array_values(array_map(
            static fn (array $choice): string => (string) $choice['value'],
            is_array($choices) ? $choices : [],
        ));
    }
}
