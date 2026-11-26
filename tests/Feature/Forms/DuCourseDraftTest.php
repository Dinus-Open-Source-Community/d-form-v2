<?php

namespace Tests\Feature\Forms;

use App\Models\DuCourse;
use App\Models\Event;
use App\Models\Form;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DuCourseDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_d_kelas_draft_has_purpose_mode_and_one_kelas_field(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        Http::fake();
        [$admin, $event] = $this->owner();

        $response = $this->actingAs($admin)->postJson(
            route('dashboard.events.forms.draft', $event),
            ['purpose' => 'd_kelas', 'mode' => 'single'],
        )->assertOk();

        $response->assertJsonPath('form.metadata.purpose', 'd_kelas')
            ->assertJsonPath('form.metadata.registration_mode', 'single')
            ->assertJsonPath('fields.0.name', 'du_course_uid')
            ->assertJsonPath('fields.0.label', 'Kelas')
            ->assertJsonPath('fields.0.metadata.rules.required', true)
            ->assertJsonPath('fields.0.metadata.optionChoices.0.label', 'Kelas A');
        Http::assertNothingSent();
    }

    public function test_untrusted_purpose_and_mode_fall_back_to_registration_single(): void
    {
        Http::fake();
        [$admin, $event] = $this->owner();
        $url = route('dashboard.events.forms.draft', $event);

        $bogus = $this->actingAs($admin)->postJson($url, ['purpose' => 'bogus', 'mode' => 'bogus'])->assertOk();
        $bogus->assertJsonPath('form.metadata.purpose', 'registration')
            ->assertJsonPath('form.metadata.registration_mode', 'single');

        $registration = $this->actingAs($admin)->postJson($url, ['purpose' => 'registration', 'mode' => 'single'])->assertOk();

        $this->assertSame($bogus->json('form.id'), $registration->json('form.id'));
        $this->assertSame([], $registration->json('fields'));
        Http::assertNothingSent();
    }

    public function test_drafts_are_scoped_per_purpose_and_do_not_hijack_each_other(): void
    {
        DuCourse::factory()->create(['uid' => 'course-a', 'title' => 'Kelas A', 'is_active' => true]);
        Http::fake();
        [$admin, $event] = $this->owner();
        $url = route('dashboard.events.forms.draft', $event);

        $kelas = $this->actingAs($admin)->postJson($url, ['purpose' => 'd_kelas', 'mode' => 'single'])->json('form.id');
        $registration = $this->actingAs($admin)->postJson($url, ['purpose' => 'registration', 'mode' => 'single'])->json('form.id');
        $kelasAgain = $this->actingAs($admin)->postJson($url, ['purpose' => 'd_kelas', 'mode' => 'single'])->json('form.id');

        $this->assertNotSame($kelas, $registration);
        $this->assertSame($kelas, $kelasAgain);
        $this->assertSame('d_kelas', Form::find($kelas)->metadata['purpose']);
        $this->assertSame('registration', Form::find($registration)->metadata['purpose']);
    }

    public function test_first_d_kelas_create_syncs_once_when_cache_is_empty_and_still_succeeds_on_failure(): void
    {
        Http::fake(['*' => Http::response(['secret' => 'x'], 503)]);
        [$admin, $event] = $this->owner();

        $this->actingAs($admin)->postJson(
            route('dashboard.events.forms.draft', $event),
            ['purpose' => 'd_kelas', 'mode' => 'single'],
        )->assertOk()->assertJsonPath('fields.0.name', 'du_course_uid');

        Http::assertSentCount(1);
    }

    /** @return array{0: User, 1: Event} */
    private function owner(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return [$admin, Event::factory()->create(['created_by' => $admin->id])];
    }
}
