<?php

namespace App\Console\Commands\E2e;

use App\Console\Commands\E2e\Concerns\ResolvesFixtureIdentity;
use App\Support\E2e\FixtureScope;
use Illuminate\Console\Command;

/**
 * Hapus baris fixture berdasarkan marker kanonik (exact) atau prefix satu run.
 * Idempoten, retry sekali setelah 250 ms, dan melaporkan tabel yang gagal.
 */
final class CleanupCommand extends Command
{
    use ResolvesFixtureIdentity;

    protected $signature = 'e2e:cleanup
        {--marker= : Marker kanonik e2e+<runId>+o<12 hex>}
        {--slug= : Slug turunan marker (divalidasi bila diisi)}
        {--marker-prefix= : Prefix run e2e+<runId>+ (khusus global teardown)}
        {--slug-prefix= : Prefix slug run (khusus global teardown)}
        {--run-id= : runId bila marker diturunkan dari ownerId}
        {--owner-id= : ownerId bila marker diturunkan dari ownerId}
        {--json : Cetak hasil sebagai JSON}';

    protected $description = 'Delete marker-owned e2e fixture rows with exact predicates.';

    public function handle(): int
    {
        $markerPrefix = (string) ($this->option('marker-prefix') ?? '');

        if ($markerPrefix !== '') {
            $slugPrefix = (string) ($this->option('slug-prefix') ?? '');
            $deleted = $this->withRetry(
                fn (): array => FixtureScope::deleteRun($markerPrefix, $slugPrefix),
            );
        } else {
            $identity = $this->resolveIdentity();
            $slug = (string) ($this->option('slug') ?? '');

            if ($slug !== '' && $slug !== $identity->slug()) {
                throw new \InvalidArgumentException('--slug must equal markerToSlug(--marker).');
            }

            $scope = new FixtureScope($identity);
            $deleted = $this->withRetry(fn (): array => $scope->delete());
        }

        $total = array_sum($deleted);
        $this->line(json_encode(['deleted' => $deleted, 'total' => $total], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /**
     * Jalankan penghapusan, ulangi sekali setelah 250 ms bila gagal.
     *
     * @param  callable(): array<string, int>  $operation
     * @return array<string, int>
     */
    private function withRetry(callable $operation): array
    {
        try {
            return $operation();
        } catch (\Throwable $first) {
            usleep(250_000);

            try {
                return $operation();
            } catch (\Throwable $second) {
                throw new \RuntimeException(
                    'e2e cleanup failed after retry; leftovers are reported by check:row-ownership. '
                    .$second->getMessage(),
                    0,
                    $second,
                );
            }
        }
    }
}
