<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentInterviewSession extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recruitment_period_id',
        'recruitment_division_id',
        'session_date',
        'starts_at',
        'ends_at',
        'location',
        'room',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RecruitmentPeriod::class, 'recruitment_period_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(RecruitmentDivision::class, 'recruitment_division_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(RecruitmentInterview::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(RecruitmentAttendance::class);
    }

    public function queueEntries(): HasMany
    {
        return $this->hasMany(RecruitmentQueueEntry::class);
    }
}
