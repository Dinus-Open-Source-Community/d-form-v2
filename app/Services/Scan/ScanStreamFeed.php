<?php

namespace App\Services\Scan;

use App\Models\EventAttendance;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentAttendance;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Services\Registration\BundleGuestDisplayNameResolver;
use App\Services\Registration\FormAnswerRecipientResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Feed gabungan event/recruitment; cursor harus melewati baris yang sudah dikonsumsi agar polling tidak macet.
 * Output sengaja memuat nama/email peserta dan status recruitment; perubahan exposure wajib mendapat persetujuan owner.
 */
final class ScanStreamFeed
{
    private const CURSOR_SEPARATOR = '|';

    private const CURSOR_PARTS = 3;

    /** Batas atas ukuran halaman; controller juga memotong, ini pertahanan berlapis karena service ini menerima nilai dari input pengguna. */
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly BundleGuestDisplayNameResolver $displayNameResolver,
        private readonly FormAnswerRecipientResolver $recipientResolver,
    ) {
    }

    /** Return rows while preserving the opaque cross-domain cursor contract.
     * @return array<int, array{id:string,ts:string,type:string,eventTitle:string,name:string,identifier:string,queueNumber:int|null,interviewStatus?:string|null}>
     */
    public function since(?string $cursor, int $limit = 50): array
    {
        return $this->page($cursor, $limit)['rows'];
    }

    /**
     *
     * Satu halaman feed beserta kursor opaque baris terakhir yang dikirim.
     * @return array{rows: list<array{id:string,ts:string,type:string,eventTitle:string,name:string,identifier:string,queueNumber:int|null,interviewStatus?:string|null}>, cursor: string|null}
     *
     */
    public function page(?string $cursor, int $limit = 50): array
    {
        $limit = min(self::MAX_LIMIT, max(1, $limit));
        $position = self::parseCursor($cursor);

        $slots = [
            ...$this->eventSlots($position, $limit),
            ...$this->recruitmentSlots($position, $limit),
        ];

        $safeEpoch = self::safeBoundaryEpoch($slots, $limit);

        $emitted = array_values(array_filter(
            $slots,
            static fn (array $slot): bool => $slot['entry'] !== null
                && ($safeEpoch === null || $slot['epoch'] <= $safeEpoch),
        ));

        usort($emitted, static function (array $left, array $right): int {
            if ($left['epoch'] !== $right['epoch']) {
                return $left['epoch'] <=> $right['epoch'];
            }

            if ($left['domainRank'] !== $right['domainRank']) {
                return $left['domainRank'] <=> $right['domainRank'];
            }

            return $left['position'] <=> $right['position'];
        });

        $page = array_slice($emitted, 0, $limit);

        return [
            'rows' => array_map(static fn (array $slot): array => $slot['entry'], $page),
            'cursor' => self::cursorForPage($slots, $page, $position, $safeEpoch) ?? $cursor,
        ];
    }

    /** Normalisasi nilai `since` dari request menjadi token kanonik, atau null bila nilai tidak dapat diparse sama sekali. */
    public static function normalizeCursor(string $value): ?string
    {
        $position = self::decodeCursor($value);

        if ($position === null) {
            return null;
        }

        return self::encodeCursor(
            $position['at']->toIso8601String(),
            $position['eventId'],
            $position['recruitmentId'],
        );
    }

    /** Keep same-timestamp rows from being skipped; rows held only by the page limit remain in the cursor boundary.
     * @param  list<array{entry: array<string, string|int|null>|null, domain: string, domainRank: int, position: int, epoch: int, ts: string, primaryKey: string}>  $slots
     * @param  list<array{entry: array<string, string|int|null>|null, domain: string, domainRank: int, position: int, epoch: int, ts: string, primaryKey: string}>  $page
     * @param  array{at: Carbon, eventId: string|null, recruitmentId: string|null}|null  $incoming
     */
    private static function cursorForPage(array $slots, array $page, ?array $incoming, ?int $safeEpoch): ?string
    {
        $boundary = self::pageBoundary($slots, $page, $safeEpoch);

        if ($boundary === null) {
            return null;
        }

        [$boundaryEpoch, $boundaryTs] = $boundary;

        $sameCursorSecond = $incoming !== null && $incoming['at']->getTimestamp() === $boundaryEpoch;

        $primaryKeys = [
            'event' => $sameCursorSecond ? $incoming['eventId'] : null,
            'recruitment' => $sameCursorSecond ? $incoming['recruitmentId'] : null,
        ];

        $pageKeys = [];
        foreach ($page as $slot) {
            $pageKeys[$slot['domain'].self::CURSOR_SEPARATOR.$slot['primaryKey']] = true;
        }

        foreach (['event', 'recruitment'] as $domain) {
            foreach ($slots as $slot) {
                if ($slot['domain'] !== $domain) {
                    continue;
                }

                if ($slot['epoch'] < $boundaryEpoch) {
                    continue;
                }

                if ($slot['epoch'] > $boundaryEpoch) {
                    break;
                }

                // Baris emittable yang tertahan `limit` belum boleh dilewati.
                if ($slot['entry'] !== null && ! isset($pageKeys[$slot['domain'].self::CURSOR_SEPARATOR.$slot['primaryKey']])) {
                    break;
                }

                $primaryKeys[$domain] = $slot['primaryKey'];
            }
        }

        return self::encodeCursor($boundaryTs, $primaryKeys['event'], $primaryKeys['recruitment']);
    }

    /** Bound the cursor by the fullest domain so a domain with more rows is never skipped.
     * @param  list<array{entry: array<string, string|int|null>|null, domain: string, domainRank: int, position: int, epoch: int, ts: string, primaryKey: string}>  $slots
     */
    private static function safeBoundaryEpoch(array $slots, int $limit): ?int
    {
        $safe = null;
        $counts = ['event' => 0, 'recruitment' => 0];
        $lastEpochs = ['event' => null, 'recruitment' => null];

        foreach ($slots as $slot) {
            $counts[$slot['domain']]++;
            $lastEpochs[$slot['domain']] = $slot['epoch'];
        }

        foreach ($counts as $domain => $count) {
            if ($count < $limit) {
                continue;
            }

            $lastEpoch = $lastEpochs[$domain];

            if ($lastEpoch === null) {
                continue;
            }

            if ($safe === null || $lastEpoch < $safe) {
                $safe = $lastEpoch;
            }
        }

        return $safe;
    }

    /** Advance past consumed rows, including unmappable rows, without passing rows held by the limit.
     * @param  list<array{entry: array<string, string|int|null>|null, domain: string, domainRank: int, position: int, epoch: int, ts: string, primaryKey: string}>  $slots
     * @param  list<array{entry: array<string, string|int|null>|null, domain: string, domainRank: int, position: int, epoch: int, ts: string, primaryKey: string}>  $page
     * @return array{0: int, 1: string}|null
     */
    private static function pageBoundary(array $slots, array $page, ?int $safeEpoch): ?array
    {
        if ($page !== []) {
            $last = $page[array_key_last($page)];

            return [$last['epoch'], $last['ts']];
        }

        $boundary = null;

        foreach ($slots as $slot) {
            if ($safeEpoch !== null) {
                if ($slot['epoch'] !== $safeEpoch) {
                    continue;
                }

                return [$safeEpoch, $slot['ts']];
            }

            if ($boundary === null || $slot['epoch'] > $boundary[0]) {
                $boundary = [$slot['epoch'], $slot['ts']];
            }
        }

        return $boundary;
    }

    private static function encodeCursor(string $ts, ?string $eventId, ?string $recruitmentId): string
    {
        $payload = implode(self::CURSOR_SEPARATOR, [$ts, $eventId ?? '', $recruitmentId ?? '']);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     *
     * @return array{at: Carbon, eventId: string|null, recruitmentId: string|null}|null
     *
     */
    private static function parseCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        return self::decodeCursor($cursor);
    }

    /**
     *
     * @return array{at: Carbon, eventId: string|null, recruitmentId: string|null}|null
     *
     */
    private static function decodeCursor(string $cursor): ?array
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($decoded !== false && str_contains($decoded, self::CURSOR_SEPARATOR)) {
            $parts = explode(self::CURSOR_SEPARATOR, $decoded);

            if (count($parts) !== self::CURSOR_PARTS) {
                return null;
            }

            $at = self::parseTimestamp($parts[0]);

            if ($at === null) {
                return null;
            }

            return [
                'at' => $at,
                'eventId' => $parts[1] !== '' ? $parts[1] : null,
                'recruitmentId' => $parts[2] !== '' ? $parts[2] : null,
            ];
        }

        return self::parseLegacyTimestamp($cursor);
    }

    /**
     *
     * @return array{at: Carbon, eventId: null, recruitmentId: null}|null
     *
     */
    private static function parseLegacyTimestamp(string $cursor): ?array
    {
        $at = self::parseTimestamp($cursor);

        return $at === null ? null : ['at' => $at, 'eventId' => null, 'recruitmentId' => null];
    }

    /** Parse timestamp absolut saja. */
    private static function parseTimestamp(string $value): ?Carbon
    {
        $absolute = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/';

        if (preg_match($absolute, $value) !== 1) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     *
     * Baris attendance event pada jendela poll, termasuk baris yang tidak bisa
     * dipetakan (`entry` null) supaya progres kursor tetap terlihat.
     *
     * @param  array{at: Carbon, eventId: string|null, recruitmentId: string|null}|null  $position
     * @return list<array{entry: array<string, string|int|null>|null, domain: string, domainRank: int, position: int, epoch: int, ts: string, primaryKey: string}>
     *
     */
    private function eventSlots(?array $position, int $limit): array
    {
        $query = EventAttendance::query()
            ->with(['formAnswer.form.event', 'formAnswer.user'])
            ->orderBy('scanned_at')
            ->orderBy('id')
            ->limit($limit);

        if ($position !== null) {
            $query->where(static function ($query) use ($position): void {
                $query->where('scanned_at', '>', $position['at'])
                    ->orWhere(static function ($query) use ($position): void {
                        $query->where('scanned_at', '=', $position['at']);

                        if ($position['eventId'] !== null) {
                            $query->where('id', '>', $position['eventId']);
                        }
                    });
            });
        }

        $slots = [];

        foreach ($query->get() as $index => $attendance) {
            $answer = $attendance->formAnswer;
            $ts = $attendance->scanned_at->toIso8601String();
            $entry = null;

            if ($answer !== null && $answer->form !== null && $answer->form->event !== null) {
                $entry = [
                    'id' => 'evt:'.$attendance->form_answer_id.':'.$ts,
                    'ts' => $ts,
                    'type' => 'event',
                    'eventTitle' => $answer->form->event->title,
                    'name' => $this->displayNameResolver->resolve($answer),
                    'identifier' => $this->recipientResolver->email($answer) ?? '',
                    'queueNumber' => null,
                ];
            }

            $slots[] = [
                'entry' => $entry,
                'domain' => 'event',
                'domainRank' => 0,
                'position' => $index,
                'epoch' => $attendance->scanned_at->getTimestamp(),
                'ts' => $ts,
                'primaryKey' => $attendance->id,
            ];
        }

        return $slots;
    }

    /**
     *
     * Baris check-in recruitment pada jendela poll.
     * @param  array{at: Carbon, eventId: string|null, recruitmentId: string|null}|null  $position
     * @return list<array{entry: array<string, string|int|null>|null, domain: string, domainRank: int, position: int, epoch: int, ts: string, primaryKey: string}>
     *
     */
    private function recruitmentSlots(?array $position, int $limit): array
    {
        $query = RecruitmentAttendance::query()
            ->orderBy('checked_in_at')
            ->orderBy('id')
            ->limit($limit);

        if ($position !== null) {
            $query->where(static function ($query) use ($position): void {
                $query->where('checked_in_at', '>', $position['at'])
                    ->orWhere(static function ($query) use ($position): void {
                        $query->where('checked_in_at', '=', $position['at']);

                        if ($position['recruitmentId'] !== null) {
                            $query->where('id', '>', $position['recruitmentId']);
                        }
                    });
            });
        }

        $recAttendances = $query->get();

        $applications = $this->applicationsFor(
            $recAttendances->pluck('recruitment_application_id')->unique()->values()->all()
        );
        $sessions = $this->sessionsFor(
            $recAttendances->pluck('recruitment_interview_session_id')->unique()->values()->all()
        );

        $slots = [];

        foreach ($recAttendances as $index => $attendance) {
            $application = $applications->get($attendance->recruitment_application_id);
            $ts = $attendance->checked_in_at->toIso8601String();
            $entry = null;

            if ($application !== null) {
                $session = $sessions->get($attendance->recruitment_interview_session_id);
                $entry = [
                    'id' => 'rec:'.$attendance->id,
                    'ts' => $ts,
                    'type' => 'recruitment',
                    'eventTitle' => 'Oprec · '.($session?->division?->name ?? '').' · '.($session?->session_date ?? ''),
                    'name' => $application->full_name,
                    'identifier' => $application->registration_number,
                    'queueNumber' => null,
                    'interviewStatus' => $application->primaryInterview?->status->value,
                ];
            }

            $slots[] = [
                'entry' => $entry,
                'domain' => 'recruitment',
                'domainRank' => 1,
                'position' => $index,
                'epoch' => $attendance->checked_in_at->getTimestamp(),
                'ts' => $ts,
                'primaryKey' => $attendance->id,
            ];
        }

        return $slots;
    }

    /**
     *
     * @param  list<string>  $ids
     * @return Collection<string, RecruitmentApplication>
     *
     */
    private function applicationsFor(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection();
        }

        return RecruitmentApplication::query()
            ->with('primaryInterview')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     *
     * @param  list<string>  $ids
     * @return Collection<string, RecruitmentInterviewSession>
     *
     */
    private function sessionsFor(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection();
        }

        return RecruitmentInterviewSession::query()
            ->with(['division:id,name', 'period:id,name'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
