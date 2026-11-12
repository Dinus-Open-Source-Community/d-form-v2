<?php

namespace Tests\Feature\Dashboard;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use App\Services\Reporting\EventReportingQuery;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Regresi untuk laporan log kehadiran event (BE-P2-006).
 *
 * Filter `event_id` + urutan `scanned_at DESC` harus dilayani index komposit
 * `(event_id, scanned_at)` supaya paginator tidak melakukan filesort. Test
 * EXPLAIN hanya berjalan di MySQL; test lain memakai SQLite `:memory:`.
 */
class EventAttendanceLogPaginationTest extends TestCase
{
    use RefreshDatabase;

    private const INDEX_NAME = 'event_attendances_event_id_scanned_at_id_index';

    /**
     * Index 2 kolom BE-P2-006 tetap ada (drop-nya butuh approval terpisah);
     * BE-P2-025 hanya menambah index 3 kolom sebagai tie-breaker `id`.
     */
    private const LEGACY_INDEX_NAME = 'event_attendances_event_id_scanned_at_index';

    private const INDEX_PREFIX = 'event_attendances_event_id_scanned_at';

    /**
     * Event dan form pendaftaran minimal untuk membuat baris kehadiran.
     *
     * @return array{0: Event, 1: Form}
     */
    private function eventWithForm(): array
    {
        $event = Event::factory()->create();

        $form = Form::factory()->create([
            'event_id' => $event->id,
        ]);

        return [$event, $form];
    }

    /**
     * Baris kehadiran berurutan: `scanned_at` naik `minutesBetweenRows` per baris.
     * Nilai 0 membuat seluruh baris berbagi `scanned_at` identik (kasus tie).
     *
     * @return list<EventAttendance>
     */
    private function createAttendanceRows(
        Event $event,
        Form $form,
        int $count,
        CarbonInterface $base,
        ?User $scanner,
        int $minutesBetweenRows = 1,
    ): array {
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $attendee = User::factory()->create();
            $answer = FormAnswer::factory()->create([
                'form_id' => $form->id,
                'user_id' => $attendee->id,
            ]);

            $rows[] = EventAttendance::query()->create([
                'event_id' => $event->id,
                'form_answer_id' => $answer->id,
                'scanned_by_user_id' => $scanner?->id,
                'scanned_at' => $base->copy()->addMinutes($index * $minutesBetweenRows),
            ]);
        }

