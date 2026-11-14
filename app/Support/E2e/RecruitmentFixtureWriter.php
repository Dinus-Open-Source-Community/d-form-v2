<?php

namespace App\Support\E2e;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\RecruitmentPeriodStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Menulis baris recruitment ber-marker: periode, aplikasi, dan dokumen minimum. */
final class RecruitmentFixtureWriter
{
    public function __construct(
        private readonly FixtureIdentity $identity,
        private readonly FixtureJournal $journal,
    ) {
    }

    /**
     *
     * Buat (atau pakai ulang) periode + aplikasi + dokumen untuk satu marker.
     *
     * @param  array<string, mixed>  $payload  Isi `payload.recruitment`
     * @return array{periodId: string, periodName: string, divisionId: string, applicationId: string, registrationNumber: string, trackingToken: string, documentId: string|null}
     *
     */
    public function create(array $payload, Carbon $now): array
    {
        $application = $payload['application'] ?? null;

        if (! is_array($application)) {
            throw new \InvalidArgumentException('payload.recruitment.application must be an object.');
        }

        $divisionId = $this->resolveDivisionId($payload['divisionCode'] ?? null);
        $period = $payload['period'] ?? [];

        if (! is_array($period)) {
            throw new \InvalidArgumentException('payload.recruitment.period must be an object.');
        }

        [$periodId, $periodName] = $this->createPeriod($period, $now);
        [$applicationId, $registrationNumber, $trackingToken] = $this->createApplication(
            $application,
            $periodId,
            $divisionId,
            $now,
        );
        $documentId = $this->createDocument($applicationId, $application['document'] ?? true, $now);

        return [
            'periodId' => $periodId,
            'periodName' => $periodName,
            'divisionId' => $divisionId,
            'applicationId' => $applicationId,
            'registrationNumber' => $registrationNumber,
            'trackingToken' => $trackingToken,
            'documentId' => $documentId,
        ];
    }

    /** Divisi tujuan aplikasi; memakai `divisionCode` bila diberikan, selain itu divisi aktif pertama. */
    private function resolveDivisionId(mixed $divisionCode): string
    {
        if ($divisionCode !== null) {
            if (! is_string($divisionCode) || $divisionCode === '') {
                throw new \InvalidArgumentException('payload.recruitment.divisionCode must be a non-empty string.');
            }

            $divisionId = DB::table('recruitment_divisions')->where('code', $divisionCode)->value('id');

            if ($divisionId === null) {
                throw new \InvalidArgumentException('payload.recruitment.divisionCode does not match any recruitment_divisions.code row.');
            }

            return (string) $divisionId;
        }

        $divisionId = DB::table('recruitment_divisions')
            ->where('is_active', true)
            ->orderBy('code')
            ->value('id');

        if ($divisionId === null) {
            throw new \InvalidArgumentException(
                'payload.recruitment needs an active recruitment division; seed recruitment divisions first.',
            );
        }

        return (string) $divisionId;
    }

