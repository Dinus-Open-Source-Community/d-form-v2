<?php

namespace App\Models\Recruitment;

use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RecruitmentApplication extends Model
{
    /** @use HasFactory<\Database\Factories\Recruitment\RecruitmentApplicationFactory> */
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recruitment_period_id',
        'registration_number',
        'tracking_token_hash',
        'full_name',
        'nim',
        'semester',
        'phone',
        'personal_email',
        'student_email',
        'instagram_username',
        'primary_division_id',
        'secondary_division_id',
        'stage',
        'result',
        'is_verified',
        'revision_required',
        'submitted_at',
        'verified_at',
        'verified_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ApplicationStage::class,
            'result' => ApplicationResult::class,
            'is_verified' => 'boolean',
            'revision_required' => 'boolean',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'semester' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RecruitmentPeriod::class, 'recruitment_period_id');
    }

    public function primaryDivision(): BelongsTo
    {
        return $this->belongsTo(RecruitmentDivision::class, 'primary_division_id');
    }

    public function secondaryDivision(): BelongsTo
    {
        return $this->belongsTo(RecruitmentDivision::class, 'secondary_division_id');
    }

    public function document(): HasOne
    {
        return $this->hasOne(RecruitmentDocument::class, 'recruitment_application_id');
    }

    public function interview(): HasOne
    {
        return $this->hasOne(RecruitmentInterview::class, 'recruitment_application_id');
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(RecruitmentAttendance::class, 'recruitment_application_id');
    }

    public function queueEntry(): HasOne
    {
        return $this->hasOne(RecruitmentQueueEntry::class, 'recruitment_application_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(RecruitmentEvaluation::class, 'recruitment_application_id');
    }

    public function finalDecision(): HasOne
    {
        return $this->hasOne(RecruitmentFinalDecision::class, 'recruitment_application_id');
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(RecruitmentCorrectionRequest::class, 'recruitment_application_id');
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(RecruitmentScreening::class, 'recruitment_application_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(RecruitmentActivityLog::class, 'recruitment_application_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(RecruitmentFeedback::class, 'recruitment_application_id');
    }
}
