<?php

namespace App\Services\Event;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;

/**
 * Daftar acara publik `/events`: cursor untuk daftar biasa, FULLTEXT ber-cache
 * untuk pencarian. Semua angka (TTL, lock, batas token) berasal dari config/events.php.
 */
class PublicEventSearch
{
    /** Jumlah acara per halaman (daftar maupun hasil pencarian). */
    public const PER_PAGE = 10;

    /** Panjang minimal token; di bawah ini FULLTEXT tidak mengindeks (innodb_ft_min_token_size). */
    public const TOKEN_MIN_LENGTH = 3;

    /** Maksimal token yang dikirim ke FULLTEXT dalam satu pencarian. */
    public const MAX_TOKENS = 5;

    /** Batas halaman hasil pencarian; daftar tanpa kata kunci tidak dibatasi. */
    public const MAX_PAGE = 20;

    /** Kata umum bahasa Indonesia yang tidak membantu pencarian judul acara. */
    private const STOPWORDS = [
        'di', 'ke', 'dari', 'dan', 'yang', 'untuk', 'dengan', 'pada', 'atau', 'ini', 'itu',
    ];

    /**
     * Normalisasi kata kunci jadi query FULLTEXT boolean-mode (`+kata*`):
     * hanya huruf/angka (operator boolean dibuang), token pendek dan stopword
     * dibuang, maksimal 5 token, huruf kecil agar key cache stabil. Null bila kosong.
     */
    public static function terms(?string $search): ?string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', trim((string) $search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = array_values(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) >= self::TOKEN_MIN_LENGTH
                && ! in_array(mb_strtolower($token), self::STOPWORDS, true),
        ));

        $tokens = array_slice($tokens, 0, self::MAX_TOKENS);

        if ($tokens === []) {
            return null;
        }

        return implode(' ', array_map(
            static fn (string $token): string => '+'.mb_strtolower($token).'*',
            $tokens,
        ));
    }

    /**
     * Halaman daftar acara publik. Tanpa kata kunci memakai cursor (tanpa OFFSET),
     * dengan kata kunci memakai cache + lock + batas waktu MySQL.
     * Mengembalikan null bila kata kunci terlalu umum (timeout / lock sibuk).
     */
    public function page(?string $search): Paginator|CursorPaginator|null
    {
        $query = Event::query()
            ->where('status', EventStatus::Published)
            ->forListPage();

        $terms = self::terms($search);

        if ($terms === null) {
            return $query->orderByDesc('start_date')
                ->orderByDesc('id')
                ->cursorPaginate(self::PER_PAGE)
                ->withQueryString();
        }

        $this->applyMatch($query, $terms, (string) $search);

        return $this->cachedPage($query, $terms);
    }

    /**
     * Key cache payload halaman pencarian; dipakai juga oleh test dan invalidasi manual.
     */
    public function cacheKey(string $terms, int $page): string
    {
        return 'event-search:'.sha1($terms).':'.$page;
    }

    /**
     * Key lock per halaman pencarian, mencegah query berat dijalankan paralel.
     */
    public function lockKey(string $terms, int $page): string
    {
        return 'event-search:lock:'.sha1($terms).':'.$page;
    }

    /**
     * Baca payload dari cache; bila kosong ambil lock, cek ulang, lalu jalankan query.
     * Semua jalur mengembalikan Paginator yang dibangun dari payload milik request ini.
     */
    private function cachedPage(Builder $query, string $terms): ?Paginator
    {
        $page = (int) Paginator::resolveCurrentPage();
        $key = $this->cacheKey($terms, $page);

        if (Cache::has($key)) {
            return $this->payloadToPaginator((array) Cache::get($key), $page);
        }

        try {
            return Cache::lock($this->lockKey($terms, $page), $this->configInt('lock_ttl_seconds', 10))
                ->block($this->configInt('lock_wait_seconds', 3), function () use ($query, $key, $page): ?Paginator {
                    // Double-check: request paralel mungkin sudah mengisi cache saat kita menunggu lock.
                    if (Cache::has($key)) {
                        return $this->payloadToPaginator((array) Cache::get($key), $page);
                    }

                    $payload = $this->runQuery($query, $page);

                    if ($payload === null) {
                        Cache::put($key, ['too_broad' => true], $this->configInt('too_broad_ttl_seconds', 60));

                        return null;
                    }

                    Cache::put($key, $payload, $this->configInt('cache_ttl_seconds', 60));

                    return $this->payloadToPaginator($payload, $page);
                });
        } catch (LockTimeoutException) {
            return null;
        }
    }

    /**
     * Jalankan query pencarian dengan batas eksekusi MySQL; null bila timeout (4012/3024).
     *
     * @return array{rows: list<array<string, mixed>>, hasMore: bool}|null
     */
    private function runQuery(Builder $query, int $page): ?array
    {
        $limit = $this->configInt('max_execution_ms', 1500);
        $this->applyTimeout($query, $limit);

        try {
            // perPage + 1: baris ekstra menentukan hasMore tanpa COUNT(*).
            $events = $query->forPage($page, self::PER_PAGE + 1)->get();
        } catch (QueryException $exception) {
            if ($this->isTimeout($exception)) {
                return null;
            }

            throw $exception;
        } finally {
            $this->applyTimeout($query, 0);
        }

        return [
            'rows' => $events->map(static fn (Event $event): array => $event->getAttributes())->all(),
            'hasMore' => $events->count() > self::PER_PAGE,
        ];
    }

    /**
     * Bangun Paginator baru dari payload cache + request saat ini, sehingga
     * link paginasi selalu memuat query string milik pemanggil, bukan request pertama.
     *
     * @param  array{rows?: list<array<string, mixed>>, too_broad?: bool}  $payload
     */
    private function payloadToPaginator(array $payload, int $page): ?Paginator
    {
        if (($payload['too_broad'] ?? false) === true) {
            return null;
        }

        $rows = Event::hydrate($payload['rows'] ?? []);

        return (new Paginator($rows, self::PER_PAGE, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]))->withQueryString();
    }

    /**
     * Filter pencarian. MySQL: index FULLTEXT dengan istilah literal (bukan binding)
     * dan urut relevansi saja; driver lain (SQLite di test) memakai LIKE.
     */
    private function applyMatch(Builder $query, string $terms, string $rawSearch): void
    {
        if ($query->getConnection()->getDriverName() !== 'mysql') {
            $query->where(function (Builder $inner) use ($rawSearch): void {
                $inner->where('title', 'like', '%'.$rawSearch.'%')
                    ->orWhere('location', 'like', '%'.$rawSearch.'%');
            });

            return;
        }

        // 1) Literal, bukan placeholder: placeholder mematikan pemakaian index FULLTEXT (5,8 s vs 1,1 s).
        // 2) ORDER BY hanya `relevance`: tie-breaker `id` memicu filesort 1 juta baris (4,8 s).
        // 3) Boolean mode mencocokkan AWALAN tiap kata, bukan substring di tengah kata.
        $quoted = $query->getConnection()->getPdo()->quote($terms);

        $query->selectRaw('MATCH(title, location) AGAINST ('.$quoted.' IN BOOLEAN MODE) AS relevance')
            ->whereRaw('MATCH(title, location) AGAINST ('.$quoted.' IN BOOLEAN MODE)')
            ->orderByDesc('relevance');
    }

    /**
     * Batasi eksekusi SELECT pada sesi MySQL (0 = tanpa batas). Reset dilakukan di finally.
     */
    private function applyTimeout(Builder $query, int $milliseconds): void
    {
        if ($query->getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $query->getConnection()->statement('SET SESSION max_execution_time = '.max(0, $milliseconds));
    }

    /**
     * MySQL 3024 = statement dibatalkan karena MAX_EXECUTION_TIME.
     */
    private function isTimeout(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 3024
            || str_contains($exception->getMessage(), 'maximum statement execution time exceeded');
    }

    /**
     * Baca angka konfigurasi pencarian dengan fallback konstanta lokal.
     */
    private function configInt(string $key, int $default): int
    {
        return (int) config('events.search.'.$key, $default);
    }
}