    /**
     *
     * @param  array<string, mixed>  $period
     * @return array{0: string, 1: string} id dan nama ber-marker
     *
     */
    private function createPeriod(array $period, Carbon $now): array
    {
        $name = $this->identity->suffixed((string) ($period['name'] ?? 'E2E recruitment'));
        $slug = (string) ($period['slug'] ?? $this->identity->slug());
        $status = (string) ($period['status'] ?? RecruitmentPeriodStatus::Closed->value);

        if (preg_match('/^[a-z0-9-]{1,100}$/', $slug) !== 1) {
            throw new \InvalidArgumentException('payload.recruitment.period.slug must match [a-z0-9-] and be at most 100 characters.');
        }

        if (! $this->isPeriodStatus($status)) {
            throw new \InvalidArgumentException('payload.recruitment.period.status is not a valid RecruitmentPeriodStatus.');
        }

        $existing = DB::table('recruitment_periods')->where('slug', $slug)->first(['id', 'name']);

        if ($existing !== null) {
            if ((string) $existing->name !== $name) {
                throw new \InvalidArgumentException(
                    'payload.recruitment.period.slug already belongs to a different period name; slug is unique.',
                );
            }

            return [(string) $existing->id, $name];
        }

        $periodId = (string) Str::orderedUuid();
        DB::table('recruitment_periods')->insert([
            'id' => $periodId,
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->journal->record('recruitment_periods', $periodId);

        return [$periodId, $name];
    }

    /**
     *
     * @param  array<string, mixed>  $application
     * @return array{0: string, 1: string, 2: string} id, nomor pendaftaran, token tracking
     *
     */
    private function createApplication(array $application, string $periodId, string $divisionId, Carbon $now): array
    {
        $registrationNumber = (string) ($application['registrationNumber'] ?? '');
        // Token caller-controlled wajib berentropi cukup; bila tidak dikirim, command membuat 40 karakter acak.
        $trackingToken = $application['trackingToken'] ?? Str::random(40);
        $stage = (string) ($application['stage'] ?? ApplicationStage::Screening->value);
        $result = (string) ($application['result'] ?? ApplicationResult::Pending->value);

        if ($registrationNumber === '' || mb_strlen($registrationNumber) > 30) {
            throw new \InvalidArgumentException('payload.recruitment.application.registrationNumber must be 1-30 characters.');
        }

        if (! is_string($trackingToken) || mb_strlen($trackingToken) < 32) {
            throw new \InvalidArgumentException(
                'payload.recruitment.application.trackingToken must be at least 32 characters; omit it to generate one.',
            );
        }

        if (! $this->isStage($stage)) {
            throw new \InvalidArgumentException('payload.recruitment.application.stage is not a valid ApplicationStage.');
        }

        if (! $this->isResult($result)) {
            throw new \InvalidArgumentException('payload.recruitment.application.result is not a valid ApplicationResult.');
        }

        $existing = DB::table('recruitment_applications')
            ->where('registration_number', $registrationNumber)
            ->first(['id', 'recruitment_period_id', 'full_name', 'nim']);

        if ($existing !== null) {
            if ((string) $existing->recruitment_period_id !== $periodId) {
                throw new \InvalidArgumentException('payload.recruitment.application.registrationNumber already exists under another period.');
            }

            $fullName = (string) ($application['fullName'] ?? 'E2E applicant '.$this->identity->ownerKey);
            $nim = (string) ($application['nim'] ?? 'E2E'.strtoupper($this->identity->ownerKey));

            // Idempoten hanya untuk baris milik payload yang sama; baris lain tidak boleh diubah.
            if ((string) $existing->full_name !== $fullName || (string) $existing->nim !== $nim) {
                throw new \InvalidArgumentException(
                    'payload.recruitment.application.registrationNumber already exists with a different '
                    .'fixture identity; refusing to reuse or mutate a foreign row.',
                );
            }

            return [(string) $existing->id, $registrationNumber, $trackingToken];
        }

        $fullName = (string) ($application['fullName'] ?? 'E2E applicant '.$this->identity->ownerKey);
        $nim = (string) ($application['nim'] ?? 'E2E'.strtoupper($this->identity->ownerKey));

        $applicationId = (string) Str::orderedUuid();
        DB::table('recruitment_applications')->insert([
            'id' => $applicationId,
            'recruitment_period_id' => $periodId,
            'registration_number' => $registrationNumber,
            'tracking_token_hash' => Hash::make($trackingToken),
            'full_name' => $fullName,
            'nim' => $nim,
            'semester' => (int) ($application['semester'] ?? 2),
            'phone' => (string) ($application['phone'] ?? '080000000000'),
            'personal_email' => (string) ($application['personalEmail'] ?? $this->identity->email()),
            'student_email' => (string) ($application['studentEmail'] ?? strtolower($this->identity->ownerKey).'@students.udinus.ac.id'),
            'instagram_username' => (string) ($application['instagramUsername'] ?? $this->identity->ownerKey),
            'primary_division_id' => $divisionId,
            'stage' => $stage,
            'result' => $result,
            'is_verified' => (bool) ($application['isVerified'] ?? false),
            'revision_required' => (bool) ($application['revisionRequired'] ?? false),
            'submitted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->journal->record('recruitment_applications', $applicationId);

        return [$applicationId, $registrationNumber, $trackingToken];
    }

    /** Dokumen minimum (CV + portfolio URL) yang dibutuhkan halaman tracking. */
    private function createDocument(string $applicationId, mixed $enabled, Carbon $now): ?string
    {
        if (! is_bool($enabled)) {
            throw new \InvalidArgumentException('payload.recruitment.application.document must be a boolean.');
        }

        if (! $enabled) {
            return null;
        }

        $existing = DB::table('recruitment_documents')
            ->where('recruitment_application_id', $applicationId)
            ->value('id');

        if ($existing !== null) {
            return (string) $existing;
        }

        $documentId = (string) Str::orderedUuid();
        DB::table('recruitment_documents')->insert([
            'id' => $documentId,
            'recruitment_application_id' => $applicationId,
            'cv_path' => 'recruitment/e2e/'.$applicationId.'/cv.pdf',
            'cv_original_name' => 'cv.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size_bytes' => 1000,
            'portfolio_type' => 'url',
            'portfolio_url' => 'https://portfolio.example.com/e2e',
            'instagram_follow_path' => 'recruitment/e2e/'.$applicationId.'/follow.jpg',
            'instagram_follow_original_name' => 'follow.jpg',
            'instagram_follow_mime' => 'image/jpeg',
            'instagram_follow_size_bytes' => 2048,
            'twibbon_url' => 'https://instagram.com/p/e2e-twibbon',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->journal->record('recruitment_documents', $documentId);

        return $documentId;
    }

    private function isPeriodStatus(string $status): bool
    {
        return in_array($status, array_map(static fn (RecruitmentPeriodStatus $case): string => $case->value, RecruitmentPeriodStatus::cases()), true);
    }

    private function isStage(string $stage): bool
    {
        return in_array($stage, array_map(static fn (ApplicationStage $case): string => $case->value, ApplicationStage::cases()), true);
    }

    private function isResult(string $result): bool
    {
        return in_array($result, array_map(static fn (ApplicationResult $case): string => $case->value, ApplicationResult::cases()), true);
    }
}
