<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentActivityLog;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\User;
use App\Support\LogRedaction;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecruitmentActivityLogger
{
    /**
     *
     * Fields approved for the audit trail: identifiers, enum-backed decisions, and counters.
     * @var array<string, true>
     *
     */
    private const AUDIT_VALUE_KEYS = [
        'semester' => true,
        'primary_division_id' => true,
        'secondary_division_id' => true,
        'stage' => true,
        'result' => true,
        'revision_required' => true,
        'is_verified' => true,
        'verified_by' => true,
        'method' => true,
        'session_id' => true,
        'correction_request_id' => true,
        'speaking_score' => true,
        'technical_score' => true,
        'attitude_score' => true,
        'recommendation' => true,
        'save_count' => true,
        'membership_type' => true,
        'final_division_id' => true,
        'final_division_name' => true,
        'is_cross_division' => true,
        'placement_source' => true,
        'type' => true,
        'template' => true,
        'reason' => true,
        'notes_present' => true,
        'scope' => true,
        'rows_generated' => true,
    ];

    private const MAX_AUDIT_VALUE_LENGTH = 200;

    /**
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     *
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
        $oldValues = self::projectValues($oldValues, $action) ?? [];
        $newValues = self::projectValues($newValues, $action) ?? [];

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

    /** Record one PII-free event for a completed recruitment CSV export. */
    public function recordExport(User $actor, string $periodId, string $scope, int $rowsGenerated): void
    {
        try {
            RecruitmentActivityLog::query()->create([
                'recruitment_application_id' => null,
                'recruitment_period_id' => $periodId,
                'actor_id' => $actor->id,
                'actor_type' => 'staff',
                'action' => 'export.generated',
                'entity_type' => 'recruitment_export',
                'entity_id' => null,
                'old_values' => null,
                'new_values' => self::projectValues([
                    'scope' => $scope,
                    'rows_generated' => $rowsGenerated,
                ], 'export.generated'),
                'ip_address' => null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('recruitment_export_audit_failed', [
                'actor_id' => $actor->id,
                'recruitment_period_id' => $periodId,
                'scope' => LogRedaction::text($scope, 100),
                'exception_class' => $exception::class,
            ]);
        }
    }

    /**
     *
     * Remove fields that are not approved for the recruitment audit trail and redact values.
     * @param  array<string, mixed>|null  $values
     * @return array<string, scalar|null>|null
     *
     */
    public static function projectValues(?array $values, ?string $action = null): ?array
    {
        if ($values === null) {
            return null;
        }

        $projected = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) || ! isset(self::AUDIT_VALUE_KEYS[$key])) {
                self::reportDroppedValue($action, $key, $value);

                continue;
            }

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if ($value !== null && ! is_scalar($value)) {
                continue;
            }

            $projected[$key] = is_string($value)
                ? LogRedaction::text($value, self::MAX_AUDIT_VALUE_LENGTH)
                : $value;
        }

        return $projected;
    }

    /** Record the key of a dropped non-allowlisted scalar without writing its value. */
    private static function reportDroppedValue(?string $action, int|string $key, mixed $value): void
    {
        if ($action === null || ! is_string($key) || $value === null || ! is_scalar($value)) {
            return;
        }

        Log::debug('activity_value_dropped', [
            'action' => $action,
            'key' => $key,
        ]);
    }

    /**
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     *
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
        $oldValues = self::projectValues($oldValues, $action) ?? [];
        $newValues = self::projectValues($newValues, $action) ?? [];

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
