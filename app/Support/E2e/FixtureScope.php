<?php

namespace App\Support\E2e;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu-satunya pembaca/pemilik baris fixture e2e.
 *
 * Semua penghapusan memakai id hasil resolusi (predikat exact), bukan wildcard pada DELETE.
 * Urutan anak→induk: attendance, answer, field, form, event, user.
 */
final class FixtureScope
{
    public const DELETE_ORDER = [
        'event_attendances',
        'form_answers',
        'form_fields',
        'forms',
        'events',
        'users',
    ];

    private const CHUNK_SIZE = 500;

    public function __construct(private readonly FixtureIdentity $identity)
    {
    }

    /**
     * Bentuk scope dari marker kanonik.
     */
    public static function forMarker(string $marker): self
    {
        return new self(FixtureIdentity::fromMarker($marker));
    }

    /**
     * Predikat kolom marker per tabel; `null` untuk tabel yang dimiliki lewat induk.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    public function columnPredicate(string $table): ?array
    {
        return match ($table) {
            'users' => ['email', '=', $this->identity->email()],
            'events' => ['slug', '=', $this->identity->slug()],
            'forms' => ['title', 'suffix', $this->identity->marker],
            'form_fields' => ['label', 'suffix', $this->identity->marker],
            'form_answers', 'event_attendances' => $this->hasMarkerColumn($table)
                ? ['meta->marker', '=', $this->identity->marker]
                : null,
            default => null,
        };
    }

    /**
     * ID baris milik marker ini, dikelompokkan per tabel (induk lebih dulu).
     *
     * @return array<string, list<string>>
     */
    public function ids(): array
    {
        return $this->resolve(
            email: $this->identity->email(),
            slug: $this->identity->slug(),
            markerLike: '% ['.$this->identity->marker.']',
            metaMarker: $this->identity->marker,
        );
    }

    /**
     * Jumlah baris per tabel milik marker ini.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return array_map(
            static fn (array $ids): int => count($ids),
            $this->ids(),
        );
    }

    /**
     * Hapus persis baris milik marker ini dalam satu transaksi; idempoten.
     *
     * @return array<string, int>
     */
    public function delete(): array
    {
        return $this->deleteIds($this->ids());
    }

    /**
     * Hapus seluruh baris satu run (dipakai hanya global teardown dan sweep).
     * Predikat awal memakai prefix marker run, bukan nama tabel sembarang.
     *
     * @return array<string, int>
     */
    public static function deleteRun(string $markerPrefix, string $slugPrefix): array
    {
        $scope = new self(self::prefixIdentity($markerPrefix, $slugPrefix));

        $ids = $scope->resolve(
            email: $markerPrefix,
            slug: $slugPrefix,
            markerLike: '% ['.$markerPrefix.'%',
            metaMarker: $markerPrefix,
            isPrefix: true,
        );

        // Audit sebelum delete: baris tanpa marker run ini tetap dihapus (perilaku lama), tapi dicatat.
        self::auditCrossRun($markerPrefix, $slugPrefix, $ids);

        return $scope->deleteIds($ids);
    }

    /**
     * Catat baris yang hanya masuk lewat klausa induk sehingga marker miliknya bukan milik run ini.
     *
     * @param  array<string, list<string>>  $ids
     */
    private static function auditCrossRun(string $markerPrefix, string $slugPrefix, array $ids): void
    {
        $ownIds = self::ownMarkerIds($markerPrefix, $slugPrefix);
        $violations = [];

        foreach (self::DELETE_ORDER as $table) {
            $owned = array_flip(array_map(static fn (mixed $id): string => (string) $id, $ownIds[$table] ?? []));
            $foreign = 0;

            foreach ($ids[$table] ?? [] as $id) {
                if (! isset($owned[(string) $id])) {
                    $foreign++;
                }
            }

            if ($foreign > 0) {
                $violations[$table] = $foreign;
            }
        }

        if ($violations === [] || preg_match('/^e2e\+([0-9A-Za-z-]{1,22})\+$/', $markerPrefix, $matches) !== 1) {
            return;
        }

        (new RunLedger())->recordTeardown($matches[1], $violations);
    }

    /**
     * ID baris yang kolom marker miliknya sendiri berada di bawah prefix run.
     *
     * @return array<string, list<string>>
     */
    private static function ownMarkerIds(string $markerPrefix, string $slugPrefix): array
    {
        $suffixLike = '% ['.$markerPrefix.'%';
        $own = [
            'users' => DB::table('users')->where('email', 'like', $markerPrefix.'%')->pluck('id')->all(),
            'events' => DB::table('events')->where('slug', 'like', $slugPrefix.'%')->pluck('id')->all(),
            'forms' => DB::table('forms')->where('title', 'like', $suffixLike)->pluck('id')->all(),
            'form_fields' => DB::table('form_fields')->where('label', 'like', $suffixLike)->pluck('id')->all(),
            'form_answers' => [],
            'event_attendances' => [],
        ];

        $expression = DB::connection()->getDriverName() === 'pgsql'
            ? "meta->>'marker'"
            : "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.marker'))";

        foreach (['form_answers', 'event_attendances'] as $table) {
            if (! Schema::hasColumn($table, 'meta')) {
                continue;
            }

            $own[$table] = DB::table($table)
                ->whereRaw("{$table}.".$expression.' like ?', [$markerPrefix.'%'])
                ->pluck('id')
                ->all();
        }

        return $own;
    }

    /**
     * Jumlah baris satu run tanpa menghapus; dipakai `check:row-ownership`.
     *
     * @return array<string, int>
     */
    public static function countRun(string $markerPrefix, string $slugPrefix): array
    {
        $scope = new self(self::prefixIdentity($markerPrefix, $slugPrefix));

        return array_map(
            static fn (array $ids): int => count($ids),
            $scope->resolve(
                email: $markerPrefix,
                slug: $slugPrefix,
                markerLike: '% ['.$markerPrefix.'%',
                metaMarker: $markerPrefix,
                isPrefix: true,
            ),
        );
    }

