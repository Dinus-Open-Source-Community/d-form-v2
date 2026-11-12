<?php

namespace Tests\Feature\Scan;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\AttendanceMethod;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use Database\Seeders\RecruitmentDivisionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Tests\TestCase;

class GlobalScanExportTest extends TestCase
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
     * @return array{0: Event, 1: User}
     */
    private function eventWithAttendance(string $attendeeName = 'Attendee One'): array
    {
        $admin = $this->admin();

        $event = Event::factory()->create(['title' => 'Global Export Event']);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration Form',
        ]);

        $participant = User::factory()->create([
            'name' => $attendeeName,
            'email' => 'attendee1@example.test',
        ]);

        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
            'review_status' => FormAnswerReviewStatus::Accepted,
            'registration_code' => 'CHK-EXP-001',
        ]);

        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $admin->id,
            'scanned_at' => now(),
        ]);

        return [$event, $admin];
    }

    /**
     * Bangun applicant tahap interview tanpa check-in (scan store yang check-in via QR).
     *
     * @return array{0: User, 1: RecruitmentApplication, 2: RecruitmentInterviewSession}
     */
    private function scheduledRecruitmentApplication(string $suffix): array
    {
        $this->seed(RecruitmentDivisionSeeder::class);

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $programming = RecruitmentDivision::query()->where('code', 'programming')->firstOrFail();
        $period = RecruitmentPeriod::factory()->create();

        $session = RecruitmentInterviewSession::query()->create([
            'recruitment_period_id' => $period->id,
            'recruitment_division_id' => $programming->id,
            'session_date' => now()->toDateString(),
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
            'location' => 'Lab DOSCOM',
            'room' => 'A101',
            'is_active' => true,
        ]);

        $application = RecruitmentApplication::factory()->create([
            'recruitment_period_id' => $period->id,
            'primary_division_id' => $programming->id,
            'registration_number' => 'OPREC-2026-0000'.$suffix,
            'stage' => ApplicationStage::Interview,
            'result' => ApplicationResult::Pending,
        ]);

        return [$staff, $application->fresh(), $session];
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => (string) Str::uuid(),
            'format' => 'csv',
        ]))->assertUnauthorized();
    }

    public function test_member_is_forbidden(): void
    {
        $this->actingAs($this->member())->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => (string) Str::uuid(),
            'format' => 'csv',
        ]))->assertForbidden();
    }

    public function test_event_without_update_permission_is_forbidden(): void
    {
        [$event] = $this->eventWithAttendance();

        $staff = User::factory()->create();
        $staff->assignRole('recruitment-staff');

        $this->actingAs($staff)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'csv',
        ]))->assertForbidden();
    }

    public function test_oprec_without_scan_permission_is_forbidden(): void
    {
        [, , $session] = $this->scheduledRecruitmentApplication('F');

        $eventsOnly = User::factory()->create();
        $eventsOnly->givePermissionTo('events.list');

        $this->actingAs($eventsOnly)->get(route('dashboard.scan.export', [
            'kind' => 'oprec',
            'target' => $session->id,
            'format' => 'csv',
        ]))->assertForbidden();
    }

    public function test_unknown_event_target_returns_not_found(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => (string) Str::uuid(),
            'format' => 'csv',
        ]))->assertNotFound();
    }

    public function test_unknown_oprec_target_returns_not_found(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard.scan.export', [
            'kind' => 'oprec',
            'target' => (string) Str::uuid(),
            'format' => 'csv',
        ]))->assertNotFound();
    }

    public function test_invalid_kind_format_and_target_are_rejected_with_client_errors(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'bogus',
            'target' => (string) Str::uuid(),
            'format' => 'csv',
        ]))->assertStatus(422);

        $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => (string) Str::uuid(),
            'format' => 'pdf',
        ]))->assertStatus(422);

        // Target kosong / bukan uuid = parameter rusak (422), bukan 404:
        // 404 hanya untuk uuid valid yang tidak ada di database.
        $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => '',
            'format' => 'csv',
        ]))->assertStatus(422);
    }

    public function test_array_and_non_uuid_query_params_are_rejected_with_422(): void
    {
        $admin = $this->admin();

        foreach ([
            '/admin/scan/export?kind=event&format=csv&target[]=x',
            '/admin/scan/export?kind[]=event&format=csv&target='.(string) Str::uuid(),
            '/admin/scan/export?format=csv&target='.(string) Str::uuid(),
            '/admin/scan/export?kind=event&format[]=csv&target='.(string) Str::uuid(),
            '/admin/scan/export?kind=event&format=csv&target=not-a-uuid',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertStatus(422);
        }
    }

    public function test_event_csv_export_contains_expected_columns_and_values(): void
    {
        Carbon::setTestNow('2026-09-15 10:30:00');

        [$event, $admin] = $this->eventWithAttendance();

        $response = $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload('attendance-'.$event->slug.'-20260915-103000.csv');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString(
            'scanned_at,attendee_name,attendee_email,form_title,registration_code,scanned_by_name,scanned_by_email',
            $content,
        );
        $this->assertStringContainsString('Attendee One', $content);
        $this->assertStringContainsString('attendee1@example.test', $content);
        $this->assertStringContainsString('Registration Form', $content);
        $this->assertStringContainsString('CHK-EXP-001', $content);
        $this->assertStringContainsString($admin->email, $content);

        Carbon::setTestNow();
    }

    public function test_event_xlsx_export_is_a_valid_xlsx_workbook(): void
    {
        Carbon::setTestNow('2026-09-15 10:30:00');

        [$event, $admin] = $this->eventWithAttendance();

        $response = $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'xlsx',
        ]));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $response->assertDownload('attendance-'.$event->slug.'-20260915-103000.xlsx');

        $content = $response->streamedContent();

        $this->assertSame('PK', substr($content, 0, 2), 'XLSX payload must be a zip (PK header), not CSV.');
        $this->assertGreaterThan(100, strlen($content));
        $this->assertStringNotContainsString('attendee_name', $content);

        $header = $this->readXlsxHeaderRow($content);

        $this->assertSame([
            'scanned_at',
            'attendee_name',
            'attendee_email',
            'form_title',
            'registration_code',
            'scanned_by_name',
            'scanned_by_email',
        ], $header);

        Carbon::setTestNow();
    }

    public function test_oprec_csv_export_contains_expected_columns_and_values(): void
    {
        Carbon::setTestNow('2026-09-15 10:30:00');

        [$staff, $application, $session] = $this->scheduledRecruitmentApplication('1');

        $this->actingAs($staff)->postJson(route('dashboard.scan.store'), [
            'raw' => $application->registration_number,
        ])->assertOk();

        $response = $this->actingAs($staff)->get(route('dashboard.scan.export', [
            'kind' => 'oprec',
            'target' => $session->id,
            'format' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $divisionSlug = Str::slug($session->division->name);
        $response->assertDownload('attendance-oprec-'.$divisionSlug.'-2026-09-15-20260915-103000.csv');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString(
            'checked_in_at,applicant_name,registration_number,nim,division,session_date,method,checked_in_by',
            $content,
        );
        $this->assertStringContainsString($application->registration_number, $content);
        $this->assertStringContainsString($application->full_name, $content);
        $this->assertStringContainsString($application->nim, $content);
        $this->assertStringContainsString($session->division->name, $content);
        $this->assertStringContainsString('2026-09-15', $content);
        $this->assertStringContainsString(',qr,', $content);

        Carbon::setTestNow();
    }

    public function test_event_exports_neutralize_formula_like_attendee_name(): void
    {
        [$event, $admin] = $this->eventWithAttendance('=HYPERLINK("http://x")');
        $query = ['kind' => 'event', 'target' => $event->id];

        $csv = $this->actingAs($admin)->get(route('dashboard.scan.export', [...$query, 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        self::assertStringNotContainsString(',=HYPERLINK', $csv);
        self::assertStringContainsString("'=HYPERLINK(", $csv);

        $xlsx = $this->actingAs($admin)->get(route('dashboard.scan.export', [...$query, 'format' => 'xlsx']))
            ->assertOk()
            ->streamedContent();

        $row = $this->readXlsxDataRow($xlsx);

        self::assertInstanceOf(StringCell::class, $row[1]);
        self::assertSame("'=HYPERLINK(\"http://x\")", $row[1]->getValue());
    }

    public function test_event_csv_export_records_pii_free_audit_with_row_count(): void
    {
        [$event, $admin] = $this->eventWithAttendance('Sensitive Attendee');

        Log::spy();

        $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'csv',
        ]))->assertOk()->streamedContent();

        Log::shouldHaveReceived('info')->once()->withArgs(
            static function (string $message, array $context) use ($admin, $event): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'event_csv_export.generated'
                    && $context === [
                        'actor_id' => $admin->id,
                        'event_id' => $event->id,
                        'export_type' => 'scan_event_csv',
                        'row_count' => 1,
                    ]
                    && ! str_contains($encoded, 'Sensitive Attendee')
                    && ! str_contains($encoded, 'attendee1@example.test');
            },
        );
    }

    public function test_event_xlsx_export_records_audit_with_row_count(): void
    {
        [$event, $admin] = $this->eventWithAttendance();

        Log::spy();

        $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'xlsx',
        ]))->assertOk()->streamedContent();

        Log::shouldHaveReceived('info')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'event_csv_export.generated'
                && $context === [
                    'actor_id' => $admin->id,
                    'event_id' => $event->id,
                    'export_type' => 'scan_event_xlsx',
                    'row_count' => 1,
                ],
        );
    }

    public function test_oprec_csv_export_records_pii_free_audit_with_row_count(): void
    {
        [$staff, $application, $session] = $this->scheduledRecruitmentApplication('1');

        $this->actingAs($staff)->postJson(route('dashboard.scan.store'), [
            'raw' => $application->registration_number,
        ])->assertOk();

        Log::spy();

        $this->actingAs($staff)->get(route('dashboard.scan.export', [
            'kind' => 'oprec',
            'target' => $session->id,
            'format' => 'csv',
        ]))->assertOk()->streamedContent();

        Log::shouldHaveReceived('info')->once()->withArgs(
            static function (string $message, array $context) use ($staff, $application, $session): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'event_csv_export.generated'
                    && $context === [
                        'actor_id' => $staff->id,
                        'event_id' => $session->id,
                        'export_type' => 'scan_oprec_csv',
                        'row_count' => 1,
                    ]
                    && ! str_contains($encoded, $application->full_name)
                    && ! str_contains($encoded, $application->nim)
                    && ! str_contains($encoded, $application->registration_number);
            },
        );
    }

    /**
     * AUDIT-P2-019: generator yang melempar di tengah stream tetap meninggalkan
     * tepat satu audit, dengan jumlah baris yang benar-benar sudah ditulis.
     */
    public function test_scan_export_audits_partial_output_when_the_stream_throws(): void
    {
        config(['scan.export_page_size' => 1]);

        [$event, $admin] = $this->eventWithAttendance();

        // Halaman kedua keyset (`scanned_at > kursor`) gagal setelah baris pertama
        // tertulis: mensimulasikan koneksi putus / error DB di tengah stream.
        DB::listen(static function (QueryExecuted $query): void {
            $sql = str_replace(['`', '"'], '', strtolower($query->sql));

            if (str_contains($sql, 'from event_attendances') && str_contains($sql, 'scanned_at >')) {
                throw new RuntimeException('scan stream exploded');
            }
        });

        Log::spy();

        $response = $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'csv',
        ]));

        $response->assertOk();

        $bufferLevel = ob_get_level();

        try {
            $response->streamedContent();
            self::fail('Kegagalan generator harus diteruskan ke pemanggil.');
        } catch (RuntimeException $exception) {
            self::assertSame('scan stream exploded', $exception->getMessage());
        } finally {
            // `streamedContent()` tidak menutup buffer-nya saat callback melempar.
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }

        Log::shouldHaveReceived('info')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'event_csv_export.generated'
                && $context === [
                    'actor_id' => $admin->id,
                    'event_id' => $event->id,
                    'export_type' => 'scan_event_csv',
                    'row_count' => 1,
                ],
        );
    }

    public function test_scan_export_audit_failure_never_aborts_the_download(): void
    {
        [$event, $admin] = $this->eventWithAttendance();

        Log::spy();
        Log::shouldReceive('error')->zeroOrMoreTimes()->withArgs(
            static function (string $message, array $context): bool {
                return $message === 'unhandled_exception'
                    && ($context['exception_class'] ?? null) === RuntimeException::class
                    && ($context['message'] ?? null) === RuntimeException::class.': [message redacted]'
                    && is_string($context['file'] ?? null)
                    && is_int($context['line'] ?? null)
                    && is_array($context['frames'] ?? null)
                    && is_string($context['correlation_id'] ?? null)
                    && Str::isUuid($context['correlation_id'])
                    && array_keys($context) === ['exception_class', 'message', 'file', 'line', 'frames', 'correlation_id'];
            },
        );
        Log::shouldReceive('info')->once()->andThrow(new RuntimeException('Logger unavailable'));
        Log::shouldReceive('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'event_csv_export_audit_failed'
                && $context['actor_id'] === $admin->id
                && $context['event_id'] === $event->id
                && $context['export_type'] === 'scan_event_csv'
                && $context['exception_class'] === RuntimeException::class,
        );

        $content = $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'csv',
        ]))->assertOk()->streamedContent();

        self::assertStringContainsString('Attendee One', $content);
    }

    public function test_event_export_keyset_pages_all_rows_sharing_one_timestamp_without_offset(): void
    {
        config(['scan.export_page_size' => 2]);

        [$event, $admin] = $this->eventWithAttendance();

        $scannedAt = Carbon::parse('2026-09-15 10:30:00');

        for ($index = 2; $index <= 5; $index++) {
            $participant = User::factory()->create([
                'name' => 'Attendee '.$index,
                'email' => 'attendee'.$index.'@example.test',
            ]);
            $answer = FormAnswer::factory()->create([
                'form_id' => $event->forms()->firstOrFail()->id,
                'user_id' => $participant->id,
                'review_status' => FormAnswerReviewStatus::Accepted,
            ]);
            EventAttendance::query()->create([
                'event_id' => $event->id,
                'form_answer_id' => $answer->id,
                'scanned_by_user_id' => $admin->id,
                'scanned_at' => $scannedAt->copy(),
            ]);
        }

        EventAttendance::query()->update(['scanned_at' => $scannedAt]);

        $expected = EventAttendance::query()->count();
        $this->assertSame(5, $expected);

        DB::enableQueryLog();
        $csv = $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'csv',
        ]))->assertOk()->streamedContent();
        $csvQueries = DB::getQueryLog();
        DB::flushQueryLog();

        $xlsx = $this->actingAs($admin)->get(route('dashboard.scan.export', [
            'kind' => 'event',
            'target' => $event->id,
            'format' => 'xlsx',
        ]))->assertOk()->streamedContent();
        $xlsxQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertStringNotContainsStringIgnoringCase('offset', $this->joinQueries($csvQueries));
        $this->assertStringNotContainsStringIgnoringCase('offset', $this->joinQueries($xlsxQueries));

        $csvRows = array_filter(explode("\n", trim($csv)), static fn (string $line): bool => $line !== '');
        $names = [];
        foreach (array_slice($csvRows, 1) as $line) {
            $names[] = str_getcsv($line)[1];
        }

        $this->assertCount($expected, $names, 'CSV must export every attendance row.');
        $this->assertCount($expected, array_unique($names), 'No attendance row may be exported twice.');
        $this->assertSame($expected, $this->countXlsxDataRows($xlsx));
    }

    public function test_oprec_export_keyset_pages_all_rows_sharing_one_timestamp_without_offset(): void
    {
        config(['scan.export_page_size' => 2]);

        [$staff, $application, $session] = $this->scheduledRecruitmentApplication('9');

        $checkedInAt = Carbon::parse('2026-09-15 10:30:00');

        for ($index = 1; $index <= 3; $index++) {
            $extra = RecruitmentApplication::factory()->create([
                'recruitment_period_id' => $application->recruitment_period_id,
                'primary_division_id' => $application->primary_division_id,
                'registration_number' => 'OPREC-2026-EX0'.$index,
                'stage' => ApplicationStage::Interview,
                'result' => ApplicationResult::Pending,
            ]);
            RecruitmentAttendance::query()->create([
                'recruitment_application_id' => $extra->id,
                'recruitment_interview_session_id' => $session->id,
                'method' => AttendanceMethod::Qr,
                'checked_in_at' => $checkedInAt->copy(),
                'checked_in_by' => $staff->id,
            ]);
        }

        RecruitmentAttendance::query()->update(['checked_in_at' => $checkedInAt]);

        $expected = RecruitmentAttendance::query()->count();
        $this->assertSame(3, $expected);

        DB::enableQueryLog();
        $csv = $this->actingAs($staff)->get(route('dashboard.scan.export', [
            'kind' => 'oprec',
            'target' => $session->id,
            'format' => 'csv',
        ]))->assertOk()->streamedContent();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertStringNotContainsStringIgnoringCase('offset', $this->joinQueries($queries));

        $csvRows = array_filter(explode("\n", trim($csv)), static fn (string $line): bool => $line !== '');
        $this->assertCount($expected, array_slice($csvRows, 1));
    }

    /**
     * @param  list<array{query: string, bindings: array<int, mixed>, time: float}>  $queries
     */
    private function joinQueries(array $queries): string
    {
        return implode("\n", array_column($queries, 'query'));
    }

    private function countXlsxDataRows(string $binary): int
    {
        $path = tempnam(sys_get_temp_dir(), 'scan-export-rows-');

        try {
            file_put_contents($path, $binary);

            $reader = new XlsxReader();
            $reader->open($path);

            $rows = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $ignored) {
                    $rows++;
                }
                break;
            }

            $reader->close();

            return $rows - 1;
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return list<\OpenSpout\Common\Entity\Cell>
     */
    private function readXlsxDataRow(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'scan-export-xlsx-');

        try {
            file_put_contents($path, $binary);

            $reader = new XlsxReader();
            $reader->open($path);

            $cells = [];
            $seenRows = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if (++$seenRows === 2) {
                        $cells = $row->cells;
                        break;
                    }
                }
                break;
            }

            $reader->close();

            return $cells;
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return list<mixed>
     */
    private function readXlsxHeaderRow(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'scan-export-xlsx-');

        try {
            file_put_contents($path, $binary);

            $reader = new XlsxReader();
            $reader->open($path);

            $header = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $header = array_map(static fn ($cell): mixed => $cell->getValue(), $row->cells);
                    break;
                }
                break;
            }

            $reader->close();

            return $header;
        } finally {
            @unlink($path);
        }
    }
}
