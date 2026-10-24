<?php

namespace App\Services\Event;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Agregat jumlah event per token kategori di tabel `event_category_counts`, supaya chart
 * dashboard tidak pernah menjalankan `GROUP BY` pada 1 juta baris. Jalur simpan memakai
 * {@see applyDelta()}; {@see rebuild()} hanya untuk backfill/drift.
 */
final class EventCategoryCounter
{
    /** Nama tabel agregat; satu definisi agar service, observer, dan test tidak menyimpang. */
    public const TABLE = 'event_category_counts';

    /** Token terbanyak yang dibaca untuk chart; token ekor tidak pernah tampil di UI. */
    private const READ_LIMIT = 100;

    /** Ukuran batch insert rebuild agar satu statement tidak membawa ratusan placeholder. */
    private const REBUILD_CHUNK_SIZE = 500;

    /**
     * Hitung ulang seluruh agregat dari `events` (offline). Wajib setelah `stress:seed`
     * atau operasi bulk yang melewati observer; tabel dikosongkan agar tidak ada sisa token.
     *
     * @return array{events: int, tokens: int}
     */
    public function rebuild(): array
    {
        $countsByCategory = Event::query()
            ->whereNull('deleted_at')
            ->toBase()
            ->select(['category'])
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        $tallies = [];

        foreach ($countsByCategory as $category => $count) {
            foreach (Event::tokensFromCsv((string) $category) as $token) {
                $tallies[$token] = ($tallies[$token] ?? 0) + (int) $count;
            }
        }

        $rows = [];

        foreach ($tallies as $token => $total) {
            $rows[] = ['category' => $token, 'total' => $total];
        }

        DB::transaction(function () use ($rows): void {
            DB::table(self::TABLE)->delete();

            foreach (array_chunk($rows, self::REBUILD_CHUNK_SIZE) as $chunk) {
                DB::table(self::TABLE)->insert($chunk);
            }
        });

        return ['events' => (int) $countsByCategory->sum(), 'tokens' => count($rows)];
    }

    /**
     * Baca token kategori teratas dari tabel agregat (<=100 baris, bukan 1 juta baris).
     *
     * @return list<array{token: string, count: int}>
     */
    public function counts(): array
    {
        return DB::table(self::TABLE)
            ->where('total', '>', 0)
            ->select(['category', 'total'])
            ->orderByDesc('total')
            ->orderBy('category')
            ->limit(self::READ_LIMIT)
            ->get()
            ->map(static fn (object $row): array => [
                'token' => (string) $row->category,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Terapkan selisih token satu event ke agregat; delta kosong berarti tidak ada tulisan.
     * Dipakai observer pada create/update/delete/restore, bukan untuk menghitung ulang tabel.
     *
     * @param  list<string>  $oldTokens
     * @param  list<string>  $newTokens
     */
    public function applyDelta(array $oldTokens, array $newTokens): void
    {
        $delta = [];

        foreach ($oldTokens as $token) {
            $delta[$token] = ($delta[$token] ?? 0) - 1;
        }

        foreach ($newTokens as $token) {
            $delta[$token] = ($delta[$token] ?? 0) + 1;
        }

        $delta = array_filter($delta, static fn (int $amount): bool => $amount !== 0);

        if ($delta === []) {
            return;
        }

        DB::transaction(function () use ($delta): void {
            foreach ($delta as $token => $amount) {
                $this->applyTokenDelta((string) $token, $amount);
            }
        });
    }

    /** Naikkan/turunkan satu baris token; tiap statement atomik agar aman dari simpan bersamaan. */
    private function applyTokenDelta(string $token, int $amount): void
    {
        if ($amount > 0) {
            $this->incrementToken($token, $amount);

            return;
        }

        $updated = DB::table(self::TABLE)
            ->where('category', $token)
            ->where('total', '>=', -$amount)
            ->decrement('total', -$amount);

        if ($updated === 0) {
            // Baris hilang atau total lebih kecil dari pengurangan: drift, catat untuk rebuild.
            Log::warning('agregat kategori drift', ['token' => $token, 'decrement' => -$amount]);
        }
    }

    /** Tambah hitungan token; baris yang belum ada dibuat tanpa menimpa hitungan yang balapan. */
    private function incrementToken(string $token, int $amount): void
    {
        if (DB::table(self::TABLE)->where('category', $token)->increment('total', $amount) > 0) {
            return;
        }

        $inserted = DB::table(self::TABLE)->insertOrIgnore(['category' => $token, 'total' => $amount]);

        if ($inserted === 0) {
            DB::table(self::TABLE)->where('category', $token)->increment('total', $amount);
        }
    }
}
