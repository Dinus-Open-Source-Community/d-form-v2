<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\CorrectionRequestStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;
use App\Models\Recruitment\RecruitmentDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class ApplicationEditor
{
    public function __construct(
        private readonly ApplicationEditGate $editGate,
        private readonly RecruitmentActivityLogger $activityLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        RecruitmentApplication $application,
        array $data,
        ?UploadedFile $cv = null,
        ?UploadedFile $portfolioFile = null,
    ): RecruitmentApplication {
        if (! $this->editGate->canEdit($application)) {
            throw ValidationException::withMessages([
                'application' => 'Pendaftaran tidak dapat diedit saat ini.',
            ]);
        }

        $isRevision = $this->editGate->isRevisionResubmit($application);
        $approvedCorrection = $this->editGate->approvedCorrection($application);

        return DB::transaction(function () use ($application, $data, $cv, $portfolioFile, $isRevision, $approvedCorrection): RecruitmentApplication {
            $oldValues = $this->snapshotApplication($application);

            $application->update([
                'full_name' => $data['full_name'],
                'nim' => $data['nim'],
                'semester' => (int) $data['semester'],
                'phone' => $data['phone'],
                'personal_email' => $data['personal_email'],
                'student_email' => $data['student_email'],
                'instagram_username' => $data['instagram_username'],
                'primary_division_id' => $data['primary_division_id'],
                'secondary_division_id' => $data['secondary_division_id'] ?? null,
            ]);

            $this->updateDocuments($application, $data, $cv, $portfolioFile);

            if ($isRevision) {
                $application->update([
                    'revision_required' => false,
                    'stage' => ApplicationStage::Submitted,
                ]);
            }

            if ($approvedCorrection !== null) {
                $approvedCorrection->update([
                    'status' => CorrectionRequestStatus::Completed,
                    'completed_at' => now(),
                ]);

                $application->update([
                    'is_verified' => false,
                    'verified_at' => null,
                    'verified_by' => null,
                ]);
            }

            $application->refresh();

            $this->activityLogger->logApplicant(
                action: 'application.updated',
                application: $application,
                oldValues: $oldValues,
                newValues: $this->snapshotApplication($application),
                entityType: 'recruitment_application',
                entityId: $application->id,
            );

            return $application->fresh(['primaryDivision', 'secondaryDivision', 'document']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateDocuments(
        RecruitmentApplication $application,
        array $data,
        ?UploadedFile $cv,
        ?UploadedFile $portfolioFile,
    ): void {
        $document = $application->document ?? new RecruitmentDocument([
            'recruitment_application_id' => $application->id,
        ]);

        $storageBase = 'recruitment/'.$application->recruitment_period_id.'/'.$application->id;

        if ($cv !== null) {
            if (filled($document->cv_path)) {
                Storage::disk('local')->delete($document->cv_path);
            }

            $document->cv_path = $cv->store($storageBase, 'local');
            $document->cv_original_name = $cv->getClientOriginalName();
            $document->cv_mime = $cv->getMimeType() ?? 'application/pdf';
            $document->cv_size_bytes = $cv->getSize();
        }

        $document->portfolio_type = $data['portfolio_type'];

        if ($data['portfolio_type'] === 'url') {
            if (filled($document->portfolio_path)) {
                Storage::disk('local')->delete($document->portfolio_path);
            }

            $document->portfolio_url = $data['portfolio_url'] ?? null;
            $document->portfolio_path = null;
            $document->portfolio_original_name = null;
            $document->portfolio_mime = null;
            $document->portfolio_size_bytes = null;
        } elseif ($portfolioFile !== null) {
            if (filled($document->portfolio_path)) {
                Storage::disk('local')->delete($document->portfolio_path);
            }

            $document->portfolio_url = null;
            $document->portfolio_path = $portfolioFile->store($storageBase, 'local');
            $document->portfolio_original_name = $portfolioFile->getClientOriginalName();
            $document->portfolio_mime = $portfolioFile->getMimeType() ?? 'application/pdf';
            $document->portfolio_size_bytes = $portfolioFile->getSize();
        }

        $document->recruitment_application_id = $application->id;
        $document->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotApplication(RecruitmentApplication $application): array
    {
        return [
            'full_name' => $application->full_name,
            'nim' => $application->nim,
            'semester' => $application->semester,
            'phone' => $application->phone,
            'personal_email' => $application->personal_email,
            'student_email' => $application->student_email,
            'instagram_username' => $application->instagram_username,
            'primary_division_id' => $application->primary_division_id,
            'secondary_division_id' => $application->secondary_division_id,
            'stage' => $application->stage->value,
            'revision_required' => $application->revision_required,
            'is_verified' => $application->is_verified,
        ];
    }
}
