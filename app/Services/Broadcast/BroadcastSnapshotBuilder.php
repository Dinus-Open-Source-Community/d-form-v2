<?php

namespace App\Services\Broadcast;

use App\Models\Broadcast;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Services\Registration\FormAnswerRecipientResolver;

final class BroadcastSnapshotBuilder
{
    public function __construct(
        private readonly FormAnswerRecipientResolver $recipientResolver,
    ) {
    }

    /** Bangun snapshot penerima saat broadcast dibuat (source of truth, anti semua-data). */
    public function build(array $validated): array
    {
        $recipients = ($validated['source'] ?? null) === Broadcast::SOURCE_RECRUITMENT_APPLICANTS
            ? $this->recruitmentRecipients((string) $validated['period_id'])
            : $this->eventRecipients((string) $validated['event_id']);

        return [
            'source' => $validated['source'],
            'event_id' => $validated['event_id'] ?? null,
            'period_id' => $validated['period_id'] ?? null,
            'captured_at' => now()->toIso8601String(),
            'total' => count($recipients),
            'recipients' => $recipients,
        ];
    }

    /** Penerima peserta event: email unik pendaftar aktif (tanpa undangan terminasi/ditolak). */
    private function eventRecipients(string $eventId): array
    {
        $submissions = FormAnswer::query()
            ->whereHas('form', fn ($query) => $query->where('event_id', $eventId))
            ->excludeTerminatedInvitationMembers()
            ->excludeRejectedSubmissions()
            ->with('user')
            ->get();

        return $this->uniqueRecipients($submissions->map(fn (FormAnswer $submission): array => [
            'email' => $this->recipientResolver->email($submission),
            'name' => $submission->user?->name,
        ])->all());
    }

    /** Penerima pelamar: email unik aplikasi aktif periode tersebut. */
    private function recruitmentRecipients(string $periodId): array
    {
        $applications = RecruitmentApplication::query()
            ->where('recruitment_period_id', $periodId)
            ->whereNull('cancelled_at')
            ->get(['full_name', 'personal_email', 'student_email']);

        return $this->uniqueRecipients($applications->map(fn (RecruitmentApplication $application): array => [
            'email' => $this->normalizeEmail($application->personal_email ?? $application->student_email),
            'name' => $application->full_name,
        ])->all());
    }

    /** Dedup email valid, urut deterministik untuk snapshot stabil. */
    private function uniqueRecipients(array $candidates): array
    {
        $byEmail = [];

        foreach ($candidates as $candidate) {
            $email = is_string($candidate['email'] ?? null) ? $candidate['email'] : null;

            if ($email === null || isset($byEmail[$email])) {
                continue;
            }

            $byEmail[$email] = ['email' => $email, 'name' => $candidate['name'] ?? null];
        }

        ksort($byEmail);

        return array_values($byEmail);
    }

    /** Normalisasi email pelamar (kecil + trim + format valid). */
    private function normalizeEmail(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $normalized = mb_strtolower(trim($raw));

        return $normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false
            ? $normalized
            : null;
    }
}
