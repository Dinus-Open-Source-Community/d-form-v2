<?php

namespace App\Support\E2e;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ledger per run e2e: baseline saat fixture pertama dibuat dan audit saat teardown menghapus.
 *
 * Satu-satunya penulis `<storage>/app/e2e/runs/<runId>/`. `check:row-ownership` membacanya
 * agar cakupan pemeriksaan bisa dibuktikan, bukan ditebak dari state DB setelah delete.
 */
final class RunLedger
{
    private const TABLES = [
        'users',
        'events',
        'forms',
        'form_fields',
        'form_answers',
        'event_attendances',
    ];

    /**
     * Tulis baseline sekali per run, dipanggil sebelum baris pertama dibuat.
     */
    public function recordBaseline(FixtureIdentity $identity): void
    {
        $path = $this->path($identity->runId, 'baseline.json');

        if (is_file($path)) {
            return;
        }

        $this->write($path, [
            'runId' => $identity->runId,
            'startedAt' => now()->toDateTimeString(),
            'markerPrefix' => $identity->markerPrefix(),
            'maxIds' => $this->maxIds(),
        ]);
    }

    /**
     * Baseline satu run; `null` bila run dibuat sebelum ledger ada.
     *
     * @return array<string, mixed>|null
     */
    public function baseline(string $runId): ?array
    {
        return $this->read($this->path($runId, 'baseline.json'));
    }

    /**
     * Catat baris milik run lain yang ikut terhapus teardown; tanpa temuan tidak menulis apa pun.
     *
     * @param  array<string, int>  $violations
     */
    public function recordTeardown(string $runId, array $violations): void
    {
        if ($violations === []) {
            return;
        }

        $this->write($this->path($runId, 'teardown.json'), [
            'runId' => $runId,
            'recordedAt' => now()->toDateTimeString(),
            'violations' => $violations,
        ]);
    }

    /**
     * Audit teardown satu run; `null` bila belum pernah dijalankan atau tidak ada temuan.
     *
     * @return array<string, mixed>|null
     */
    public function teardown(string $runId): ?array
    {
        return $this->read($this->path($runId, 'teardown.json'));
    }

    /**
     * ID maksimum per tabel domain sebelum run menulis apa pun.
     *
     * @return array<string, int|string|null>
     */
    private function maxIds(): array
    {
        $maxIds = [];

        foreach (self::TABLES as $table) {
            $maxIds[$table] = DB::table($table)->max('id');
        }

        return $maxIds;
    }

    /**
     * Path artefak satu run; runId divalidasi agar tidak bisa keluar dari folder ledger.
     */
    private function path(string $runId, string $file): string
    {
        if (preg_match('/^\d{8}T\d{6}-[0-9a-f]{6}$/', $runId) !== 1) {
            throw new InvalidArgumentException('runId must look like 20260102T030405-abcdef.');
        }

        return storage_path('app/e2e/runs/'.$runId.'/'.$file);
    }

    /**
     * Tulis artefak JSON; folder run dibuat sekali bila belum ada.
     *
     * @param  array<string, mixed>  $payload
     */
    private function write(string $path, array $payload): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Cannot create e2e ledger directory: '.$directory);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * Baca artefak JSON; `null` bila belum ada atau isinya rusak.
     *
     * @return array<string, mixed>|null
     */
    private function read(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
