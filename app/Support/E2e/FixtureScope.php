<?php

namespace App\Support\E2e;

use Illuminate\Support\Facades\DB;

/** Satu-satunya pembaca/pemilik baris fixture e2e. */
final class FixtureScope
{
    public const DELETE_ORDER = [
        'email_logs',
        'email_dataset_recipients',
        'email_datasets',
        'email_broadcast_recipients',
        'email_broadcast_attachments',
        'email_broadcasts',
        'recruitment_evaluations',
        'recruitment_attendances',
        'recruitment_interviews',
        'recruitment_final_decisions',
        'recruitment_feedbacks',
        'recruitment_activity_logs',
        'recruitment_screenings',
        'recruitment_correction_requests',
        'recruitment_documents',
        'recruitment_applications',
        'recruitment_periods',
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

    /** Bentuk scope dari marker kanonik. */
    public static function forMarker(string $marker): self
    {
        return new self(FixtureIdentity::fromMarker($marker));
    }

    /**
     *
     * ID baris milik marker ini, dikelompokkan per tabel (induk lebih dulu).
     *
     * @return array<string, list<string>>
     *
     */
    public function ids(): array
    {
        return $this->resolve(
            email: $this->identity->email(),
            slug: $this->identity->slug(),
            markerLike: '% ['.$this->identity->marker.']',
        );
    }

    /**
     *
     * Jumlah baris per tabel milik marker ini.
     *
     * @return array<string, int>
     *
     */
    public function counts(): array
    {
        return array_map(
            static fn (array $ids): int => count($ids),
            $this->ids(),
        );
    }

    /**
     *
     * Hapus persis baris milik marker ini dalam satu transaksi; idempoten.
     *
     * @return array<string, int>
     *
     */
    public function delete(): array
    {
        return $this->deleteIds($this->ids());
    }

    /**
     *
     * Hapus seluruh baris satu run (dipakai hanya global teardown dan sweep).
     * Predikat awal memakai prefix marker run, bukan nama tabel sembarang.
     *
     * @return array<string, int>
     *
     */
    public static function deleteRun(string $markerPrefix, string $slugPrefix): array
    {
        $scope = new self(self::prefixIdentity($markerPrefix, $slugPrefix));

        $ids = $scope->resolve(
            email: $markerPrefix,
            slug: $slugPrefix,
            markerLike: '% ['.$markerPrefix.'%',
            isPrefix: true,
        );

        // Audit sebelum delete: baris tanpa marker run ini tetap dihapus (perilaku lama), tapi dicatat.
        self::auditCrossRun($markerPrefix, $slugPrefix, $ids);

        return $scope->deleteIds($ids);
    }

    /**
     *
     * Catat baris yang hanya masuk lewat klausa induk sehingga marker miliknya bukan milik run ini.
     *
     * @param  array<string, list<string>>  $ids
     *
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
     *
     * ID baris yang kolom marker miliknya sendiri berada di bawah prefix run.
     *
     * @return array<string, list<string>>
     *
     */
    private static function ownMarkerIds(string $markerPrefix, string $slugPrefix): array
    {
        $suffixLike = '% ['.$markerPrefix.'%';
        $own = [
            'users' => self::stringIds(DB::table('users')->where('email', 'like', $markerPrefix.'%')->pluck('id')),
            'events' => self::stringIds(DB::table('events')->where('slug', 'like', $slugPrefix.'%')->pluck('id')),
            'forms' => [],
            'form_fields' => [],
            'form_answers' => [],
            'event_attendances' => [],
            'email_logs' => [],
            'recruitment_periods' => self::stringIds(
                DB::table('recruitment_periods')->where('name', 'like', $suffixLike)->pluck('id'),
            ),
            'email_broadcasts' => self::stringIds(
                DB::table('email_broadcasts')->where('name', 'like', $suffixLike)->pluck('id'),
            ),
            'email_datasets' => self::stringIds(
                DB::table('email_datasets')->where('name', 'like', $suffixLike)->pluck('id'),
            ),
        ];

        // Form/field diturunkan dari event/form milik run; LIKE hanya fallback saat induknya
        // sudah tidak ada, supaya tabel `forms` (1 juta baris) tidak dipindai penuh.
        $own['forms'] = self::resolveFormIds($own['events'], $own['users'], $suffixLike);
        $own['form_fields'] = self::resolveFieldIds($own['forms'], $suffixLike);

        // Answer/attendance tidak punya kolom marker; kepemilikan diturunkan dari induk fixture,
        // memakai predikat yang sama dengan resolve() agar audit tidak menuduh baris sendiri.
        $own['form_answers'] = self::stringIds(
            DB::table('form_answers')
                ->where(function ($query) use ($own): void {
                    $query->whereIn('form_id', $own['forms'])
                        ->orWhereIn('user_id', $own['users']);
                })
                ->pluck('id'),
        );
        $own['event_attendances'] = self::stringIds(
            DB::table('event_attendances')
                ->where(function ($query) use ($own): void {
                    $query->whereIn('event_id', $own['events'])
                        ->orWhereIn('form_answer_id', $own['form_answers'])
                        ->orWhereIn('scanned_by_user_id', $own['users']);
                })
                ->pluck('id'),
        );

        $own['recruitment_applications'] = self::childIds(
            'recruitment_applications',
            'recruitment_period_id',
            $own['recruitment_periods'],
        );

        foreach ([
            'recruitment_documents',
            'recruitment_screenings',
            'recruitment_correction_requests',
            'recruitment_interviews',
            'recruitment_attendances',
            'recruitment_evaluations',
            'recruitment_final_decisions',
            'recruitment_feedbacks',
            'recruitment_activity_logs',
        ] as $table) {
            $own[$table] = self::childIds($table, 'recruitment_application_id', $own['recruitment_applications']);
        }

        // Activity log boleh menempel ke periode tanpa aplikasi; periode marker tetap milik run ini.
        $own['recruitment_activity_logs'] = array_merge(
            $own['recruitment_activity_logs'],
            self::childIds('recruitment_activity_logs', 'recruitment_period_id', $own['recruitment_periods']),
        );

        $own['email_broadcast_recipients'] = self::childIds(
            'email_broadcast_recipients',
            'broadcast_id',
            $own['email_broadcasts'],
        );
        $own['email_broadcast_attachments'] = self::childIds(
            'email_broadcast_attachments',
            'broadcast_id',
            $own['email_broadcasts'],
        );
        $own['email_dataset_recipients'] = self::childIds(
            'email_dataset_recipients',
            'dataset_id',
            $own['email_datasets'],
        );

        $eventIds = $own['events'];
        $userIds = $own['users'];
        $answerIds = $own['form_answers'];
        $applicationIds = $own['recruitment_applications'];

        $own['email_logs'] = self::stringIds(
            DB::table('email_logs')
                ->where(function ($query) use ($eventIds, $userIds, $answerIds, $applicationIds): void {
                    $query->whereIn('event_id', $eventIds)
                        ->orWhereIn('form_answer_id', $answerIds)
                        ->orWhereIn('user_id', $userIds)
                        ->orWhereIn('recruitment_application_id', $applicationIds);
                })
                ->pluck('id'),
        );

        return $own;
    }

    /**
     *
     * Normalisasi koleksi id menjadi list<string> agar perbandingan antar tabel konsisten.
     *
     * @param  iterable<int, mixed>  $ids
     * @return list<string>
     *
     */
    private static function stringIds(iterable $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            $normalized[] = (string) $id;
        }

        return $normalized;
    }

