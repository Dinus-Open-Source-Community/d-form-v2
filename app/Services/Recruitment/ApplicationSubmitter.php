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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
final class ApplicationSubmitter
{
    /** Marker index (MySQL) / kolom (SQLite) untuk bentrok unique NIM per periode. */
    private const NIM_UNIQUE_MARKERS = ['rec_apps_period_nim_uniq', 'recruitment_applications.nim'];

    public function __construct(
        private readonly RecruitmentRegistrationNumberIssuer $registrationNumberIssuer,
        private readonly RecruitmentTrackingTokenGenerator $trackingTokenGenerator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: RecruitmentApplication, tracking_token: string}
     */
    public function submit(RecruitmentPeriod $period, array $data, UploadedFile $cv, ?UploadedFile $portfolioFile = null): array
    {
        try {
            $result = DB::transaction(function () use ($period, $data, $cv, $portfolioFile): array {
                $registrationNumber = $this->registrationNumberIssuer->issue($period);
                $trackingToken = $this->trackingTokenGenerator->generate();

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
                    'portfolio_type' => $data['portfolio_type'],
                ];

                if ($data['portfolio_type'] === 'url') {
                    $documentData['portfolio_url'] = $data['portfolio_url'] ?? null;
                } elseif ($portfolioFile !== null) {
                    $portfolioPath = $portfolioFile->store($storageBase, 'local');
                    $documentData['portfolio_path'] = $portfolioPath;
                    $documentData['portfolio_original_name'] = $portfolioFile->getClientOriginalName();
                    $documentData['portfolio_mime'] = $portfolioFile->getMimeType() ?? 'application/pdf';
                    $documentData['portfolio_size_bytes'] = $portfolioFile->getSize();
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

            if (UniqueConstraintViolation::matches($exception, self::NIM_UNIQUE_MARKERS)) {
                throw ValidationException::withMessages([
                    'nim' => ['NIM ini sudah terdaftar pada periode ini.'],
                ]);
            }

            Log::warning('[ApplicationSubmitter] Non-NIM unique conflict while inserting application.', [
                'recruitment_period_id' => $period->id,
                'exception_message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'registration_number' => ['Nomor registrasi bentrok saat dialokasikan. Silakan kirim ulang formulir.'],
            ]);
        }

        SendRecruitmentApplicationConfirmationJob::dispatch(
            $result['application']->id,
            $result['tracking_token'],
        );

        return $result;
    }
}
