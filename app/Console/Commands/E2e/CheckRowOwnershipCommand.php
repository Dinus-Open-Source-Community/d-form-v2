<?php

namespace App\Console\Commands\E2e;

use App\Support\E2e\FixtureScope;
use App\Support\E2e\RunLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buktikan tiga kepemilikan baris e2e untuk satu run: nol sisa, nol marker ilegal di window run,
 * dan nol baris run lain yang tersentuh teardown. Exit non-zero bila salah satu tidak terbukti.
 */
final class CheckRowOwnershipCommand extends Command
{
    protected $signature = 'check:row-ownership
        {--run-id= : runId yang sudah selesai}
        {--json : Cetak hasil sebagai JSON}';

    protected $description = 'Verify zero leftovers, no in-window illegal markers, and no cross-run teardown touches.';

    public function handle(): int
    {
        $runId = (string) ($this->option('run-id') ?? '');

        if (preg_match('/^\d{8}T\d{6}-[0-9a-f]{6}$/', $runId) !== 1) {
            throw new \InvalidArgumentException('--run-id must look like 20260102T030405-abcdef.');
        }

        $prefix = 'e2e+'.$runId.'+';
        $slugPrefix = strtolower(str_replace('+', '-', $prefix));
        $leftover = FixtureScope::countRun($prefix, $slugPrefix);

        $ledger = new RunLedger();
        $window = $this->window($ledger->baseline($runId));
        $illegal = $window['startedAt'] === null ? [] : $this->illegalCounts($window['startedAt']);
        $crossRunTables = $this->crossRunTables($ledger->teardown($runId));

        $leftoverTotal = array_sum($leftover);
        $illegalTotal = array_sum($illegal);
        $crossRunTotal = array_sum($crossRunTables);

        // Tanpa baseline, pemeriksaan window tidak bisa dijalankan; itu belum terbukti, bukan lulus.
        $ok = $window['source'] === 'ledger'
            && $leftoverTotal === 0
            && $illegalTotal === 0
            && $crossRunTotal === 0;

        $this->line(json_encode([
            'runId' => $runId,
            'leftover' => $leftover,
            'leftoverTotal' => $leftoverTotal,
            'window' => $window,
            'illegal' => $illegal,
            'illegalTotal' => $illegalTotal,
            'crossRun' => ['violations' => $crossRunTotal, 'tables' => $crossRunTables],
            'ok' => $ok,
        ], JSON_THROW_ON_ERROR));

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Batas window run dari ledger; `source=absent` bila baseline belum ada.
     *
     * @param  array<string, mixed>|null  $baseline
     * @return array{source: string, startedAt: string|null, note?: string}
     */
    private function window(?array $baseline): array
    {
        $startedAt = $baseline['startedAt'] ?? null;

        if (! is_string($startedAt) || $startedAt === '') {
            return [
                'source' => 'absent',
                'startedAt' => null,
                'note' => 'no ledger baseline for this run; in-window illegal markers cannot be proven',
            ];
        }

        if (! Schema::hasColumn('users', 'created_at') || ! Schema::hasColumn('events', 'created_at')) {
            return [
                'source' => 'ledger',
                'startedAt' => $startedAt,
                'note' => 'created_at missing on users/events; illegal markers cannot be window-scoped',
            ];
        }

        return ['source' => 'ledger', 'startedAt' => $startedAt];
    }

    /**
     * Marker ilegal yang dibuat sejak awal run; baris lama di luar window tidak dihitung.
     *
     * @return array<string, int>
     */
    private function illegalCounts(string $startedAt): array
    {
        return [
            'users' => $this->illegalCount(
                DB::table('users')
                    ->where('email', 'like', '%@e2e.invalid')
                    ->where('created_at', '>=', $startedAt)
                    ->pluck('email'),
                '/^e2e\+[0-9A-Za-z-]{1,22}\+o[0-9a-f]{12}@e2e\.invalid$/',
            ),
            'events' => $this->illegalCount(
                DB::table('events')
                    ->where('slug', 'like', 'e2e-%')
                    ->where('created_at', '>=', $startedAt)
                    ->pluck('slug'),
                '/^e2e-\d{8}t\d{6}-[0-9a-f]{6}-o[0-9a-f]{12}$/',
            ),
        ];
    }

    /**
     * Tabel yang tercatat di audit teardown; kosong bila teardown bersih atau belum pernah jalan.
     *
     * @param  array<string, mixed>|null  $teardown
     * @return array<string, int>
     */
    private function crossRunTables(?array $teardown): array
    {
        $violations = $teardown['violations'] ?? [];

        if (! is_array($violations)) {
            return [];
        }

        $tables = [];

        foreach ($violations as $table => $count) {
            if (is_string($table) && is_numeric($count)) {
                $tables[$table] = (int) $count;
            }
        }

        return $tables;
    }

    /**
     * Hitung nilai yang tidak cocok dengan grammar marker kanonik.
     *
     * @param  iterable<int, mixed>  $values
     */
    private function illegalCount(iterable $values, string $pattern): int
    {
        $count = 0;

        foreach ($values as $value) {
            if (preg_match($pattern, (string) $value) !== 1) {
                $count++;
            }
        }

        return $count;
    }
}
