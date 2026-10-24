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
        $key = $buster === null ? $name : $name.':'.(int) Cache::get($buster, 0);

        /** @var array<TValue> $cached */
        $cached = Cache::remember($key, $ttlSeconds, $resolver);

        return $cached;
    }

    /**
     * Naikkan buster agar seluruh kunci yang memakainya dianggap kedaluwarsa.
     */
    public static function bump(string $buster): void
    {
        Cache::forever($buster, (int) Cache::get($buster, 0) + 1);
    }
}
