<?php

namespace App\Console\Commands\Stress;

use App\Models\Event;
use App\Services\Reporting\EventReportingQuery;
use App\Services\Scan\ScanStreamFeed;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Benchmark query produksi (read-only) terhadap dataset stress 1 juta baris.
 *
 * Dipakai setelah `stress:seed` untuk menemukan slow query, full scan, dan N+1
 * sebelum endpoint yang memakainya diuji dengan k6.
 */
final class QueryBenchmarkCommand extends Command
{
    private const WARN_MILLISECONDS = 100;

    private const BIG_TABLE_ROWS = 100_000;

    private const MEASURE_RUNS = 3;

    private const MANIFEST_PATH = 'stress/manifest.json';

    /** Peta identifier sampel: alias manifest (case-insensitive) yang menunjuk ke kolom id. */
    private const SAMPLE_SOURCES = [
        'event_id' => ['event_id', 'event', 'events', 'events_id'],
        'form_id' => ['form_id', 'form', 'forms', 'forms_id'],
        'user_id' => ['user_id', 'user', 'users', 'users_id'],
        'period_id' => ['period_id', 'period', 'recruitment_period_id', 'recruitment_periods'],
        'division_id' => ['division_id', 'division', 'recruitment_division_id', 'recruitment_divisions'],
        'broadcast_id' => ['broadcast_id', 'broadcast', 'email_broadcast_id', 'email_broadcasts'],
    ];

    /** Tabel fallback untuk tiap identifier sampel. */
    private const SAMPLE_TABLES = [
        'event_id' => 'events',
        'form_id' => 'forms',
        'user_id' => 'users',
        'period_id' => 'recruitment_periods',
        'division_id' => 'recruitment_divisions',
        'broadcast_id' => 'email_broadcasts',
    ];

    protected $signature = 'stress:queries {--limit=50} {--json} {--only=}';

    protected $description = 'Benchmark query produksi (read-only) pada dataset stress dan laporkan slow query/full scan';

    /** @var array<string, string|null> */
    private array $samples = [];

    private ?string $explainMode = null;

    public function handle(): int
    {
        $this->samples = $this->resolveSampleIdentifiers();

        $selection = $this->selectBenchmarks($this->benchmarkCatalog());
        $results = [];
        $skipped = $selection['skipped'];

        foreach ($selection['run'] as $index => $benchmark) {
            try {
                $results[] = $this->measure($benchmark);
            } catch (Throwable $exception) {
                $skipped[] = [
                    'name' => $benchmark['name'],
                    'reason' => 'eksekusi gagal: '.$exception->getMessage(),
                ];
            }

            $this->renderProgress([
                'current' => $index + 1,
                'total' => count($selection['run']),
                'name' => $benchmark['name'],
            ]);
        }

        $payload = [
            'driver' => DB::connection()->getDriverName(),
            'database' => DB::connection()->getDatabaseName(),
            'explain_mode' => $this->explainMode ?? 'unknown',
            'threshold_ms' => self::WARN_MILLISECONDS,
            'runs_per_query' => self::MEASURE_RUNS,
            'generated_at' => now()->toIso8601String(),
            'queries' => $results,
            'n_plus_one' => $this->probeQueryCounts(),
            'skipped' => $skipped,
            'has_warnings' => $this->hasWarnings($results),
        ];

        if ($this->option('json')) {
            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! is_string($encoded)) {
                $this->error('Gagal mengubah hasil benchmark menjadi JSON.');

                return self::FAILURE;
            }

            $this->line($encoded);

            return self::SUCCESS;
        }

        $this->renderTextReport($payload);

