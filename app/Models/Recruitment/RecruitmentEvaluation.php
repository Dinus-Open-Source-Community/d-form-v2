<?php

namespace App\Models\Recruitment;

use App\Enums\Recruitment\EvaluationRecommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentEvaluation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recruitment_application_id',
        'recruitment_interview_id',
        'speaking_score',
        'technical_score',
        'attitude_score',
        'recommendation',
        'notes',
        'evaluated_by',
        'evaluated_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'speaking_score' => 'integer',
            'technical_score' => 'integer',
            'attitude_score' => 'integer',
            'recommendation' => EvaluationRecommendation::class,
            'evaluated_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(RecruitmentInterview::class, 'recruitment_interview_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
