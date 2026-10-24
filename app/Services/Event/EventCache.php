<?php

namespace App\Services\Event;

use Illuminate\Support\Facades\Cache;

/**
 * Cache agregat event dengan buster per kebutuhan: perubahan satu aspek
 * (mis. kategori) tidak membuang cache yang tidak terkait.
 */
final class EventCache
{
    public const BUSTER_LIST = 'events:list:cache:buster';

    public const BUSTER_CATEGORY = 'events:category:cache:buster';

    public const BUSTER_STATS = 'events:stats:cache:buster';

    /** Khusus agregat jumlah pendaftar; registrasi tidak boleh membuang statistik event. */
    public const BUSTER_REGISTRANTS = 'events:registrants:cache:buster';

    /**
     * Ambil array dari cache; resolver hanya berjalan saat cache kosong atau buster berubah.
     *
     * @template TValue
     * @param  \Closure(): array<TValue>  $resolver
     * @return array<TValue>
     */
    public static function remember(
        string $name,
        int $ttlSeconds,
        \Closure $resolver,
        ?string $buster = self::BUSTER_LIST,
    ): array {
        $key = self::keyFor($name, $buster);

        /** @var array<TValue> $cached */
        $cached = Cache::remember($key, $ttlSeconds, $resolver);

        return $cached;
    }

    /**
     * Baca nilai cache tanpa resolver; `null` berarti kunci belum terisi (request path
     * dilarang menghitung sendiri — isi lewat command `dashboard:warm`).
     *
     * @return array<mixed>|null
     */
    public static function peek(string $name, ?string $buster = self::BUSTER_LIST): ?array
    {
        $cached = Cache::get(self::keyFor($name, $buster));

        return is_array($cached) ? $cached : null;
    }

    /**
     * Hitung ulang lalu timpa nilai cache tanpa jendela kosong (dipakai scheduler, bukan request).
     *
     * @template TValue
     * @param  \Closure(): array<TValue>  $resolver
     * @return array<TValue>
     */
    public static function refresh(
        string $name,
        int $ttlSeconds,
        \Closure $resolver,
        ?string $buster = self::BUSTER_LIST,
    ): array {
        $value = $resolver();
        Cache::put(self::keyFor($name, $buster), $value, $ttlSeconds);

        return $value;
    }

    /** Satu-satunya tempat format kunci cache, agar `remember`, `peek`, dan `refresh` tidak menyimpang. */
    private static function keyFor(string $name, ?string $buster): string
    {
        return $buster === null ? $name : $name.':'.(int) Cache::get($buster, 0);
    }

    /**
     * Gabungkan nilai CSV (kategori/sesi) satu event ke daftar opsi yang tersimpan,
     * agar opsi filter tidak perlu memindai seluruh tabel `events`.
     */
    public static function mergeOptionTokens(string $key, ?string $csv): void
    {
        $value = trim((string) $csv);

        if ($value === '') {
            return;
        }

        $rows = Cache::get($key, []);
        $rows = is_array($rows) ? $rows : [];

        if (in_array($value, $rows, true)) {
            return;
        }

        $rows[] = $value;
        Cache::forever($key, array_values($rows));
    }

    /**
     * Naikkan buster agar seluruh kunci yang memakainya dianggap kedaluwarsa.
     */
    public static function bump(string $buster): void
    {
        Cache::forever($buster, (int) Cache::get($buster, 0) + 1);
    }
}
