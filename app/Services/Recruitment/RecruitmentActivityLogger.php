<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\User;
use Illuminate\Http\Request;

final class RecruitmentActivityLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $action,
        User $actor,
        ?RecruitmentApplication $application = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $entityType = null,
        ?string $entityId = null,
        ?Request $request = null,
        string $actorType = 'staff',
    ): RecruitmentActivityLog {
        return RecruitmentActivityLog::query()->create([
            'recruitment_application_id' => $application?->id,
            'recruitment_period_id' => $application?->recruitment_period_id,
            'actor_id' => $actor->id,
            'actor_type' => $actorType,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues !== [] ? $oldValues : null,
            'new_values' => $newValues !== [] ? $newValues : null,
            'ip_address' => $request?->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function logApplicant(
        string $action,
        RecruitmentApplication $application,
        array $oldValues = [],
        array $newValues = [],
        ?string $entityType = null,
        ?string $entityId = null,
        ?Request $request = null,
    ): RecruitmentActivityLog {
        return RecruitmentActivityLog::query()->create([
            'recruitment_application_id' => $application->id,
            'recruitment_period_id' => $application->recruitment_period_id,
            'actor_id' => null,
            'actor_type' => 'applicant',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues !== [] ? $oldValues : null,
            'new_values' => $newValues !== [] ? $newValues : null,
            'ip_address' => $request?->ip(),
            'created_at' => now(),
        ]);
    }
}
