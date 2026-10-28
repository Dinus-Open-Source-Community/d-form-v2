<?php

namespace App\Console\Commands\E2e;

use App\Support\E2e\FixtureScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sapu run e2e yang ditinggal: tanpa heartbeat hidup dan baris terbaru lebih tua dari grace.
 * Penghapusan memakai scope run yang sah (prefix marker/slug), bukan predikat bebas.
 */
final class SweepCommand extends Command
{
    protected $signature = 'e2e:sweep
        {--grace-hours=24 : Grace period untuk run yang setup-nya mati sebelum heartbeat pertama}
        {--budget=120 : Batas waktu keseluruhan dalam detik}
        {--live= : Daftar runId hidup yang sudah diverifikasi pemanggil, dipisah koma}
        {--json : Cetak hasil sebagai JSON}';

    protected $description = 'Delete abandoned e2e runs (no live heartbeat, older than the grace period).';

    public function handle(): int
    {
        $graceHours = max(1, (int) ($this->option('grace-hours') ?? 24));
        $budgetSeconds = max(1, (int) ($this->option('budget') ?? 120));
        $startedAt = microtime(true);
        $liveRuns = array_filter(array_map('trim', explode(',', (string) ($this->option('live') ?? ''))));

        $runIds = $this->candidateRunIds();
        $report = [
            'scanned' => count($runIds),
            'deletedRuns' => [],
            'skippedLive' => [],
            'skippedFresh' => [],
            'deleted' => [],
            'budgetExceeded' => false,
        ];

        foreach ($runIds as $runId) {
            if (microtime(true) - $startedAt > $budgetSeconds) {
                $report['budgetExceeded'] = true;
                break;
            }

            if (in_array($runId, $liveRuns, true) || $this->hasLiveHeartbeat($runId)) {
                $report['skippedLive'][] = $runId;
                continue;
            }

            if (! $this->isOlderThan($runId, $graceHours)) {
                $report['skippedFresh'][] = $runId;
                continue;
            }

            $prefix = 'e2e+'.$runId.'+';
            $slugPrefix = strtolower(str_replace('+', '-', $prefix));

            $report['deleted'][$runId] = FixtureScope::deleteRun($prefix, $slugPrefix);
            $report['deletedRuns'][] = $runId;
            $this->removeHeartbeatDirectory($runId);
        }

        $this->line(json_encode($report, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /**
     * Kumpulkan runId dari email pemilik fixture (satu-satunya kolom wajib tiap run).
     *
     * @return list<string>
     */
    private function candidateRunIds(): array
    {
        $emails = DB::table('users')
            ->where('email', 'like', 'e2e+%')
            ->pluck('email');

        $runIds = [];

        foreach ($emails as $email) {
            if (preg_match('/^e2e\+([0-9A-Za-z-]{1,22})\+o[0-9a-f]{12}@/', (string) $email, $matches) === 1) {
                $runIds[$matches[1]] = true;
            }
        }

        return array_keys($runIds);
    }

    /**
     * Baris terbaru run ini lebih tua dari grace period.
     */
    private function isOlderThan(string $runId, int $graceHours): bool
    {
        $prefix = 'e2e+'.$runId.'+';
        $slugPrefix = strtolower(str_replace('+', '-', $prefix));

        $newest = collect([
            DB::table('users')->where('email', 'like', $prefix.'%')->max('created_at'),
            DB::table('events')->where('slug', 'like', $slugPrefix.'%')->max('created_at'),
            DB::table('forms')->where('title', 'like', '% ['.$prefix.'%')->max('created_at'),
        ])->filter()->max();

        if ($newest === null) {
            return true;
        }

        return strtotime((string) $newest) < now()->subHours($graceHours)->getTimestamp();
    }

    /**
     * Fallback liveness saat command dijalankan sendiri: mtime <= 30 s, pid ada, cmdline node/playwright.
     */
    private function hasLiveHeartbeat(string $runId): bool
    {
        $directory = storage_path('app/e2e/heartbeats/'.$runId);

        if (! is_dir($directory)) {
            return false;
        }

        foreach (glob($directory.'/*.json') ?: [] as $file) {
            if (time() - (int) filemtime($file) > 30) {
                continue;
            }

            $payload = json_decode((string) file_get_contents($file), true);

            if (! is_array($payload) || ! is_int($payload['pid'] ?? null)) {
                continue;
            }

            $cmdlinePath = '/proc/'.$payload['pid'].'/cmdline';

            if (! is_file($cmdlinePath)) {
                continue;
            }

            $cmdline = (string) file_get_contents($cmdlinePath);

            if (preg_match('/playwright|node/i', $cmdline) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hapus folder heartbeat run yang sudah disapu.
     */
    private function removeHeartbeatDirectory(string $runId): void
    {
        $directory = storage_path('app/e2e/heartbeats/'.$runId);

        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }
}
