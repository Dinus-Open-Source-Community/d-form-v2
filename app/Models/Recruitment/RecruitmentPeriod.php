<?php

namespace App\Models\Recruitment;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Policies\Recruitment\RecruitmentPeriodPolicy;

#[UsePolicy(RecruitmentPeriodPolicy::class)]
class RecruitmentPeriod extends Model
{
    /** @use HasFactory<\Database\Factories\Recruitment\RecruitmentPeriodFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'description',
        'registration_opens_at',
        'registration_closes_at',
        'interview_starts_at',
        'interview_ends_at',
        'finalization_deadline_at',
        'landing_content',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecruitmentPeriodStatus::class,
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'interview_starts_at' => 'date',
            'interview_ends_at' => 'date',
            'finalization_deadline_at' => 'date',
            'landing_content' => 'array',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RecruitmentApplication::class);
    }

    public function interviewSessions(): HasMany
    {
        return $this->hasMany(RecruitmentInterviewSession::class);
    }

    public function registrationSequence(): HasOne
    {
        return $this->hasOne(RecruitmentRegistrationSequence::class);
    }
}