    /**
     *
     * Id anak yang menempel pada daftar id induk; query dilewati bila induk kosong.
     *
     * @param  list<string>  $parentIds
     * @return list<string>
     *
     */
    private static function childIds(string $table, string $column, array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        return self::stringIds(DB::table($table)->whereIn($column, $parentIds)->pluck('id'));
    }

    /**
     *
     * ID form milik marker: event marker ATAU dibuat user marker, lalu fallback LIKE.
     * @param  list<string>  $eventIds
     * @param  list<string>  $userIds
     * @return list<string>
     *
     */
    private static function resolveFormIds(array $eventIds, array $userIds, string $markerLike): array
    {
        if ($eventIds === [] && $userIds === []) {
            return self::stringIds(
                DB::table('forms')->select('id')->where('title', 'like', $markerLike)->pluck('id'),
            );
        }

        return self::stringIds(
            DB::table('forms')
                ->select('id')
                ->where(function ($query) use ($eventIds, $userIds): void {
                    $query->whereIn('event_id', $eventIds)->orWhereIn('created_by', $userIds);
                })
                ->pluck('id'),
        );
    }

    /**
     *
     * ID field milik form marker; LIKE hanya dipakai saat form-nya sudah tidak ada.
     * @param  list<string>  $formIds
     * @return list<string>
     *
     */
    private static function resolveFieldIds(array $formIds, string $markerLike): array
    {
        if ($formIds === []) {
            return self::stringIds(
                DB::table('form_fields')->select('id')->where('label', 'like', $markerLike)->pluck('id'),
            );
        }

        return self::stringIds(
            DB::table('form_fields')->select('id')->whereIn('form_id', $formIds)->pluck('id'),
        );
    }

