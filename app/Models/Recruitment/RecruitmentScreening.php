<?php

namespace App\Models\Recruitment;

use App\Enums\Recruitment\ScreeningDecision;
use App\Enums\Recruitment\ScreeningReason;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentScreening extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'recruitment_application_id',
        'decision',
        'reason',
        'notes',
        'acted_by',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ScreeningDecision::class,
            'reason' => ScreeningReason::class,
            'acted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
