<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentFinalDecision extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recruitment_application_id',
        'membership_type',
        'final_division_id',
        'internal_reason',
        'public_message',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    public function finalDivision(): BelongsTo
    {
        return $this->belongsTo(RecruitmentDivision::class, 'final_division_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'decided_by');
    }
}
