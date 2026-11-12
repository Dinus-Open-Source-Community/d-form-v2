<?php

namespace Tests\Feature\Dashboard\Events;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EventAttendanceCsvExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_attendance_export_audits_row_count_without_recording_csv_values(): void
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);
        $participant = User::factory()->create([
            'name' => 'Private Attendance Participant',
            'email' => 'private.attendance.participant@example.test',
        ]);
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
        ]);
        $scanner = User::factory()->create([
            'name' => 'Private Attendance Scanner',
            'email' => 'private.attendance.scanner@example.test',
        ]);
        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $scanner->id,
            'scanned_at' => now(),
        ]);

        $actor = User::factory()->create();
        $actor->assignRole('admin');
        Log::spy();

        $response = $this->actingAs($actor)
            ->get(route('dashboard.events.exports.attendance-csv', $event));

        $response->assertOk();
        $csv = (string) preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());
        self::assertStringContainsString($participant->email, $csv);
        self::assertStringContainsString($scanner->email, $csv);
        self::assertCount(2, array_filter(explode("\n", trim($csv)), static fn (string $line): bool => $line !== ''));

        Log::shouldHaveReceived('info')->once()->withArgs(
            static function (string $message, array $context) use ($actor, $event, $participant, $scanner): bool {
                $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'event_csv_export.generated'
                    && $context === [
                        'actor_id' => $actor->id,
                        'event_id' => $event->id,
                        'export_type' => 'attendance',
                        'row_count' => 1,
                    ]
                    && ! str_contains($encodedContext, $participant->name)
                    && ! str_contains($encodedContext, $participant->email)
                    && ! str_contains($encodedContext, $scanner->name)
                    && ! str_contains($encodedContext, $scanner->email);
            },
        );
    }
}
