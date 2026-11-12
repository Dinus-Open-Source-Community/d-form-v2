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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * BE-P2-030: export CSV kehadiran memakai keyset `(scanned_at, id)` ascending,
 * bukan `chunk()`/OFFSET. `scanned_at` hanya presisi detik, jadi seluruh baris
 * fixture sengaja berbagi satu timestamp identik untuk menguji tie antar halaman.
 */
class EventAttendanceCsvExportKeysetTest extends TestCase
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

    /**
     * @return array{0: Event, 1: Form}
     */
    private function eventWithForm(): array
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'category' => 'rkt',
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);

        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);

        return [$event, $form];
    }

    private function attendanceWithSharedTimestamp(Event $event, Form $form, int $count): void
    {
        $scannedAt = '2026-02-01 08:00:00';

        for ($index = 0; $index < $count; $index++) {
            $answer = FormAnswer::factory()->create([
                'form_id' => $form->id,
            ]);

            EventAttendance::query()->create([
                'event_id' => $event->id,
                'form_answer_id' => $answer->id,
                'scanned_at' => $scannedAt,
            ]);
        }
    }

    /**
     * @return list<list<string|null>>
     */
    private function exportRows(Event $event): array
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.events.exports.attendance-csv', $event));

        $response->assertOk();
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());

        return array_values(array_map(
            static fn (string $line): array => str_getcsv($line),
            array_filter(explode("\n", trim($content)), static fn (string $line): bool => $line !== '')
        ));
    }

    public function test_export_writes_every_row_with_identical_scanned_at_exactly_once(): void
    {
        config(['registration.attendance_export_page_size' => 7]);

        [$event, $form] = $this->eventWithForm();
        $this->attendanceWithSharedTimestamp($event, $form, 15);

        $rows = $this->exportRows($event);

        self::assertSame(
            ['scanned_at', 'attendee_name', 'attendee_email', 'form_answer_id', 'scanned_by_name', 'scanned_by_email'],
            $rows[0],
        );

        $dataRows = array_slice($rows, 1);
        $exportedFormAnswerIds = array_map(static fn (array $row): string => (string) $row[3], $dataRows);

        $expectedFormAnswerIds = EventAttendance::query()
            ->where('event_id', $event->id)
            ->orderBy('scanned_at')
            ->orderBy('id')
            ->pluck('form_answer_id')
            ->all();

        self::assertCount((int) EventAttendance::query()->where('event_id', $event->id)->count(), $dataRows);
        self::assertCount(count($dataRows), array_unique($exportedFormAnswerIds));
        self::assertSame($expectedFormAnswerIds, $exportedFormAnswerIds);

        $expectedTimestamp = EventAttendance::query()
            ->where('event_id', $event->id)
            ->firstOrFail()
            ->scanned_at
            ->timezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');

        foreach ($dataRows as $row) {
            self::assertSame($expectedTimestamp, $row[0]);
        }
    }

    public function test_export_paginates_with_keyset_cursor_and_never_uses_offset(): void
    {
        config(['registration.attendance_export_page_size' => 4]);

        [$event, $form] = $this->eventWithForm();
        $this->attendanceWithSharedTimestamp($event, $form, 9);

        DB::enableQueryLog();

        try {
            $this->actingAs($this->admin())
                ->get(route('dashboard.events.exports.attendance-csv', $event))
                ->assertOk()
                ->streamedContent();

            $queries = array_values(array_map(
                static fn (array $entry): string => (string) ($entry['query'] ?? ''),
                DB::getQueryLog()
            ));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        foreach ($queries as $sql) {
            self::assertStringNotContainsString(' offset ', strtolower($sql));
        }

        $pageQueries = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'event_attendances') && str_contains(strtolower($sql), ' limit ')
        ));

        // 9 baris / 4 per halaman = halaman berisi (4, 4, 1); loop berhenti setelah
        // halaman ketiga karena hasilnya kurang dari page size.
        self::assertCount(3, $pageQueries);

        $normalized = array_map(
            static fn (string $sql): string => str_replace(['"', '`'], '', $sql),
            $pageQueries,
        );

        // Halaman kedua dan ketiga wajib memakai predikat kursor komposit, bukan
        // sekadar ORDER BY: tanpa ini test tetap hijau saat filter kursor hilang.
        foreach (array_slice($normalized, 1) as $cursorSql) {
            self::assertStringContainsString('scanned_at > ', $cursorSql);
            self::assertStringContainsString('id > ', $cursorSql);
        }
    }

    public function test_pagination_indexes_are_created_by_migration(): void
    {
        $attendanceIndexes = collect(Schema::getIndexes('event_attendances'))->keyBy('name');
        self::assertSame(
            ['event_id', 'scanned_at', 'id'],
            $attendanceIndexes['event_attendances_event_id_scanned_at_id_index']['columns'],
        );

        $answerIndexes = collect(Schema::getIndexes('form_answers'))->keyBy('name');
        self::assertSame(
            ['form_id', 'created_at', 'id'],
            $answerIndexes['form_answers_form_id_created_at_id_index']['columns'],
        );
    }
}