    /**
     * Identitas dummy valid untuk operasi berbasis prefix (marker run, bukan marker owner).
     */
    private static function prefixIdentity(string $markerPrefix, string $slugPrefix): FixtureIdentity
    {
        if (preg_match('/^e2e\+[0-9A-Za-z-]{1,22}\+$/', $markerPrefix) !== 1) {
            throw new \InvalidArgumentException('marker prefix must look like e2e+<runId>+.');
        }

        if (preg_match('/^e2e-[0-9a-z-]{1,22}-$/', $slugPrefix) !== 1) {
            throw new \InvalidArgumentException('slug prefix must look like e2e-<runid>-.');
        }

        // Prefix yang tidak sepasang akan menghapus event run lain; turunkan ulang, jangan percaya input.
        if ($slugPrefix !== strtolower(str_replace('+', '-', $markerPrefix))) {
            throw new \InvalidArgumentException('slug prefix must equal markerToSlug(marker prefix).');
        }

        return FixtureIdentity::fromMarker($markerPrefix.'o'.str_repeat('0', 12));
    }

    /**
     * Resolve id per tabel dari predikat yang diberikan.
     *
     * @return array<string, list<string>>
     */
    private function resolve(
        string $email,
        string $slug,
        string $markerLike,
        string $metaMarker,
        bool $isPrefix = false,
    ): array {
        $operator = $isPrefix ? 'like' : '=';
        $emailMatch = $isPrefix ? $email.'%' : $email;
        $slugMatch = $isPrefix ? $slug.'%' : $slug;
        $metaValue = $isPrefix ? $metaMarker.'%' : $metaMarker;

        $userIdQuery = DB::table('users')->select('id')->where('email', $operator, $emailMatch);
        $userIds = $userIdQuery->pluck('id')->all();

        $eventIds = DB::table('events')
            ->select('id')
            ->where('slug', $operator, $slugMatch)
            ->pluck('id')
            ->all();

        $formIds = DB::table('forms')
            ->select('id')
            ->where(function ($query) use ($eventIds, $markerLike): void {
                $query->whereIn('event_id', $eventIds)
                    ->orWhere('title', 'like', $markerLike);
            })
            ->pluck('id')
            ->all();

        $fieldIds = DB::table('form_fields')
            ->select('id')
            ->where(function ($query) use ($formIds, $markerLike): void {
                $query->whereIn('form_id', $formIds)
                    ->orWhere('label', 'like', $markerLike);
            })
            ->pluck('id')
            ->all();

        $answerIds = DB::table('form_answers')
            ->select('id')
            ->where(function ($query) use ($formIds, $userIds, $metaMarker, $isPrefix): void {
                $query->whereIn('form_id', $formIds)->orWhereIn('user_id', $userIds);

                if (Schema::hasColumn('form_answers', 'meta')) {
                    $this->applyMetaMarker($query, 'form_answers', $metaMarker, $isPrefix);
                }
            })
            ->pluck('id')
            ->all();

        $attendanceIds = DB::table('event_attendances')
            ->select('id')
            ->where(function ($query) use ($eventIds, $answerIds, $userIds, $metaMarker, $isPrefix): void {
                $query->whereIn('event_id', $eventIds)
                    ->orWhereIn('form_answer_id', $answerIds)
                    ->orWhereIn('scanned_by_user_id', $userIds);

                if (Schema::hasColumn('event_attendances', 'meta')) {
                    $this->applyMetaMarker($query, 'event_attendances', $metaMarker, $isPrefix);
                }
            })
            ->pluck('id')
            ->all();

        return [
            'users' => $userIds,
            'events' => $eventIds,
            'forms' => $formIds,
            'form_fields' => $fieldIds,
            'form_answers' => $answerIds,
            'event_attendances' => $attendanceIds,
        ];
    }

    /**
     * Tambahkan predikat JSON marker untuk tabel yang punya kolom `meta`.
     */
    private function applyMetaMarker($query, string $table, string $marker, bool $isPrefix): void
    {
        $expression = DB::connection()->getDriverName() === 'pgsql'
            ? "{$table}.meta->>'marker'"
            : "JSON_UNQUOTE(JSON_EXTRACT({$table}.meta, '$.marker'))";

        $query->orWhereRaw($expression.' like ?', [$isPrefix ? $marker.'%' : $marker]);
    }

    /**
     * Hapus id per tabel dalam satu transaksi, anak lebih dulu, per 500 baris.
     *
     * @param  array<string, list<string>>  $ids
     * @return array<string, int>
     */
    private function deleteIds(array $ids): array
    {
        $deleted = [];

        DB::transaction(function () use ($ids, &$deleted): void {
            foreach (self::DELETE_ORDER as $table) {
                $deleted[$table] = 0;

                foreach (array_chunk($ids[$table] ?? [], self::CHUNK_SIZE) as $chunk) {
                    try {
                        $deleted[$table] += DB::table($table)->whereIn('id', $chunk)->delete();
                    } catch (\Throwable $exception) {
                        throw new \RuntimeException(
                            "e2e cleanup failed on table {$table} (id in resolved ids): {$exception->getMessage()}",
                            0,
                            $exception,
                        );
                    }
                }
            }
        });

        return $deleted;
    }

    /**
     * Apakah tabel punya kolom `meta` untuk menyimpan marker JSON.
     */
    private function hasMarkerColumn(string $table): bool
    {
        return Schema::hasColumn($table, 'meta');
    }
}