        return self::SUCCESS;
    }

    /**
     * Daftar query yang meniru service/controller aktif, dikelompokkan per domain.
     *
     * @return list<array{name:string,sql:string,bindings:list<string|int|null>,skip?:string|null}>
     */
    private function benchmarkCatalog(): array
    {
        return array_merge(
            $this->eventBenchmarks(),
            $this->formBenchmarks(),
            $this->scanBenchmarks(),
            $this->reportingBenchmarks(),
            $this->recruitmentBenchmarks(),
            $this->broadcastBenchmarks(),
        );
    }

    /**
     * @return list<array{name:string,sql:string,bindings:list<string|null>}>
     */
    private function eventBenchmarks(): array
    {
        return [
            [
                'name' => 'events_list_published',
                'sql' => <<<'SQL'
                    SELECT id, title, slug, category, start_date, status, registered_count, quota
                    FROM events
                    WHERE status = 'published' AND deleted_at IS NULL
                    ORDER BY start_date DESC
                    SQL,
                'bindings' => [],
            ],
            [
                'name' => 'event_registrant_roster',
                'sql' => <<<'SQL'
                    SELECT fa.id, fa.form_id, fa.review_status, fa.created_at, u.name, u.email
                    FROM form_answers fa
                    INNER JOIN forms f ON f.id = fa.form_id
                    LEFT JOIN users u ON u.id = fa.user_id
                    WHERE f.event_id = ? AND f.deleted_at IS NULL
                      AND (fa.registration_role IS NULL OR fa.registration_role <> 'member' OR (fa.registration_role = 'member' AND fa.member_confirmation_status = 'accepted'))
                    ORDER BY fa.created_at DESC
                    LIMIT 200
                    SQL,
                'bindings' => [$this->samples['event_id']],
            ],
            [
                'name' => 'event_registrant_roster_count',
                'sql' => <<<'SQL'
                    SELECT COUNT(*) AS total
                    FROM form_answers fa
                    INNER JOIN forms f ON f.id = fa.form_id
                    WHERE f.event_id = ? AND f.deleted_at IS NULL
                      AND (fa.registration_role IS NULL OR fa.registration_role <> 'member' OR (fa.registration_role = 'member' AND fa.member_confirmation_status = 'accepted'))
                    SQL,
                'bindings' => [$this->samples['event_id']],
            ],
        ];
    }

    /**
     * @return list<array{name:string,sql:string,bindings:list<string|null>}>
     */
    private function formBenchmarks(): array
    {
        return [
            [
                'name' => 'form_submissions_page',
                'sql' => <<<'SQL'
                    SELECT fa.id, fa.form_id, fa.review_status, fa.created_at, fa.reviewed_at,
                           u.name, u.email, r.name AS reviewer_name
                    FROM form_answers fa
                    LEFT JOIN users u ON u.id = fa.user_id
                    LEFT JOIN users r ON r.id = fa.reviewed_by
                    WHERE fa.form_id = ?
                      AND (fa.registration_role IS NULL OR fa.registration_role <> 'member' OR (fa.registration_role = 'member' AND fa.member_confirmation_status = 'accepted'))
                    ORDER BY fa.created_at DESC
                    LIMIT 200
                    SQL,
                'bindings' => [$this->samples['form_id']],
            ],
            [
                'name' => 'form_submissions_count',
                'sql' => <<<'SQL'
                    SELECT COUNT(*) AS total
                    FROM form_answers fa
                    WHERE fa.form_id = ?
                      AND (fa.registration_role IS NULL OR fa.registration_role <> 'member' OR (fa.registration_role = 'member' AND fa.member_confirmation_status = 'accepted'))
                    SQL,
                'bindings' => [$this->samples['form_id']],
            ],
            [
                'name' => 'form_access_duplicate_check',
                'sql' => <<<'SQL'
                    SELECT fa.id
                    FROM form_answers fa
                    WHERE fa.form_id = ? AND fa.user_id = ?
                      AND (fa.review_status IS NULL OR fa.review_status <> 'rejected')
                      AND (fa.registration_role IS NULL OR fa.registration_role <> 'member' OR fa.member_confirmation_status IS NULL OR fa.member_confirmation_status NOT IN ('rejected', 'expired'))
                    LIMIT 1
                    SQL,
                'bindings' => [$this->samples['form_id'], $this->samples['user_id']],
            ],
            [
                'name' => 'form_access_other_registration_form',
                'sql' => <<<'SQL'
                    SELECT fa.id
                    FROM form_answers fa
                    WHERE fa.user_id = ? AND fa.form_id <> ?
                      AND EXISTS (
                          SELECT 1 FROM forms f
                          WHERE f.id = fa.form_id AND f.event_id = ?
                            AND (f.metadata IS NULL OR JSON_EXTRACT(f.metadata, '$.purpose') IS NULL OR JSON_EXTRACT(f.metadata, '$.purpose') = 'registration')
                      )
                      AND (fa.review_status IS NULL OR fa.review_status <> 'rejected')
                      AND (fa.registration_role IS NULL OR fa.registration_role <> 'member' OR fa.member_confirmation_status IS NULL OR fa.member_confirmation_status NOT IN ('rejected', 'expired'))
                    LIMIT 1
                    SQL,
                'bindings' => [$this->samples['user_id'], $this->samples['form_id'], $this->samples['event_id']],
            ],
        ];
    }

    /**
     * @return list<array{name:string,sql:string,bindings:list<string>}>
     */
    private function scanBenchmarks(): array
    {
        $cursor = now()->subDay()->format('Y-m-d H:i:s');

        return [
            [
                'name' => 'scan_feed_event_since',
                'sql' => <<<'SQL'
                    SELECT ea.id, ea.form_answer_id, ea.scanned_at,
                           fa.form_id, f.event_id, e.title, u.name, u.email, fa.invited_email
                    FROM event_attendances ea
                    LEFT JOIN form_answers fa ON fa.id = ea.form_answer_id
                    LEFT JOIN forms f ON f.id = fa.form_id
                    LEFT JOIN events e ON e.id = f.event_id
                    LEFT JOIN users u ON u.id = fa.user_id
                    WHERE ea.scanned_at > ?
                    ORDER BY ea.scanned_at ASC
                    LIMIT 100
                    SQL,
                'bindings' => [$cursor],
            ],
            [
                'name' => 'scan_feed_recruitment_since',
                'sql' => <<<'SQL'
                    SELECT ra.id, ra.recruitment_application_id, ra.recruitment_interview_session_id, ra.checked_in_at
                    FROM recruitment_attendances ra
                    WHERE ra.checked_in_at > ?
                    ORDER BY ra.checked_in_at ASC
                    LIMIT 100
                    SQL,
                'bindings' => [$cursor],
            ],
        ];
    }

    /**
     * @return list<array{name:string,sql:string,bindings:list<string>}>
     */
    private function reportingBenchmarks(): array
    {
        return [
            [
                'name' => 'reporting_event_submission_count',
                'sql' => <<<'SQL'
                    SELECT COUNT(*) AS total
                    FROM form_answers fa
                    INNER JOIN forms f ON f.id = fa.form_id
                    WHERE f.event_id = ? AND f.deleted_at IS NULL
                    SQL,
                'bindings' => [$this->samples['event_id']],
            ],
            [
                'name' => 'reporting_registration_trend',
                'sql' => 'SELECT '.$this->monthExpression().' AS ym, COUNT(*) AS c
                    FROM form_answers fa
                    INNER JOIN forms f ON f.id = fa.form_id
                    INNER JOIN events e ON e.id = f.event_id
                    WHERE e.deleted_at IS NULL AND f.deleted_at IS NULL AND fa.created_at >= ?
                    GROUP BY ym ORDER BY ym',
                'bindings' => [now()->startOfMonth()->subMonths(5)->format('Y-m-d H:i:s')],
            ],
            [
                'name' => 'reporting_global_submission_count',
                'sql' => <<<'SQL'
                    SELECT COUNT(*) AS total
                    FROM form_answers fa
                    INNER JOIN forms f ON f.id = fa.form_id
                    INNER JOIN events e ON e.id = f.event_id
                    WHERE e.deleted_at IS NULL AND f.deleted_at IS NULL
                    SQL,
                'bindings' => [],
            ],
            [
                'name' => 'reporting_attendance_log_page',
                'sql' => <<<'SQL'
                    SELECT ea.id, ea.scanned_at, ea.form_answer_id, u.name, u.email
                    FROM event_attendances ea
                    LEFT JOIN form_answers fa ON fa.id = ea.form_answer_id
                    LEFT JOIN users u ON u.id = fa.user_id
                    WHERE ea.event_id = ?
                    ORDER BY ea.scanned_at DESC
                    LIMIT 20
                    SQL,
                'bindings' => [$this->samples['event_id']],
            ],
        ];
    }

    /**
     * @return list<array{name:string,sql:string,bindings:list<string>}>
     */
    private function recruitmentBenchmarks(): array
    {
        return [
            [
                'name' => 'recruitment_applicants_page',
                'sql' => <<<'SQL'
                    SELECT ra.id, ra.registration_number, ra.full_name, ra.nim, ra.semester,
                           ra.stage, ra.result, p.name AS period_name,
                           d1.name AS primary_division, d2.name AS secondary_division
                    FROM recruitment_applications ra
                    LEFT JOIN recruitment_periods p ON p.id = ra.recruitment_period_id
                    LEFT JOIN recruitment_divisions d1 ON d1.id = ra.primary_division_id
                    LEFT JOIN recruitment_divisions d2 ON d2.id = ra.secondary_division_id
                    WHERE ra.recruitment_period_id = ? AND ra.stage = ?
                      AND (ra.primary_division_id = ? OR ra.secondary_division_id = ?)
                    ORDER BY ra.submitted_at DESC, ra.id DESC
                    LIMIT 20
                    SQL,
                'bindings' => [$this->samples['period_id'], 'interview', $this->samples['division_id'], $this->samples['division_id']],
            ],
            [
                'name' => 'recruitment_report_funnel',
                'sql' => <<<'SQL'
                    SELECT stage, COUNT(*) AS total
                    FROM recruitment_applications
                    WHERE recruitment_period_id = ?
                    GROUP BY stage
                    SQL,
                'bindings' => [$this->samples['period_id']],
            ],
            [
                'name' => 'recruitment_report_by_division',
                'sql' => <<<'SQL'
                    SELECT d.name AS division_name, COUNT(*) AS total
                    FROM recruitment_applications ra
                    INNER JOIN recruitment_divisions d ON d.id = ra.primary_division_id
                    WHERE ra.recruitment_period_id = ?
                    GROUP BY d.name, d.sort_order
                    ORDER BY d.sort_order
                    SQL,
                'bindings' => [$this->samples['period_id']],
            ],
            [
                'name' => 'recruitment_interview_today',
                'sql' => <<<'SQL'
                    SELECT ri.id, ri.status, ri.scheduled_at, s.session_date, s.starts_at, s.location, s.room
                    FROM recruitment_interviews ri
                    INNER JOIN recruitment_interview_sessions s ON s.id = ri.recruitment_interview_session_id
                    WHERE ri.interviewer_id = ? AND ri.interview_kind = 'primary' AND s.is_active = 1
                      AND DATE(s.session_date) = ?
                    ORDER BY ri.booked_at DESC
                    LIMIT 20
                    SQL,
                'bindings' => [$this->samples['user_id'], now()->toDateString()],
            ],
            [
                'name' => 'recruitment_waiting_pool',
                'sql' => <<<'SQL'
                    SELECT ri.id
                    FROM recruitment_interviews ri
                    INNER JOIN recruitment_interview_sessions s ON s.id = ri.recruitment_interview_session_id
                    WHERE ri.interview_kind = 'primary' AND ri.status = 'waiting' AND ri.interviewer_id IS NULL
                      AND s.is_active = 1 AND s.recruitment_division_id = ?
                      AND EXISTS (
                          SELECT 1 FROM recruitment_applications ra
                          INNER JOIN recruitment_attendances att ON att.recruitment_application_id = ra.id
                          WHERE ra.id = ri.recruitment_application_id
                            AND ra.stage = 'interview' AND ra.cancelled_at IS NULL
                      )
                    ORDER BY ri.scheduled_at ASC
                    LIMIT 20
                    SQL,
                'bindings' => [$this->samples['division_id']],
            ],
        ];
    }

    /**
     * Query broadcast hanya diukur bila FEATURE_BROADCAST aktif (default: dorman).
     *
     * @return list<array{name:string,sql:string,bindings:list<string|null>,skip:string|null}>
     */
    private function broadcastBenchmarks(): array
    {
        $skip = config('features.broadcast', false) ? null : 'FEATURE_BROADCAST=false';

        return [
            [
                'name' => 'broadcast_event_participants',
                'sql' => <<<'SQL'
                    SELECT fa.id
                    FROM form_answers fa
                    WHERE (fa.registration_role IS NULL OR fa.registration_role <> 'member' OR (fa.registration_role = 'member' AND fa.member_confirmation_status = 'accepted'))
                      AND EXISTS (SELECT 1 FROM forms f WHERE f.id = fa.form_id AND f.event_id = ?)
                    LIMIT 200
                    SQL,
                'bindings' => [$this->samples['event_id']],
                'skip' => $skip,
            ],
            [
                'name' => 'broadcast_recipients_page',
                'sql' => <<<'SQL'
                    SELECT id, name, email, status, sent_at
                    FROM email_broadcast_recipients
                    WHERE broadcast_id = ? AND status = ?
                    ORDER BY id
                    LIMIT 50
                    SQL,
                'bindings' => [$this->samples['broadcast_id'], 'pending'],
                'skip' => $skip,
            ],
        ];
    }

    /**
     * Terapkan filter --only, batas --limit, dan penanda skip.
     *
     * @param  list<array{name:string,sql:string,bindings:list<string|int|null>,skip?:string|null}>  $catalog
     * @return array{run:list<array{name:string,sql:string,bindings:list<string|int|null>}>,skipped:list<array{name:string,reason:string}>}
     */
    private function selectBenchmarks(array $catalog): array
    {
        $only = $this->onlyFilters();
        $limit = max(1, (int) $this->option('limit'));
        $run = [];
        $skipped = [];

        foreach ($catalog as $entry) {
            if ($only !== [] && ! $this->matchesOnly($entry['name'], $only)) {
                continue;
            }

            $reason = $entry['skip'] ?? null;

            if ($reason === null && in_array(null, $entry['bindings'], true)) {
                $reason = 'id sampel tidak tersedia (manifest kosong dan tabel kosong)';
            }

            if ($reason === null && count($run) >= $limit) {
                $reason = 'melebihi --limit='.$limit;
            }

            if ($reason !== null) {
                $skipped[] = ['name' => $entry['name'], 'reason' => $reason];

                continue;
            }

            $this->assertReadOnly($entry['sql']);
            $run[] = ['name' => $entry['name'], 'sql' => $entry['sql'], 'bindings' => $entry['bindings']];
        }

        return ['run' => $run, 'skipped' => $skipped];
    }

    /**
     * Ukur median eksekusi, rencana akses, dan warning untuk satu query.
     *
     * @param  array{name:string,sql:string,bindings:list<string|int|null>}  $benchmark
     * @return array{name:string,sql:string,median_ms:float,rows_returned:int,rows_examined:int|null,access:string|null,warnings:list<string>}
     */
    private function measure(array $benchmark): array
    {
        $timing = $this->measureExecution($benchmark);
        $plan = $this->inspectPlan($benchmark);

        return [
            'name' => $benchmark['name'],
            'sql' => $this->compactSql($benchmark['sql']),
            'median_ms' => $timing['median_ms'],
            'rows_returned' => $timing['rows_returned'],
            'rows_examined' => $plan['rows_examined'],
            'access' => $plan['access'],
            'warnings' => $this->buildWarnings([
                'median_ms' => $timing['median_ms'],
                'access' => $plan['access'],
                'rows_examined' => $plan['rows_examined'],
                'full_scan_rows' => $plan['full_scan_rows'],
                'note' => $plan['note'],
            ]),
        ];
    }

    /**
     * Jalankan query beberapa kali dan ambil median durasi dalam milidetik.
     *
     * @param  array{name:string,sql:string,bindings:list<string|int|null>}  $benchmark
     * @return array{median_ms:float,rows_returned:int}
     */
    private function measureExecution(array $benchmark): array
    {
        $durations = [];
        $rowsReturned = 0;

        for ($run = 0; $run < self::MEASURE_RUNS; $run++) {
            $startedAt = hrtime(true);
            $rows = DB::select($benchmark['sql'], $benchmark['bindings']);
            $durations[] = (hrtime(true) - $startedAt) / 1_000_000;
            $rowsReturned = count($rows);
        }

        sort($durations);
        $middle = intdiv(count($durations), 2);
        $median = count($durations) % 2 === 0
            ? ($durations[$middle - 1] + $durations[$middle]) / 2
            : $durations[$middle];

        return ['median_ms' => round($median, 3), 'rows_returned' => $rowsReturned];
    }

    /**
     * Ambil EXPLAIN ANALYZE (bila didukung) atau EXPLAIN biasa untuk satu query.
     *
     * @param  array{name:string,sql:string,bindings:list<string|int|null>}  $benchmark
     * @return array{rows_examined:int|null,access:string|null,full_scan_rows:int|null,note:string|null}
     */
    private function inspectPlan(array $benchmark): array
    {
        $mode = $this->explainMode ??= $this->detectExplainMode();

        try {
            $rows = DB::select(($mode === 'analyze' ? 'EXPLAIN ANALYZE ' : 'EXPLAIN ').$benchmark['sql'], $benchmark['bindings']);
        } catch (Throwable $exception) {
            return [
                'rows_examined' => null,
                'access' => null,
                'full_scan_rows' => null,
                'note' => 'explain gagal: '.$exception->getMessage(),
            ];
        }

        $analysis = $this->isTreePlan($rows)
            ? $this->analyseTreePlan($rows)
            : $this->analyseTabularPlan($rows);

        $analysis['note'] = null;

        return $analysis;
    }

    /**
     * Deteksi dukungan EXPLAIN ANALYZE sekali per eksekusi command.
     */
    private function detectExplainMode(): string
    {
        try {
            DB::select('EXPLAIN ANALYZE SELECT 1');

            return 'analyze';
        } catch (Throwable) {
            return 'explain';
        }
    }

    /**
     * MySQL 8 mengembalikan satu kolom EXPLAIN berisi pohon rencana.
     *
     * @param  list<stdClass>  $rows
     */
    private function isTreePlan(array $rows): bool
    {
        return isset($rows[0]) && property_exists($rows[0], 'EXPLAIN');
    }

    /**
     * Ringkas pohon EXPLAIN ANALYZE MySQL: total baris aktual, akses terburuk, dan baris full scan.
     *
     * @param  list<stdClass>  $rows
     * @return array{rows_examined:int|null,access:string|null,full_scan_rows:int|null}
     */
    private function analyseTreePlan(array $rows): array
    {
        $text = '';

        foreach ($rows as $row) {
            if (property_exists($row, 'EXPLAIN') && is_string($row->EXPLAIN)) {
                $text .= $row->EXPLAIN."\n";
            }
        }

        preg_match_all('/rows=([0-9.]+)\s+loops=([0-9]+)/', $text, $matches);
        $rowsExamined = 0;

        foreach ($matches[1] as $index => $actualRows) {
            $rowsExamined += (int) round((float) $actualRows * (int) $matches[2][$index]);
        }

        preg_match_all('/(?:table scan|full scan)[^\n]*?rows=([0-9.]+)\s+loops=([0-9]+)/i', $text, $scans);
        $fullScanRows = 0;

        foreach ($scans[1] as $index => $actualRows) {
            $fullScanRows += (int) round((float) $actualRows * (int) $scans[2][$index]);
        }

        return [
            'rows_examined' => $rowsExamined > 0 ? $rowsExamined : null,
            'access' => $fullScanRows > 0 ? 'ALL' : $this->treeAccess($text),
            'full_scan_rows' => $fullScanRows > 0 ? $fullScanRows : null,
        ];
    }

    /**
     * Petakan teks pohon rencana (tanpa full scan) ke jenis akses range/index.
     */
    private function treeAccess(string $planText): ?string
    {
        $text = strtolower($planText);

        if (str_contains($text, 'range scan')) {
            return 'range';
        }

        if (str_contains($text, 'index lookup') || str_contains($text, 'index scan') || str_contains($text, 'covering index')) {
            return 'index';
        }

        return null;
    }

    /**
     * Ringkas EXPLAIN tabular (MySQL fallback / MariaDB): jumlah baris dan tipe akses.
     *
     * @param  list<stdClass>  $rows
     * @return array{rows_examined:int|null,access:string|null,full_scan_rows:int|null}
     */
    private function analyseTabularPlan(array $rows): array
    {
        $rowsExamined = 0;
        $fullScanRows = 0;
        $access = null;

        foreach ($rows as $row) {
            $type = property_exists($row, 'type') && is_string($row->type) ? $row->type : null;
            $normalised = $type !== null ? $this->normaliseAccess($type) : null;

            if ($normalised !== null && $this->isWorseAccess($normalised, $access)) {
                $access = $normalised;
            }

            $estimate = $row->r_rows ?? null;
            $estimate = $estimate ?? ($row->rows ?? null);

            if (! is_numeric($estimate)) {
                continue;
            }

            $rowsExamined += (int) $estimate;

            if ($normalised === 'ALL') {
                $fullScanRows += (int) $estimate;
            }
        }

        return [
            'rows_examined' => $rowsExamined > 0 ? $rowsExamined : null,
            'access' => $access,
            'full_scan_rows' => $fullScanRows > 0 ? $fullScanRows : null,
        ];
    }

    /**
     * Normalisasi tipe akses EXPLAIN MySQL/MariaDB ke ALL/range/index.
     */
    private function normaliseAccess(string $type): string
    {
        return match (strtolower(trim($type))) {
            'all' => 'ALL',
            'range', 'index_merge' => 'range',
            'index', 'fulltext' => 'index',
            'ref', 'eq_ref', 'const', 'system' => 'index',
            default => 'unknown',
        };
    }

    /**
     * Bandingkan dua jenis akses; ALL selalu dianggap paling buruk.
     */
    private function isWorseAccess(string $candidate, ?string $current): bool
    {
        $rank = ['ALL' => 4, 'unknown' => 3, 'range' => 2, 'index' => 1];
        $candidateRank = $rank[$candidate] ?? 3;
        $currentRank = $current !== null ? ($rank[$current] ?? 0) : 0;

        return $candidateRank > $currentRank;
    }

    /**
     * Susun warning dari hasil ukur: lambat (>100 ms) dan full scan tabel besar.
     *
     * @param  array{median_ms:float,access:string|null,rows_examined:int|null,full_scan_rows:int|null,note:string|null}  $measurement
     * @return list<string>
     */
    private function buildWarnings(array $measurement): array
    {
        $warnings = [];

        if ($measurement['median_ms'] > self::WARN_MILLISECONDS) {
            $warnings[] = sprintf('median %.1f ms > %d ms', $measurement['median_ms'], self::WARN_MILLISECONDS);
        }

        $scannedRows = $measurement['full_scan_rows'] ?? $measurement['rows_examined'];

        if ($measurement['access'] === 'ALL'
            && $scannedRows !== null
            && $scannedRows >= self::BIG_TABLE_ROWS) {
            $warnings[] = 'full table scan '.number_format((float) $scannedRows, 0, ',', '.').' baris';
        }

        if ($measurement['note'] !== null) {
            $warnings[] = $measurement['note'];
        }

        return $warnings;
    }

    /**
     * Hitung jumlah query per pemanggilan service (indikasi N+1).
     *
     * @return list<array{target:string,queries:int,note:string|null}>
     */
    private function probeQueryCounts(): array
    {
        $probes = [];
        $event = $this->samples['event_id'] !== null ? Event::query()->find($this->samples['event_id']) : null;

        if ($event instanceof Event) {
            $probes[] = [
                'target' => 'EventReportingQuery::eventReportingSummary',
                'run' => fn (): array => app(EventReportingQuery::class)->eventReportingSummary($event),
            ];
        }

        $probes[] = [
            'target' => 'ScanStreamFeed::since(50)',
            'run' => fn (): array => app(ScanStreamFeed::class)->since(null, 50),
        ];

        $results = [];

        foreach ($probes as $probe) {
            $results[] = $this->runProbe($probe['target'], $probe['run']);
        }

        return $results;
    }

    /**
     * Jalankan satu probe dengan query log aktif, kembalikan jumlah query aktualnya.
     *
     * @param  Closure():array<mixed>  $run
     * @return array{target:string,queries:int,note:string|null}
     */
    private function runProbe(string $target, Closure $run): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $run();
        } catch (Throwable $exception) {
            return ['target' => $target, 'queries' => 0, 'note' => 'probe gagal: '.$exception->getMessage()];
        } finally {
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return ['target' => $target, 'queries' => $queries, 'note' => null];
    }

    /**
     * Baca identifier sampel dari manifest stress; fallback ke baris pertama tabel.
     *
     * @return array<string, string|null>
     */
    private function resolveSampleIdentifiers(): array
    {
        $manifest = $this->loadManifest();
        $samples = [];

        foreach (self::SAMPLE_SOURCES as $key => $aliases) {
            $samples[$key] = $this->findIdentifierInManifest($manifest, $aliases)
                ?? $this->firstIdentifier(self::SAMPLE_TABLES[$key]);
        }

        return $samples;
    }

    /**
     * @return array<mixed>
     */
    private function loadManifest(): array
    {
        $path = storage_path('app/'.self::MANIFEST_PATH);

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Cari nilai identifier pertama pada manifest untuk salah satu alias kunci.
     *
     * @param  array<mixed>  $tree
     * @param  list<string>  $aliases
     */
    private function findIdentifierInManifest(array $tree, array $aliases): ?string
    {
        foreach ($tree as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $aliases, true)) {
                if (is_array($value)) {
                    $scalar = $this->scalarFromTree($value);

                    if ($scalar !== null) {
                        return $scalar;
                    }
                } elseif (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }

            if (is_array($value)) {
                $nested = $this->findIdentifierInManifest($value, $aliases);

                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * Ambil string pertama yang tidak kosong dari struktur manifest bersarang.
     *
     * @param  array<mixed>  $tree
     */
    private function scalarFromTree(array $tree): ?string
    {
        foreach ($tree as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                $nested = $this->scalarFromTree($value);

                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * Ambil id baris pertama sebuah tabel sebagai sampel; null bila tabel tidak ada.
     */
    private function firstIdentifier(string $table): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $value = DB::table($table)->value('id');

        return is_string($value) ? $value : null;
    }

    /**
     * Daftar filter --only (dipisah koma, case-insensitive).
     *
     * @return list<string>
     */
    private function onlyFilters(): array
    {
        $raw = $this->option('only');

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $raw),
        ), static fn (string $item): bool => $item !== ''));
    }

    /**
     * Cocokkan nama query dengan salah satu filter --only.
     *
     * @param  list<string>  $filters
     */
    private function matchesOnly(string $name, array $filters): bool
    {
        foreach ($filters as $filter) {
            if (str_contains(strtolower($name), $filter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Jaga command tetap read-only: hanya pernyataan SELECT yang boleh diukur.
     */
    private function assertReadOnly(string $sql): void
    {
        if (preg_match('/^\s*SELECT\b/i', $sql) !== 1) {
            throw new RuntimeException('Benchmark menolak query non-SELECT.');
        }
    }

    /**
     * Rapikan SQL multi-baris menjadi satu baris; potong bila melebihi batas tampilan.
     */
    private function compactSql(string $sql, int $maxLength = 0): string
    {
        $compact = (string) preg_replace('/\s+/', ' ', trim($sql));

        if ($maxLength <= 0 || strlen($compact) <= $maxLength) {
            return $compact;
        }

        return substr($compact, 0, $maxLength - 1).'…';
    }

    /**
     * Ekspresi bulan yang sesuai driver untuk query tren registrasi.
     */
    private function monthExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', fa.created_at)"
            : "DATE_FORMAT(fa.created_at, '%Y-%m')";
    }

    /**
     * @param  list<array{name:string,sql:string,median_ms:float,rows_returned:int,rows_examined:int|null,access:string|null,warnings:list<string>}>  $results
     */
    private function hasWarnings(array $results): bool
    {
        foreach ($results as $result) {
            if ($result['warnings'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tampilkan progres singkat ke stderr agar tidak mengganggu output tabel.
     *
     * @param  array{current:int,total:int,name:string}  $progress
     */
    private function renderProgress(array $progress): void
    {
        if ($this->option('json') || ! $this->output->isVerbose()) {
            return;
        }

        $this->line(sprintf('[%d/%d] %s', $progress['current'], $progress['total'], $progress['name']));
    }

    /**
     * Render laporan default dalam bentuk tabel teks yang mudah dibaca.
     *
     * @param  array<string, mixed>  $payload
     */
    private function renderTextReport(array $payload): void
    {
        /** @var list<array<string, mixed>> $queries */
        $queries = $payload['queries'];

        $this->info(sprintf(
            'Driver: %s · explain: %s · %d query · ambang warning %d ms',
            (string) $payload['driver'],
            (string) $payload['explain_mode'],
            count($queries),
            self::WARN_MILLISECONDS,
        ));

        if ($queries === []) {
            $this->warn('Tidak ada query yang diukur. Jalankan stress:seed lebih dulu atau longgarkan --only/--limit.');

            return;
        }

        $rows = [];

        foreach ($queries as $query) {
            $warnings = $query['warnings'];

            $rows[] = [
                $query['name'],
                $this->compactSql((string) $query['sql'], 90),
                number_format((float) $query['median_ms'], 2),
                $query['rows_examined'] !== null ? number_format((float) $query['rows_examined'], 0, ',', '.') : '-',
                $query['access'] ?? '-',
                $warnings === [] ? 'ok' : implode('; ', $warnings),
            ];
        }

        $this->table(['Query', 'SQL ringkas', 'Median ms', 'Rows examined', 'Access', 'Warning'], $rows);

        /** @var list<array{target:string,queries:int,note:string|null}> $probes */
        $probes = $payload['n_plus_one'];

        if ($probes !== []) {
            $this->line('');
            $this->info('Jumlah query per pemanggilan service (indikasi N+1):');
            $this->table(['Service', 'Queries', 'Catatan'], array_map(
                static fn (array $probe): array => [$probe['target'], (string) $probe['queries'], $probe['note'] ?? '-'],
                $probes,
            ));
        }

        /** @var list<array{name:string,reason:string}> $skipped */
        $skipped = $payload['skipped'];

        if ($skipped !== []) {
            $this->line('');
            $this->warn('Dilewati:');

            foreach ($skipped as $entry) {
                $this->line(sprintf('  - %s: %s', $entry['name'], $entry['reason']));
            }
        }
    }
}
