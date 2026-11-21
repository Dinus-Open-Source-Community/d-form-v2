<?php

namespace Tests\Feature\Forms;

use App\Models\EventAttendance;
use App\Models\FormAnswer;
use App\Models\FormSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class FormSessionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_allows_one_row_per_slot_and_blocks_duplicates(): void
    {
        $answer = FormAnswer::factory()->create();
        $session = FormSession::factory()->create([
            'form_id' => $answer->form_id,
            'session_date' => today(),
            'label' => 'Main session',
        ]);

        EventAttendance::query()->create([
            'event_id' => $answer->form->event_id,
            'form_answer_id' => $answer->id,
            'form_session_id' => $session->id,
            'attendance_slot' => $session->id,
            'scanned_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        EventAttendance::query()->create([
            'event_id' => $answer->form->event_id,
            'form_answer_id' => $answer->id,
            'form_session_id' => $session->id,
            'attendance_slot' => $session->id,
            'scanned_at' => now(),
        ]);
    }

    public function test_attendance_allows_different_slots_for_same_answer(): void
    {
        $answer = FormAnswer::factory()->create();
        $session = FormSession::factory()->create([
            'form_id' => $answer->form_id,
            'session_date' => today(),
            'label' => 'Main session',
        ]);

        foreach (['morning', 'afternoon'] as $slot) {
            EventAttendance::query()->create([
                'event_id' => $answer->form->event_id,
                'form_answer_id' => $answer->id,
                'form_session_id' => $session->id,
                'attendance_slot' => $slot,
                'scanned_at' => now(),
            ]);
        }

        $this->assertDatabaseCount('event_attendances', 2);
    }
}
