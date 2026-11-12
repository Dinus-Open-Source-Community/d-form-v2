<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole('member');

        return $user;
    }

    /**
     * @return array{0: Event, 1: Form}
     */
    private function eventWithForm(array $eventOverrides = []): array
    {
        $event = Event::factory()->create(array_merge([
            'status' => EventStatus::Published,
            'category' => 'rkt',
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ], $eventOverrides));

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        return [$event, $form];
    }

    public function test_member_cannot_view_reports_dashboard(): void
    {
        $this->actingAs($this->member())
            ->get(route('dashboard.reports.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_reports_route_redirects_to_events_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.reports.index'))
            ->assertRedirect(route('dashboard.events.index'));
    }

    public function test_admin_can_view_event_laporan_page(): void
    {
        [$event] = $this->eventWithForm();

        $this->actingAs($this->admin())
            ->get(route('dashboard.events.laporan', $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Events/Laporan')
                ->has('globalSummary')
                ->has('eventReporting.summary')
                ->has('eventReporting.attendanceLog.data'));
    }

    public function test_member_cannot_view_event_laporan_page(): void
    {
        [$event] = $this->eventWithForm();

        $this->actingAs($this->member())
            ->get(route('dashboard.events.laporan', $event))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_download_registrations_csv_with_expected_columns(): void
    {
        [$event, $form] = $this->eventWithForm();
        $participant = User::factory()->create([
            'name' => 'CSV Participant',
            'email' => 'csvparticipant@example.test',
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'ABC123',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.events.exports.registrations-csv', $event));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('submission_id', $content);
        $this->assertStringContainsString('answers_summary', $content);
        $this->assertStringContainsString('csvparticipant@example.test', $content);
    }

    public function test_member_cannot_download_registrations_csv(): void
    {
        [$event] = $this->eventWithForm();

        $this->actingAs($this->member())
            ->get(route('dashboard.events.exports.registrations-csv', $event))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_download_attendance_csv(): void
    {
        [$event, $form] = $this->eventWithForm();
        $scanner = $this->admin();
        $participant = User::factory()->create([
            'email' => 'attendee-report@example.test',
        ]);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $scanner->id,
            'scanned_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.events.exports.attendance-csv', $event));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('form_answer_id', $content);
        $this->assertStringContainsString($answer->id, $content);
        $this->assertStringContainsString('attendee-report@example.test', $content);
    }

    public function test_registrations_csv_neutralizes_formula_like_participant_values(): void
    {
        [$event, $form] = $this->eventWithForm();
        $form->update(['title' => '@SUM(1+1)']);

        FormField::factory()->create([
            'form_id' => $form->id,
            'label' => 'Nama Panggilan',
            'name' => 'nama_panggilan',
            'order' => 1,
        ]);
        FormField::factory()->create([
            'form_id' => $form->id,
            'label' => 'Catatan',
            'name' => 'catatan',
            'order' => 2,
        ]);

        $participant = User::factory()->create([
            'name' => "=cmd|'/C calc'!A1",
            'email' => 'formula-registration@example.test',
        ]);

        FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'answers' => [
                'nama_panggilan' => '-1+1',
                'catatan' => 'Peserta Biasa',
            ],
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.events.exports.registrations-csv', $event));
        $response->assertOk();

        $rows = $this->csvRows($response->streamedContent());

        self::assertSame("'@SUM(1+1)", $rows[1][2]);
        self::assertSame("'=cmd|'/C calc'!A1", $rows[1][3]);
        self::assertSame('Nama Panggilan: -1+1 | Catatan: Peserta Biasa', $rows[1][12]);
    }

    public function test_attendance_csv_neutralizes_formula_like_attendee_name(): void
    {
        [$event, $form] = $this->eventWithForm();
        $scanner = $this->admin();
        $participant = User::factory()->create([
            'name' => '-1+1',
            'email' => 'formula-attendance@example.test',
        ]);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
        ]);

        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $scanner->id,
            'scanned_at' => now(),
        ]);

        $response = $this->actingAs($scanner)
            ->get(route('dashboard.events.exports.attendance-csv', $event));
        $response->assertOk();

        $rows = $this->csvRows($response->streamedContent());

        self::assertSame("'-1+1", $rows[1][1]);
        self::assertSame($scanner->name, $rows[1][4]);
    }

    /**
     * @return list<list<string|null>>
     */
    private function csvRows(string $content): array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        return array_values(array_map(
            fn (string $line): array => str_getcsv($line),
            array_filter(explode("\n", trim($content)), fn (string $line): bool => $line !== '')
        ));
    }
}
