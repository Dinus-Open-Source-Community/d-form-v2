<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentFeedback extends Model
{
    use HasUuids;

    protected $table = 'recruitment_feedbacks';

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'recruitment_application_id',
        'recruitment_period_id',
        'rating_registration_ease',
        'rating_info_clarity',
        'rating_tracking_ease',
        'rating_interview_experience',
        'rating_staff_service',
        'feedback_text',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'rating_registration_ease' => 'integer',
            'rating_info_clarity' => 'integer',
            'rating_tracking_ease' => 'integer',
            'rating_interview_experience' => 'integer',
            'rating_staff_service' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RecruitmentPeriod::class, 'recruitment_period_id');
    }
}
