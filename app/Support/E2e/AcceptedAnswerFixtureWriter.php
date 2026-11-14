<?php

namespace App\Support\E2e;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Menulis submission `review_status=accepted` ber-marker untuk form milik fixture. */
final class AcceptedAnswerFixtureWriter
{
    public function __construct(private readonly FixtureJournal $journal)
    {
    }

    /**
     *
     * @param  array<string, mixed>  $payload  Payload fixture penuh (membaca `acceptedAnswers`)
     * @param  list<string>  $formIds  Id form fixture, urut sesuai `payload.forms`
     * @return list<array{answerId: string, registrationCode: string, formId: string, attendanceId: string|null}>
     *
     */
    public function create(array $payload, array $formIds, string $eventId, string $scannedByUserId, Carbon $now): array
    {
        $entries = $payload['acceptedAnswers'] ?? [];

        if (! is_array($entries)) {
            throw new \InvalidArgumentException('payload.acceptedAnswers must be an array.');
        }

        $results = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw new \InvalidArgumentException('each payload.acceptedAnswers entry must be an object.');
            }

            $formIndex = $entry['formIndex'] ?? 0;

            if (! is_int($formIndex) || $formIndex < 0 || ! isset($formIds[$formIndex])) {
                throw new \InvalidArgumentException('payload.acceptedAnswers.formIndex must reference an existing payload.forms entry.');
            }

            $formId = $formIds[$formIndex];
            $registrationCode = trim((string) ($entry['registrationCode'] ?? ''));

            if ($registrationCode === '' || mb_strlen($registrationCode) > 32) {
                throw new \InvalidArgumentException('payload.acceptedAnswers.registrationCode must be 1-32 characters.');
            }

            $answers = $entry['answers'] ?? ['name' => 'E2E attendee'];

            if (! is_array($answers) || $answers === []) {
                throw new \InvalidArgumentException('payload.acceptedAnswers.answers must be a non-empty object.');
            }

            $invitedEmail = $entry['invitedEmail'] ?? null;

            if ($invitedEmail !== null) {
                if (! is_string($invitedEmail) || filter_var($invitedEmail, FILTER_VALIDATE_EMAIL) === false) {
                    throw new \InvalidArgumentException('payload.acceptedAnswers.invitedEmail must be a valid email address.');
                }
            }

            $attendance = $entry['attendance'] ?? false;

            if (! is_bool($attendance)) {
                throw new \InvalidArgumentException('payload.acceptedAnswers.attendance must be a boolean.');
            }

            $answerId = $this->createAnswer($formId, $registrationCode, $answers, $invitedEmail, $now);
            $attendanceId = $attendance ? $this->createAttendance($eventId, $answerId, $scannedByUserId, $now) : null;

            $results[] = [
                'answerId' => $answerId,
                'registrationCode' => $registrationCode,
                'formId' => $formId,
                'attendanceId' => $attendanceId,
            ];
        }

        return $results;
    }

    /**
     *
     * @param  array<string, mixed>  $answers
     *
     */
    private function createAnswer(string $formId, string $registrationCode, array $answers, ?string $invitedEmail, Carbon $now): string
    {
        $existing = DB::table('form_answers')
            ->where('registration_code', $registrationCode)
            ->first(['id', 'form_id']);

        if ($existing !== null) {
            if ((string) $existing->form_id !== $formId) {
                throw new \InvalidArgumentException(
                    'payload.acceptedAnswers.registrationCode already exists on another form; '
                    .'fixture tidak boleh mengadopsi submission di luar form fixture.',
                );
            }

            return (string) $existing->id;
        }

        $answerId = (string) Str::orderedUuid();
        DB::table('form_answers')->insert([
            'id' => $answerId,
            'answers' => json_encode($answers, JSON_THROW_ON_ERROR),
            'user_id' => null,
            'form_id' => $formId,
            'is_active' => 1,
            'review_status' => 'accepted',
            'reviewed_at' => $now,
            'reviewed_by' => null,
            'registration_code' => $registrationCode,
            'invited_email' => $invitedEmail,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->journal->record('form_answers', $answerId);

        return $answerId;
    }

    private function createAttendance(string $eventId, string $answerId, string $scannedByUserId, Carbon $now): string
    {
        $existing = DB::table('event_attendances')
            ->where('event_id', $eventId)
            ->where('form_answer_id', $answerId)
            ->value('id');

        if ($existing !== null) {
            return (string) $existing;
        }

        $attendanceId = (string) Str::orderedUuid();
        DB::table('event_attendances')->insert([
            'id' => $attendanceId,
            'event_id' => $eventId,
            'form_answer_id' => $answerId,
            'scanned_by_user_id' => $scannedByUserId,
            'scanned_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->journal->record('event_attendances', $attendanceId);

        return $attendanceId;
    }
}
