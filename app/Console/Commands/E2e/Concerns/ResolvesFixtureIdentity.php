<?php

namespace App\Console\Commands\E2e\Concerns;

use App\Support\E2e\FixtureIdentity;

/**
 * Membaca identitas fixture dari `--marker` (kanonik) atau `--run-id` + `--owner-id`.
 * Dipakai semua command e2e agar hanya ada satu cara membentuk marker.
 */
trait ResolvesFixtureIdentity
{
    /**
     * Identitas dari opsi command; melempar bila tidak ada bentuk yang cocok.
     */
    protected function resolveIdentity(): FixtureIdentity
    {
        $marker = (string) ($this->option('marker') ?? '');

        if ($marker !== '') {
            return FixtureIdentity::fromMarker($marker);
        }

        $runId = (string) ($this->option('run-id') ?? '');
        $ownerId = (string) ($this->option('owner-id') ?? '');

        if ($runId === '' || $ownerId === '') {
            throw new \InvalidArgumentException(
                'Provide --marker=e2e+<runId>+o<12 hex>, or both --run-id and --owner-id.',
            );
        }

        return FixtureIdentity::fromOwnerId($runId, $ownerId);
    }

    /**
     * Baca payload JSON dari stdin; array kosong bila stdin tidak berisi JSON.
     *
     * @return array<string, mixed>
     */
    protected function readPayload(): array
    {
        $raw = stream_get_contents(STDIN);

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('stdin payload must be a JSON object.');
        }

        return $decoded;
    }
}
