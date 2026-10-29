<?php

namespace App\Console\Commands\E2e;

use App\Support\E2e\FixtureScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Periksa bahwa run selesai tidak meninggalkan baris dan tidak ada marker e2e ilegal.
 * Exit non-zero bila masih ada sisa atau marker yang tidak sesuai grammar kanonik.
 */
final class CheckRowOwnershipCommand extends Command
{
    protected $signature = 'check:row-ownership
        {--run-id= : runId yang sudah selesai}
        {--json : Cetak hasil sebagai JSON}';

    protected $description = 'Verify zero leftovers for a finished run and no illegal e2e markers.';

    public function handle(): int
    {
        $runId = (string) ($this->option('run-id') ?? '');

        if (preg_match('/^\d{8}T\d{6}-[0-9a-f]{6}$/', $runId) !== 1) {
            throw new \InvalidArgumentException('--run-id must look like 20260102T030405-abcdef.');
        }

        $prefix = 'e2e+'.$runId.'+';
        $slugPrefix = strtolower(str_replace('+', '-', $prefix));
        $leftover = FixtureScope::countRun($prefix, $slugPrefix);

        $illegal = [
            'users' => $this->illegalCount(
                DB::table('users')->where('email', 'like', '%@e2e.invalid')->pluck('email'),
                '/^e2e\+[0-9A-Za-z-]{1,22}\+o[0-9a-f]{12}@e2e\.invalid$/',
            ),
            'events' => $this->illegalCount(
                DB::table('events')->where('slug', 'like', 'e2e-%')->pluck('slug'),
                '/^e2e-\d{8}t\d{6}-[0-9a-f]{6}-o[0-9a-f]{12}$/',
            ),
        ];

        $leftoverTotal = array_sum($leftover);
        $illegalTotal = array_sum($illegal);

        $this->line(json_encode([
            'runId' => $runId,
            'leftover' => $leftover,
            'leftoverTotal' => $leftoverTotal,
            'illegal' => $illegal,
            'illegalTotal' => $illegalTotal,
            'ok' => $leftoverTotal === 0 && $illegalTotal === 0,
        ], JSON_THROW_ON_ERROR));

        return $leftoverTotal === 0 && $illegalTotal === 0 ? self::SUCCESS : self::FAILURE;
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
