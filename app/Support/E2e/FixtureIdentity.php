<?php

namespace App\Support\E2e;

use InvalidArgumentException;

/**
 * Identitas fixture e2e: satu-satunya tempat marker kanonik 40 karakter dibentuk.
 *
 * Marker = `e2e+<runId>+<ownerKey>`; semua bentuk turunan (slug, email) berasal dari sini
 * agar fixture, teardown, sweep, dan pemeriksaan kepemilikan memakai nilai yang sama.
 */
final readonly class FixtureIdentity
{
    private const MARKER_PATTERN = '/^e2e\+([0-9A-Za-z-]{1,22})\+o([0-9a-f]{12})$/';

    private const RUN_ID_PATTERN = '/^\d{8}T\d{6}-[0-9a-f]{6}$/';

    public function __construct(
        public string $marker,
        public string $runId,
        public string $ownerKey,
    ) {
    }

    /** Bentuk identitas dari marker kanonik; menolak format lain agar `--marker` tidak longgar. */
    public static function fromMarker(string $marker): self
    {
        if (preg_match(self::MARKER_PATTERN, $marker, $matches) !== 1) {
            throw new InvalidArgumentException(
                'Marker must match ^e2e\+<runId>\+o<12 hex>$ (40 characters).',
            );
        }

        return new self($marker, $matches[1], 'o'.$matches[2]);
    }

    /** Bentuk identitas dari `runId` + `ownerId`; `ownerKey` = `o` + 12 hex pertama sha256(ownerId). */
    public static function fromOwnerId(string $runId, string $ownerId): self
    {
        if (preg_match(self::RUN_ID_PATTERN, $runId) !== 1) {
            throw new InvalidArgumentException('runId must look like 20260102T030405-abcdef.');
        }

        if ($ownerId === '') {
            throw new InvalidArgumentException('ownerId must not be empty.');
        }

        $ownerKey = 'o'.substr(hash('sha256', $ownerId), 0, 12);

        return self::fromMarker('e2e+'.$runId.'+'.$ownerKey);
    }

    /** Kolom `[a-z0-9-]` (slug event): huruf kecil, `+` menjadi `-`. */
    public function slug(): string
    {
        return strtolower(str_replace('+', '-', $this->marker));
    }

    /** Email pemilik fixture; local part adalah marker kanonik. */
    public function email(): string
    {
        return $this->marker.'@e2e.invalid';
    }

    /** Judul/label ber-marker memakai sufiks ` [<marker>]`. */
    public function suffixed(string $value): string
    {
        return $value.' ['.$this->marker.']';
    }

    /** Prefix `LIKE` milik satu run; hanya dipakai cleanup/sweep ber-scope run. */
    public function markerPrefix(): string
    {
        return 'e2e+'.$this->runId.'+';
    }

    /** Prefix slug milik satu run; pasangan `markerPrefix()`. */
    public function slugPrefix(): string
    {
        return strtolower(str_replace('+', '-', $this->markerPrefix()));
    }
}
