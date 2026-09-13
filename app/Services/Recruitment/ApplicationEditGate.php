<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\CorrectionRequestStatus;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentCorrectionRequest;

final class ApplicationEditGate
{
    public function canEdit(RecruitmentApplication $application): bool
    {
        if ($application->cancelled_at !== null) {
            return false;
        }

        if ($application->result !== ApplicationResult::Pending) {
            return false;
        }

        if ($application->revision_required) {
            return true;
        }

        if (! $application->is_verified) {
            return true;
        }

        return $this->approvedCorrection($application) !== null;
    }

    public function canRequestCorrection(RecruitmentApplication $application): bool
    {
        if ($application->cancelled_at !== null) {
            return false;
        }

        if (! $application->is_verified) {
            return false;
        }

        if ($application->result !== ApplicationResult::Pending) {
            return false;
        }

        if ($application->revision_required) {
            return false;
        }

        return ! RecruitmentCorrectionRequest::query()
            ->where('recruitment_application_id', $application->id)
            ->whereIn('status', [
                CorrectionRequestStatus::Pending,
                CorrectionRequestStatus::Approved,
            ])
            ->exists();
    }

    public function approvedCorrection(RecruitmentApplication $application): ?RecruitmentCorrectionRequest
    {
        return RecruitmentCorrectionRequest::query()
            ->where('recruitment_application_id', $application->id)
            ->where('status', CorrectionRequestStatus::Approved)
            ->latest('created_at')
            ->first();
    }

    public function isRevisionResubmit(RecruitmentApplication $application): bool
    {
        return $application->revision_required;
    }

    public function isCorrectionResubmit(RecruitmentApplication $application): bool
    {
        return $this->approvedCorrection($application) !== null;
    }
}
