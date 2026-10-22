<?php

namespace App\Console\Commands\Stress;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use stdClass;
use Throwable;

/**
 * Snapshot dan diff performance_schema.events_statements_summary_by_digest.
 *
 * Dipakai harness stress untuk mengukur query apa yang dijalankan beban k6
 * (snapshot sebelum → beban → diff), lalu EXPLAIN query teratas.
 */
final class DigestSnapshotCommand extends Command
{
    private const DEFAULT_EXPLAIN_LIMIT = 10;

    /** Pernyataan internal command ini dan setup koneksi: bukan kandidat analisis, dibuang dari diff. */
    private const IGNORED_PREFIXES = ['EXPLAIN', 'USE', 'SET'];

    protected $signature = 'stress:digests
        {--snapshot= : Path JSON snapshot performance_schema}
        {--diff= : Path snapshot pembanding yang akan di-diff}
        {--out= : Path JSON hasil diff (default storage/app/stress/digests-diff.json)}
        {--limit=10 : Jumlah delta teratas yang di-EXPLAIN}';

    protected $description = 'Snapshot dan diff digest query MySQL (performance_schema) untuk analisis beban stress';

    public function handle(): int
    {
        $snapshotPath = $this->pathOption('snapshot');
        $diffPath = $this->pathOption('diff');

        if (($snapshotPath === null) === ($diffPath === null)) {
            $this->error('Pakai salah satu: --snapshot=<path> atau --diff=<before.json> [--out=<path>] [--limit=<n>].');

            return self::FAILURE;
        }

        if (! $this->ensureMysql()) {
            return self::FAILURE;
        }

        if ($snapshotPath !== null) {
            return $this->writeSnapshot($snapshotPath);
        }

        return $this->writeDiff($diffPath, $this->pathOption('out') ?? storage_path('app/stress/digests-diff.json'));
    }

    /**
     * Tulis snapshot seluruh digest milik schema aplikasi ke file JSON.
     */
    private function writeSnapshot(string $path): int
    {
        $digests = $this->readDigests();

        $written = $this->writeJson($path, [
            'captured_at' => now()->toIso8601String(),
            'schema' => $this->schemaName(),
            'digests' => $digests,
        ]);

        if (! $written) {
            return self::FAILURE;
        }

        $this->info(sprintf('Snapshot %d digest (%s) ditulis ke %s', count($digests), $this->schemaName(), $path));

        return self::SUCCESS;
    }

    /**
     * Hitung selisih counter terhadap snapshot pembanding, lalu EXPLAIN delta teratas.
     */
    private function writeDiff(string $beforePath, string $outPath): int
    {
        $before = $this->readSnapshot($beforePath);

        if ($before === null) {
            return self::FAILURE;
        }

        $deltas = $this->buildDeltas($before, $this->readDigests());

        if ($deltas === []) {
            $this->warn('Tidak ada delta query. Pastikan beban berjalan di schema '.$this->schemaName().' di antara snapshot dan diff.');
        }

        $deltas = $this->attachExplains($deltas, $this->explainLimit());

        $written = $this->writeJson($outPath, [
            'generated_at' => now()->toIso8601String(),
            'before' => $beforePath,
            'totals' => [
                'calls' => array_sum(array_column($deltas, 'calls')),
                'total_ms' => round(array_sum(array_column($deltas, 'total_ms')), 3),
            ],
            'deltas' => $deltas,
        ]);

        if (! $written) {
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Diff %d digest · %d panggilan · %s ms ditulis ke %s',
            count($deltas),
            array_sum(array_column($deltas, 'calls')),
            number_format(round(array_sum(array_column($deltas, 'total_ms')), 3), 3),
            $outPath,
        ));

        $this->renderSlowest($deltas);

