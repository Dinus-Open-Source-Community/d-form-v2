<?php

namespace App\Models\Recruitment;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentInterview extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recruitment_application_id',
        'recruitment_interview_session_id',
        'interviewer_id',
        'scheduled_at',
        'location',
        'room',
        'status',
        'reminder_h1_sent_at',
        'reminder_h2_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'reminder_h1_sent_at' => 'datetime',
            'reminder_h2_sent_at' => 'datetime',
            'status' => \App\Enums\Recruitment\InterviewStatus::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RecruitmentInterviewSession::class, 'recruitment_interview_session_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function evaluation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RecruitmentEvaluation::class, 'recruitment_interview_id');
    }
}
