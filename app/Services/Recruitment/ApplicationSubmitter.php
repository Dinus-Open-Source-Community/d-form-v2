<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Jobs\Recruitment\SendRecruitmentApplicationConfirmationJob;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDocument;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Support\Database\UniqueConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ApplicationSubmitter
{
    public function __construct(
        private readonly RecruitmentRegistrationNumberIssuer $registrationNumberIssuer,
        private readonly RecruitmentTrackingTokenGenerator $trackingTokenGenerator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: RecruitmentApplication, tracking_token: string}
     */
    public function submit(
        RecruitmentPeriod $period,
        array $data,
        UploadedFile $cv,
        ?UploadedFile $portfolioFile = null,
        ?UploadedFile $instagramFollowProof = null,
    ): array {
        $registrationNumber = $this->registrationNumberIssuer->issue($period);
        $trackingToken = $this->trackingTokenGenerator->generate();

        try {
            $result = DB::transaction(function () use ($period, $data, $cv, $portfolioFile, $instagramFollowProof, $registrationNumber, $trackingToken): array {
                $application = RecruitmentApplication::query()->create([
                    'recruitment_period_id' => $period->id,
                    'registration_number' => $registrationNumber,
                    'tracking_token_hash' => Hash::make($trackingToken),
                    'full_name' => $data['full_name'],
                    'nim' => $data['nim'],
                    'semester' => (int) $data['semester'],
                    'phone' => $data['phone'],
                    'personal_email' => $data['personal_email'],
                    'student_email' => $data['student_email'],
                    'instagram_username' => $data['instagram_username'],
                    'primary_division_id' => $data['primary_division_id'],
                    'secondary_division_id' => $data['secondary_division_id'] ?? null,
                    'stage' => ApplicationStage::Submitted,
                    'result' => ApplicationResult::Pending,
                    'submitted_at' => now(),
                ]);

                $storageBase = 'recruitment/'.$period->id.'/'.$application->id;

                $cvPath = $cv->store($storageBase, 'local');

                $documentData = [
                    'recruitment_application_id' => $application->id,
                    'cv_path' => $cvPath,
                    'cv_original_name' => $cv->getClientOriginalName(),
                    'cv_mime' => $cv->getMimeType() ?? 'application/pdf',
                    'cv_size_bytes' => $cv->getSize(),
                    'portfolio_type' => $data['portfolio_type'] ?: 'none',
                    'twibbon_url' => $data['twibbon_url'] ?? null,
                ];

                if (($data['portfolio_type'] ?? 'none') === 'url') {
                    $documentData['portfolio_url'] = $data['portfolio_url'] ?? null;
                } elseif (($data['portfolio_type'] ?? 'none') === 'file' && $portfolioFile !== null) {
                    $portfolioPath = $portfolioFile->store($storageBase, 'local');
                    $documentData['portfolio_path'] = $portfolioPath;
                    $documentData['portfolio_original_name'] = $portfolioFile->getClientOriginalName();
                    $documentData['portfolio_mime'] = $portfolioFile->getMimeType() ?? 'application/pdf';
                    $documentData['portfolio_size_bytes'] = $portfolioFile->getSize();
                } elseif (($data['portfolio_type'] ?? 'none') === 'file' && $portfolioFile === null) {
                    // File portfolio opsional: tipe file tanpa unggahan disimpan sebagai none.
                    $documentData['portfolio_type'] = 'none';
                }

                if ($instagramFollowProof !== null) {
                    $documentData['instagram_follow_path'] = $instagramFollowProof->store($storageBase, 'local');
                    $documentData['instagram_follow_original_name'] = $instagramFollowProof->getClientOriginalName();
                    $documentData['instagram_follow_mime'] = $instagramFollowProof->getMimeType() ?? 'image/jpeg';
                    $documentData['instagram_follow_size_bytes'] = $instagramFollowProof->getSize();
                }

                RecruitmentDocument::query()->create($documentData);

                return [
                    'application' => $application->fresh(['primaryDivision', 'secondaryDivision', 'document']),
                    'tracking_token' => $trackingToken,
                ];
            });
        } catch (QueryException $exception) {
            if (! UniqueConstraintViolation::isViolation($exception)) {
                throw $exception;
            }

            if ($this->applicationExistsForNim($period, (string) $data['nim'])) {
                throw ValidationException::withMessages([
                    'nim' => ['NIM ini sudah terdaftar pada periode ini.'],
                ]);
            }

            if ($this->applicationExistsForRegistrationNumber($registrationNumber)) {
                throw ValidationException::withMessages([
                    'registration_number' => ['Nomor registrasi bentrok saat dialokasikan. Silakan kirim ulang formulir.'],
                ]);
            }

            throw $exception;
        }

        SendRecruitmentApplicationConfirmationJob::dispatch(
            $result['application']->id,
            $result['tracking_token'],
        );

        return $result;
    }

    private function applicationExistsForNim(RecruitmentPeriod $period, string $nim): bool
    {
        return RecruitmentApplication::query()
            ->where('recruitment_period_id', $period->id)
            ->where('nim', $nim)
            ->exists();
    }

    private function applicationExistsForRegistrationNumber(string $registrationNumber): bool
    {
        return RecruitmentApplication::query()
            ->where('registration_number', $registrationNumber)
            ->exists();
    }
}