        return $rows;
    }

    private function reportingQuery(): EventReportingQuery
    {
        return $this->app->make(EventReportingQuery::class);
    }

    /**
     * Paginator tidak menyediakan getter untuk resolver-nya; reflection dipakai
     * agar test dapat mengembalikan nilai asli (termasuk keadaan null).
     */
    private function currentPageResolverValue(): ?Closure
    {
        $resolver = (new ReflectionProperty(Paginator::class, 'currentPageResolver'))->getValue();

        return $resolver instanceof Closure ? $resolver : null;
    }

    private function restoreCurrentPageResolver(?Closure $resolver): void
    {
        if ($resolver !== null) {
            Paginator::currentPageResolver($resolver);

            return;
        }

        (new ReflectionProperty(Paginator::class, 'currentPageResolver'))->setValue(null, null);
    }

    public function test_event_attendances_has_event_id_scanned_at_composite_index(): void
    {
        $indexes = collect(Schema::getIndexes('event_attendances'))->keyBy('name');

        $this->assertArrayHasKey(self::LEGACY_INDEX_NAME, $indexes->all());
        $this->assertSame(['event_id', 'scanned_at'], $indexes[self::LEGACY_INDEX_NAME]['columns']);

        $this->assertArrayHasKey(self::INDEX_NAME, $indexes->all());
        $this->assertSame(['event_id', 'scanned_at', 'id'], $indexes[self::INDEX_NAME]['columns']);
    }

    public function test_log_page_returns_ordered_rows_with_expected_payload_shape(): void
    {
        [$event, $form] = $this->eventWithForm();
        $scanner = User::factory()->create([
            'name' => 'Scanner Satu',
            'email' => 'scanner@example.test',
        ]);
        $base = now()->startOfSecond()->subHours(2);

        // 19 baris reguler; baris terakhir (paling baru) adalah peserta guest
        // tanpa user dan tanpa scanner sehingga kedua cabang null ikut teruji.
        $rows = $this->createAttendanceRows($event, $form, 19, $base, $scanner);

        $guestAnswer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => null,
        ]);
        $guestRow = EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $guestAnswer->id,
            'scanned_by_user_id' => null,
            'scanned_at' => $base->copy()->addMinutes(19),
        ]);

        $page = $this->reportingQuery()->paginateAttendanceLog($event, 20);
        $items = $page->items();

        $this->assertCount(20, $items);
        $this->assertSame(20, $page->perPage());
        $this->assertSame(20, $page->total());

        $this->assertSame(['id', 'scanned_at', 'form_answer_id', 'attendee', 'scanned_by'], array_keys($items[0]));

        $newest = $items[0];
        $this->assertSame($guestRow->id, $newest['id']);
        $this->assertSame($guestAnswer->id, $newest['form_answer_id']);
        $this->assertNull($newest['attendee']);
        $this->assertNull($newest['scanned_by']);
        $this->assertSame($base->copy()->addMinutes(19)->toIso8601String(), $newest['scanned_at']);

        $second = $items[1];
        $this->assertSame($rows[18]->id, $second['id']);
        $this->assertSame('Scanner Satu', $second['scanned_by']['name']);
        $this->assertSame('scanner@example.test', $second['scanned_by']['email']);
        $this->assertSame($base->copy()->addMinutes(18)->toIso8601String(), $second['scanned_at']);

        $oldest = $items[19];
        $this->assertSame($rows[0]->id, $oldest['id']);
        $this->assertSame($base->toIso8601String(), $oldest['scanned_at']);
        $this->assertIsArray($oldest['attendee']);
        $this->assertArrayHasKey('name', $oldest['attendee']);
        $this->assertArrayHasKey('email', $oldest['attendee']);

        // Urutan menurun murni pada scanned_at yang seluruhnya berbeda.
        $expectedScannedAt = [];
        for ($offset = 19; $offset >= 0; $offset--) {
            $expectedScannedAt[] = $base->copy()->addMinutes($offset)->toIso8601String();
        }

        $this->assertSame($expectedScannedAt, array_column($items, 'scanned_at'));
    }

    public function test_two_pages_partition_rows_exactly_once(): void
    {
        [$event, $form] = $this->eventWithForm();
        $scanner = User::factory()->create();
        $base = now()->startOfSecond()->subDay();

        $this->createAttendanceRows($event, $form, 45, $base, $scanner);

        $expectedIds = EventAttendance::query()
            ->where('event_id', $event->id)
            ->orderByDesc('scanned_at')
            ->pluck('id')
            ->all();

        $this->assertCount(45, $expectedIds);

        $service = $this->reportingQuery();
        $previousResolver = $this->currentPageResolverValue();

        try {
            Paginator::currentPageResolver(static fn (): int => 1);
            $first = $service->paginateAttendanceLog($event, 20);
            Paginator::currentPageResolver(static fn (): int => 2);
            $second = $service->paginateAttendanceLog($event, 20);
            Paginator::currentPageResolver(static fn (): int => 3);
            $third = $service->paginateAttendanceLog($event, 20);
        } finally {
            $this->restoreCurrentPageResolver($previousResolver);
        }

        $this->assertCount(20, $first->items());
        $this->assertCount(20, $second->items());
        $this->assertCount(5, $third->items());

        $collectedIds = [
            ...array_column($first->items(), 'id'),
            ...array_column($second->items(), 'id'),
            ...array_column($third->items(), 'id'),
        ];

        $this->assertCount(45, $collectedIds);
        $this->assertCount(45, array_unique($collectedIds));
        $this->assertSame($expectedIds, $collectedIds);
    }

    /**
     * BE-P2-025: seluruh baris berbagi `scanned_at` identik (presisi detik). Tanpa
     * tie-breaker `id`, urutan di antara tie bebas dan OFFSET bisa menulis ulang
     * atau melewatkan baris.
     */
    public function test_two_pages_with_identical_scanned_at_partition_rows_exactly_once(): void
    {
        [$event, $form] = $this->eventWithForm();
        $scanner = User::factory()->create();
        $base = now()->startOfSecond()->subDay();

        $this->createAttendanceRows($event, $form, 45, $base, $scanner, 0);

        // Ekspektasi dari urutan DB sendiri: collation `id` di MySQL tidak perlu
        // diasumsikan sama dengan SQLite.
        $expectedIds = EventAttendance::query()
            ->where('event_id', $event->id)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $service = $this->reportingQuery();
        $previousResolver = $this->currentPageResolverValue();
        $pages = [];

        try {
            foreach ([1, 2, 3] as $pageNumber) {
                Paginator::currentPageResolver(static fn (): int => $pageNumber);
                $pages[] = $service->paginateAttendanceLog($event, 20);
            }
        } finally {
            $this->restoreCurrentPageResolver($previousResolver);
        }

        $collectedIds = [];
        $collectedScannedAt = [];

        foreach ($pages as $page) {
            foreach ($page->items() as $item) {
                $collectedIds[] = $item['id'];
                $collectedScannedAt[] = $item['scanned_at'];
            }
        }

        $this->assertCount(45, $collectedIds);
        $this->assertCount(45, array_unique($collectedIds));
        $this->assertSame($expectedIds, $collectedIds);
        $this->assertSame(array_fill(0, 45, $base->toIso8601String()), $collectedScannedAt);
    }

    /**
     * BE-P3-019: halaman di luar `last_page` di-clamp ke halaman terakhir, bukan
     * mengembalikan data kosong dengan label rentang mustahil.
     */
    public function test_page_beyond_last_page_clamps_to_last_page(): void
    {
        [$event, $form] = $this->eventWithForm();
        $this->createAttendanceRows($event, $form, 45, now()->startOfSecond()->subDay(), null);

        $previousResolver = $this->currentPageResolverValue();

        try {
            Paginator::currentPageResolver(static fn (): int => 999999);
            $page = $this->reportingQuery()->paginateAttendanceLog($event, 20);
        } finally {
            $this->restoreCurrentPageResolver($previousResolver);
        }

        $this->assertSame(3, $page->currentPage());
        $this->assertSame(3, $page->lastPage());
        $this->assertSame(45, $page->total());
        $this->assertSame(20, $page->perPage());
        $this->assertCount(5, $page->items());
    }

    public function test_page_beyond_last_page_on_empty_event_resolves_to_first_page(): void
    {
        $event = Event::factory()->create();

        $previousResolver = $this->currentPageResolverValue();

        try {
            Paginator::currentPageResolver(static fn (): int => 999999);
            $page = $this->reportingQuery()->paginateAttendanceLog($event, 20);
        } finally {
            $this->restoreCurrentPageResolver($previousResolver);
        }

        $this->assertSame(1, $page->currentPage());
        $this->assertSame(1, $page->lastPage());
        $this->assertSame(0, $page->total());
        $this->assertSame([], $page->items());
    }

    /**
     * M-1: halaman liar di-clamp SEBELUM query daftar, sehingga OFFSET tetap
     * `(lastPage-1)*perPage` dan jumlah query sama dengan halaman in-range
     * (1 COUNT + 1 SELECT), bukan dua kali paginate.
     */
    public function test_out_of_range_page_is_clamped_before_querying_with_bounded_offset(): void
    {
        [$event, $form] = $this->eventWithForm();
        $this->createAttendanceRows($event, $form, 45, now()->startOfSecond()->subDay(), null);

        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = str_replace(['"', '`'], '', $query->sql);
        });

        $previousResolver = $this->currentPageResolverValue();

        try {
            Paginator::currentPageResolver(static fn (): int => 999999999);
            $page = $this->reportingQuery()->paginateAttendanceLog($event, 20);
        } finally {
            $this->restoreCurrentPageResolver($previousResolver);
        }

        $this->assertSame(3, $page->currentPage());
        $this->assertSame(3, $page->lastPage());
        $this->assertCount(5, $page->items());

        $attendanceQueries = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'from event_attendances'),
        ));

        // 1 COUNT + 1 SELECT untuk event_attendances.
        $this->assertCount(2, $attendanceQueries);
        $this->assertStringStartsWith('select count(*) as aggregate from event_attendances', $attendanceQueries[0]);
        $this->assertStringContainsString('limit 20 offset 40', strtolower($attendanceQueries[1]));
    }

    /**
     * Halaman in-range tetap 1 COUNT + 1 SELECT (contract paginator tidak berubah).
     */
    public function test_in_range_page_runs_one_count_and_one_select(): void
    {
        [$event, $form] = $this->eventWithForm();
        $this->createAttendanceRows($event, $form, 45, now()->startOfSecond()->subDay(), null);

        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = str_replace(['"', '`'], '', $query->sql);
        });

        $previousResolver = $this->currentPageResolverValue();

        try {
            Paginator::currentPageResolver(static fn (): int => 2);
            $page = $this->reportingQuery()->paginateAttendanceLog($event, 20);
        } finally {
            $this->restoreCurrentPageResolver($previousResolver);
        }

        $this->assertSame(2, $page->currentPage());
        $this->assertSame(45, $page->total());

        $attendanceQueries = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'from event_attendances'),
        ));

        $this->assertCount(2, $attendanceQueries);
        $this->assertStringStartsWith('select count(*) as aggregate from event_attendances', $attendanceQueries[0]);
        $this->assertStringContainsString('limit 20 offset 20', strtolower($attendanceQueries[1]));
    }

    public function test_log_page_eager_loads_every_relation(): void
    {
        [$event, $form] = $this->eventWithForm();
        $scanner = User::factory()->create();
        $this->createAttendanceRows($event, $form, 20, now()->startOfSecond()->subHour(), $scanner);

        Model::preventLazyLoading(true);

        try {
            $page = $this->reportingQuery()->paginateAttendanceLog($event, 20);
            $items = $page->items();
        } finally {
            Model::preventLazyLoading(false);
        }

        $this->assertCount(20, $items);
        $this->assertIsArray($items[0]['attendee']);
        $this->assertIsArray($items[0]['scanned_by']);
    }

    public function test_log_page_query_count_is_bounded(): void
    {
        [$event, $form] = $this->eventWithForm();
        $scanner = User::factory()->create();
        $this->createAttendanceRows($event, $form, 20, now()->startOfSecond()->subHour(), $scanner);

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->reportingQuery()->paginateAttendanceLog($event, 20);

        // count + select utama + form_answers + users (attendee) + users (scanner).
        // Laravel tidak menyediakan API untuk melepas DB::listen(); setiap test
        // mendapat koneksi baru lewat RefreshDatabase sehingga listener tidak bocor.
        $this->assertSame(5, $queryCount);
    }

    /**
     * Query paginator yang benar-benar dikirim harus memakai index komposit tanpa
     * filesort. Read-only pada koneksi `mysql`; dilewati bila koneksi, tabel,
     * index, atau data MySQL tidak tersedia (CI tidak menyediakan service MySQL).
     */
    public function test_log_query_uses_composite_index_without_filesort_on_mysql(): void
    {
        // phpunit.xml memaksa DB_DATABASE=:memory: untuk koneksi default (SQLite);
        // koneksi `mysql` tetap memakai nama database asli dari environment proses.
        $database = getenv('DB_DATABASE');

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            $this->markTestSkipped('Nama database MySQL tidak tersedia di environment.');
        }

        $previousDefault = DB::getDefaultConnection();
        $previousDatabase = config('database.connections.mysql.database');

        try {
            config(['database.connections.mysql.database' => $database]);
            DB::purge('mysql');

            try {
                $connection = DB::connection('mysql');
                $connection->getPdo();
            } catch (\Throwable $exception) {
                $this->markTestSkipped('Koneksi MySQL tidak tersedia: '.$exception->getMessage());
            }

            if (! Schema::connection('mysql')->hasTable('event_attendances')) {
                $this->markTestSkipped('Tabel event_attendances tidak ada di MySQL.');
            }

            $indexNames = collect(Schema::connection('mysql')->getIndexes('event_attendances'))
                ->pluck('name')
                ->all();

            if (! in_array(self::INDEX_NAME, $indexNames, true)) {
                $this->markTestSkipped(self::INDEX_NAME.' belum dibuat di MySQL.');
            }

            // Batasi query pemilihan event agar DB yang sedang sibuk gagal cepat,
            // bukan menggantung tanpa batas. Variabel ini tidak ada di MariaDB,
            // jadi kegagalan SET diabaikan.
            foreach (['SET SESSION max_execution_time = 30000', 'SET SESSION lock_wait_timeout = 5'] as $sessionStatement) {
                try {
                    $connection->statement($sessionStatement);
                } catch (\Throwable) {
                    // Dialek tanpa variabel tersebut; guard tetap dilewati.
                }
            }

            // Pakai event nyata dari tabel kehadiran. Pemilihan sengaja index-only
            // (`ORDER BY event_id LIMIT 1`) supaya probe tidak memicu agregasi 1 juta
            // baris; ukuran dataset dicatat agar keterbatasan plan terlihat.
            $eventId = $connection->scalar(
                'SELECT ea.event_id
                 FROM event_attendances ea
                 INNER JOIN events e ON e.id = ea.event_id
                 WHERE e.deleted_at IS NULL
                 ORDER BY ea.event_id
                 LIMIT 1'
            );

            if (! is_string($eventId)) {
                $this->markTestSkipped('Tidak ada event_attendances dengan event aktif di MySQL.');
            }

            $selectedRowCount = (int) $connection->scalar(
                'SELECT COUNT(*) FROM event_attendances WHERE event_id = ?',
                [$eventId]
            );

            // Catat ukuran dataset yang benar-benar diuji (1 baris/event pada
            // dataset stress, jadi event terpilih bisa tetap kecil).
            fwrite(STDERR, sprintf(
                "\n[BE-P2-006] event terpilih: %s (%d baris kehadiran)\n",
                $eventId,
                $selectedRowCount,
            ));

            $captured = [];
            $connection->listen(static function (QueryExecuted $query) use (&$captured): void {
                $captured[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
            });

            // Jalankan service pada koneksi mysql agar SQL yang diuji adalah SQL asli
            // yang dikirim paginate(), bukan SELECT tulis tangan yang bisa drift.
            DB::setDefaultConnection('mysql');

            $event = Event::query()->findOrFail($eventId);
            $this->reportingQuery()->paginateAttendanceLog($event, 20);

            $listQuery = collect($captured)->first(
                static fn (array $query): bool => str_starts_with($query['sql'], 'select ')
                    && str_contains($query['sql'], 'order by'),
            );

            $this->assertIsArray($listQuery, 'Query daftar tidak tertangkap dari paginate().');

            $explainSql = $connection->getQueryGrammar()
                ->substituteBindingsIntoRawSql($listQuery['sql'], $listQuery['bindings']);

            $step = collect($connection->select('EXPLAIN '.$explainSql))
                ->map(static fn (object $row): array => (array) $row)
                ->first(static fn (array $row): bool => ($row['table'] ?? null) === 'event_attendances');

            $this->assertIsArray($step, 'EXPLAIN tidak memuat baris event_attendances.');

            $extra = (string) ($step['Extra'] ?? $step['extra'] ?? '');
            $index = (string) ($step['key'] ?? $step['Key'] ?? '');

            // Optimizer boleh memilih index 2 kolom (warisan BE-P2-006) atau 3 kolom
            // (BE-P2-025); keduanya sama-sama melayani prefix `event_id, scanned_at`.
            // Yang dikunci di sini: index komposit dipakai dan tidak ada filesort.
            $this->assertStringStartsWith(self::INDEX_PREFIX, $index);
            $this->assertStringNotContainsStringIgnoringCase('using filesort', $extra);
        } finally {
            DB::setDefaultConnection($previousDefault);
            config(['database.connections.mysql.database' => $previousDatabase]);
        }
    }
}