    /**
     *
     * Jumlah baris satu run tanpa menghapus; dipakai `check:row-ownership`.
     *
     * @return array<string, int>
     *
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
                isPrefix: true,
            ),
        );
    }

    /** Identitas dummy valid untuk operasi berbasis prefix (marker run, bukan marker owner). */
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
     *
     * Resolve id per tabel dari predikat yang diberikan.
     *
     * @return array<string, list<string>>
     *
     */
    private function resolve(
        string $email,
        string $slug,
        string $markerLike,
        bool $isPrefix = false,
    ): array {
        $operator = $isPrefix ? 'like' : '=';
        $emailMatch = $isPrefix ? $email.'%' : $email;
        $slugMatch = $isPrefix ? $slug.'%' : $slug;

        $userIds = DB::table('users')->select('id')->where('email', $operator, $emailMatch)->pluck('id')->all();

        $eventIds = DB::table('events')
            ->select('id')
            ->where('slug', $operator, $slugMatch)
            ->pluck('id')
            ->all();

        $formIds = self::resolveFormIds($eventIds, $userIds, $markerLike);
        $fieldIds = self::resolveFieldIds($formIds, $markerLike);

        $answerIds = DB::table('form_answers')
            ->select('id')
            ->where(function ($query) use ($formIds, $userIds): void {
                $query->whereIn('form_id', $formIds)->orWhereIn('user_id', $userIds);
            })
            ->pluck('id')
            ->all();

        $attendanceIds = DB::table('event_attendances')
            ->select('id')
            ->where(function ($query) use ($eventIds, $answerIds, $userIds): void {
                $query->whereIn('event_id', $eventIds)
                    ->orWhereIn('form_answer_id', $answerIds)
                    ->orWhereIn('scanned_by_user_id', $userIds);
            })
            ->pluck('id')
            ->all();

        $periodIds = self::stringIds(
            DB::table('recruitment_periods')->select('id')->where('name', 'like', $markerLike)->pluck('id'),
        );
        $applicationIds = self::childIds('recruitment_applications', 'recruitment_period_id', $periodIds);
        $recruitmentChildIds = [];

        foreach ([
            'recruitment_documents',
            'recruitment_screenings',
            'recruitment_correction_requests',
            'recruitment_interviews',
            'recruitment_attendances',
            'recruitment_evaluations',
            'recruitment_final_decisions',
            'recruitment_feedbacks',
        ] as $table) {
            $recruitmentChildIds[$table] = self::childIds($table, 'recruitment_application_id', $applicationIds);
        }

        $activityIds = array_merge(
            self::childIds('recruitment_activity_logs', 'recruitment_application_id', $applicationIds),
            self::childIds('recruitment_activity_logs', 'recruitment_period_id', $periodIds),
        );

        $broadcastIds = self::stringIds(
            DB::table('email_broadcasts')->select('id')->where('name', 'like', $markerLike)->pluck('id'),
        );
        $broadcastRecipientIds = self::childIds('email_broadcast_recipients', 'broadcast_id', $broadcastIds);
        $broadcastAttachmentIds = self::childIds('email_broadcast_attachments', 'broadcast_id', $broadcastIds);

        $datasetIds = self::stringIds(
            DB::table('email_datasets')->select('id')->where('name', 'like', $markerLike)->pluck('id'),
        );
        $datasetRecipientIds = self::childIds('email_dataset_recipients', 'dataset_id', $datasetIds);

        $emailLogIds = DB::table('email_logs')
            ->select('id')
            ->where(function ($query) use ($eventIds, $answerIds, $userIds, $applicationIds): void {
                $query->whereIn('event_id', $eventIds)
                    ->orWhereIn('form_answer_id', $answerIds)
                    ->orWhereIn('user_id', $userIds)
                    ->orWhereIn('recruitment_application_id', $applicationIds);
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
            'email_logs' => $emailLogIds,
            'recruitment_periods' => $periodIds,
            'recruitment_applications' => $applicationIds,
            'recruitment_documents' => $recruitmentChildIds['recruitment_documents'],
            'recruitment_screenings' => $recruitmentChildIds['recruitment_screenings'],
            'recruitment_correction_requests' => $recruitmentChildIds['recruitment_correction_requests'],
            'recruitment_interviews' => $recruitmentChildIds['recruitment_interviews'],
            'recruitment_attendances' => $recruitmentChildIds['recruitment_attendances'],
            'recruitment_evaluations' => $recruitmentChildIds['recruitment_evaluations'],
            'recruitment_final_decisions' => $recruitmentChildIds['recruitment_final_decisions'],
            'recruitment_feedbacks' => $recruitmentChildIds['recruitment_feedbacks'],
            'recruitment_activity_logs' => $activityIds,
            'email_broadcasts' => $broadcastIds,
            'email_broadcast_recipients' => $broadcastRecipientIds,
            'email_broadcast_attachments' => $broadcastAttachmentIds,
            'email_datasets' => $datasetIds,
            'email_dataset_recipients' => $datasetRecipientIds,
        ];
    }

    /**
     *
     * Hapus id per tabel dalam satu transaksi, anak lebih dulu, per 500 baris.
     *
     * @param  array<string, list<string>>  $ids
     * @return array<string, int>
     *
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
}