        return self::SUCCESS;
    }

    /**
     * Baca digest dari performance_schema untuk schema koneksi aktif (global, bukan per-schema).
     *
     * @return list<array{digest:string,digest_text:string,count_star:int,sum_timer_wait:int,sum_rows_examined:int,sum_rows_sent:int,sum_no_index_used:int,sum_created_tmp_tables:int}>
     */
    private function readDigests(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT digest, digest_text, count_star, sum_timer_wait, sum_rows_examined,
                   sum_rows_sent, sum_no_index_used, sum_created_tmp_tables
            FROM performance_schema.events_statements_summary_by_digest
            WHERE schema_name = DATABASE()
            SQL);

        return array_map(static fn (stdClass $row): array => [
            'digest' => (string) $row->digest,
            'digest_text' => (string) ($row->digest_text ?? ''),
            'count_star' => (int) $row->count_star,
            'sum_timer_wait' => (int) $row->sum_timer_wait,
            'sum_rows_examined' => (int) $row->sum_rows_examined,
            'sum_rows_sent' => (int) $row->sum_rows_sent,
            'sum_no_index_used' => (int) $row->sum_no_index_used,
            'sum_created_tmp_tables' => (int) $row->sum_created_tmp_tables,
        ], $rows);
    }

    /**
     * Baca snapshot pembanding; null (plus pesan error) bila file tidak valid.
     *
     * @return list<array{digest:string,count_star:int,sum_timer_wait:int,sum_rows_examined:int,sum_rows_sent:int,sum_no_index_used:int,sum_created_tmp_tables:int}>|null
     */
    private function readSnapshot(string $path): ?array
    {
        if (! is_file($path)) {
            $this->error('Snapshot pembanding tidak ditemukan: '.$path);

            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! is_array($decoded['digests'] ?? null)) {
            $this->error('Snapshot pembanding tidak berisi "digests": '.$path);

            return null;
        }

        $digests = [];

        foreach ($decoded['digests'] as $row) {
            if (! is_array($row) || ! is_string($row['digest'] ?? null)) {
                continue;
            }

            $digests[] = [
                'digest' => $row['digest'],
                'count_star' => (int) ($row['count_star'] ?? 0),
                'sum_timer_wait' => (int) ($row['sum_timer_wait'] ?? 0),
                'sum_rows_examined' => (int) ($row['sum_rows_examined'] ?? 0),
                'sum_rows_sent' => (int) ($row['sum_rows_sent'] ?? 0),
                'sum_no_index_used' => (int) ($row['sum_no_index_used'] ?? 0),
                'sum_created_tmp_tables' => (int) ($row['sum_created_tmp_tables'] ?? 0),
            ];
        }

        return $digests;
    }

    /**
     * Hitung delta counter per digest dan urutkan dari total waktu terbesar.
     *
     * @param  list<array{digest:string,count_star:int,sum_timer_wait:int,sum_rows_examined:int,sum_rows_sent:int,sum_no_index_used:int,sum_created_tmp_tables:int}>  $before
     * @param  list<array{digest:string,digest_text:string,count_star:int,sum_timer_wait:int,sum_rows_examined:int,sum_rows_sent:int,sum_no_index_used:int,sum_created_tmp_tables:int}>  $after
     * @return list<array{digest:string,digest_text:string,calls:int,total_ms:float,avg_ms:float,rows_examined:int,rows_sent:int,no_index_used:int,explain:array<mixed>|null,explain_error:string|null}>
     */
    private function buildDeltas(array $before, array $after): array
    {
        $previous = [];

        foreach ($before as $row) {
            $previous[$row['digest']] = $row;
        }

        $deltas = [];

        foreach ($after as $row) {
            if ($this->isIgnoredDigest($row['digest_text'])) {
                continue;
            }

            $baseline = $previous[$row['digest']] ?? null;
            $calls = $row['count_star'] - ($baseline['count_star'] ?? 0);

            if ($calls <= 0) {
                continue;
            }

            $timerWait = max(0, $row['sum_timer_wait'] - ($baseline['sum_timer_wait'] ?? 0));
            $totalMs = $timerWait / 1e9;

            $deltas[] = [
                'digest' => $row['digest'],
                'digest_text' => $row['digest_text'],
                'calls' => $calls,
                'total_ms' => round($totalMs, 3),
                'avg_ms' => round($totalMs / $calls, 3),
                'rows_examined' => max(0, $row['sum_rows_examined'] - ($baseline['sum_rows_examined'] ?? 0)),
                'rows_sent' => max(0, $row['sum_rows_sent'] - ($baseline['sum_rows_sent'] ?? 0)),
                'no_index_used' => max(0, $row['sum_no_index_used'] - ($baseline['sum_no_index_used'] ?? 0)),
                'explain' => null,
                'explain_error' => null,
            ];
        }

        usort($deltas, static function (array $left, array $right): int {
            if ($left['total_ms'] === $right['total_ms']) {
                return strcmp($left['digest'], $right['digest']);
            }

            return $right['total_ms'] <=> $left['total_ms'];
        });

        return $deltas;
    }

    /**
     * Jalankan EXPLAIN FORMAT=JSON hanya untuk delta teratas; kegagalan dicatat, bukan menghentikan command.
     *
     * @param  list<array{digest:string,digest_text:string,calls:int,total_ms:float,avg_ms:float,rows_examined:int,rows_sent:int,no_index_used:int,explain:array<mixed>|null,explain_error:string|null}>  $deltas
     * @return list<array{digest:string,digest_text:string,calls:int,total_ms:float,avg_ms:float,rows_examined:int,rows_sent:int,no_index_used:int,explain:array<mixed>|null,explain_error:string|null}>
     */
    private function attachExplains(array $deltas, int $limit): array
    {
        foreach (array_slice($deltas, 0, $limit) as $index => $delta) {
            $plan = $this->explainDigest($delta['digest_text']);
            $deltas[$index]['explain'] = $plan['explain'];
            $deltas[$index]['explain_error'] = $plan['explain_error'];
        }

        return $deltas;
    }

    /**
     * Ambil rencana akses satu digest_text; digest ber-placeholder biasanya gagal dan dicatat sebagai explain_error.
     *
     * @return array{explain:array<mixed>|null,explain_error:string|null}
     */
    private function explainDigest(string $digestText): array
    {
        try {
            $rows = DB::select('EXPLAIN FORMAT=JSON '.$this->explainSql($digestText));
        } catch (Throwable $exception) {
            return ['explain' => null, 'explain_error' => 'EXPLAIN gagal: '.$exception->getMessage()];
        }

        $raw = $rows[0]->EXPLAIN ?? null;

        if (! is_string($raw)) {
            return ['explain' => null, 'explain_error' => 'EXPLAIN tidak mengembalikan JSON'];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return ['explain' => null, 'explain_error' => 'EXPLAIN JSON tidak bisa diparse'];
        }

        return ['explain' => $decoded, 'explain_error' => null];
    }

    /**
     * Buang digest yang bukan kandidat analisis: EXPLAIN dari command ini, serta
     * USE/SET yang selalu muncul saat koneksi baru dibuka dan waktunya ~0 ms.
     */
    private function isIgnoredDigest(string $digestText): bool
    {
        $normalised = strtoupper(ltrim($digestText));

        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($normalised, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rapikan digest_text sebelum EXPLAIN: MySQL 9 menolak spasi sebelum "(" yang
     * dihasilkan normalisasi miliknya sendiri (mis. "COUNT ( * )").
     */
    private function explainSql(string $digestText): string
    {
        return (string) preg_replace('/\s+\(/', '(', $digestText);
    }

    /**
     * Tampilkan beberapa delta terlambat agar terlihat langsung di terminal.
     *
     * @param  list<array{digest_text:string,calls:int,total_ms:float,avg_ms:float,explain_error:string|null}>  $deltas
     */
    private function renderSlowest(array $deltas): void
    {
        if ($deltas === []) {
            return;
        }

        $rows = [];

        foreach (array_slice($deltas, 0, 5) as $delta) {
            $rows[] = [
                number_format($delta['total_ms'], 3),
                number_format($delta['avg_ms'], 3),
                (string) $delta['calls'],
                substr($delta['digest_text'], 0, 100),
            ];
        }

        $this->table(['Total ms', 'Avg ms', 'Calls', 'Digest'], $rows);
    }

    /**
     * Path hasil opsi command; null bila opsi tidak diisi.
     */
    private function pathOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * Batas jumlah EXPLAIN; minimal satu.
     */
    private function explainLimit(): int
    {
        return max(1, (int) ($this->option('limit') ?? self::DEFAULT_EXPLAIN_LIMIT));
    }

    /**
     * Tolak driver non-MySQL karena performance_schema tidak tersedia di sana.
     */
    private function ensureMysql(): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'mysql') {
            $this->error('stress:digests hanya mendukung MySQL (driver aktif: '.$driver.').');

            return false;
        }

        return true;
    }

    /**
     * Nama schema aktif, dipakai sebagai nilai "schema" di snapshot.
     */
    private function schemaName(): string
    {
        return (string) DB::connection()->getDatabaseName();
    }

    /**
     * Tulis payload JSON (pretty print) dan pastikan direktori tujuan ada.
     *
     * @param  array<string,mixed>  $payload
     */
    private function writeJson(string $path, array $payload): bool
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! File::makeDirectory($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Gagal membuat direktori: '.$directory);

            return false;
        }

        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if (! is_string($encoded)) {
            $this->error('Gagal mengubah payload menjadi JSON: '.json_last_error_msg());

            return false;
        }

        File::put($path, $encoded.PHP_EOL);

        return true;
    }
}
