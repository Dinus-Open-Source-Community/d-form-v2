<?php

namespace App\Console\Commands\E2e;

use App\Support\E2e\FixtureIdentity;
use Illuminate\Console\Command;

/**
 * Turunkan ownerKey, marker, slug, dan email dari ownerId + runId.
 * Satu implementasi derivasi untuk debugging dan reproduksi marker.
 */
final class DeriveKeyCommand extends Command
{
    protected $signature = 'e2e:derive-key
        {--run-id= : runId 22 karakter}
        {--owner-id= : ownerId <project>.<spec>.<test>.r<retry>}
        {--json : Cetak hasil sebagai JSON}';

    protected $description = 'Derive the canonical e2e marker from a runId and ownerId.';

    public function handle(): int
    {
        $runId = (string) ($this->option('run-id') ?? '');
        $ownerId = (string) ($this->option('owner-id') ?? '');
        $identity = FixtureIdentity::fromOwnerId($runId, $ownerId);

        $this->line(json_encode([
            'ownerId' => $ownerId,
            'runId' => $identity->runId,
            'ownerKey' => $identity->ownerKey,
            'marker' => $identity->marker,
            'slug' => $identity->slug(),
            'email' => $identity->email(),
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
