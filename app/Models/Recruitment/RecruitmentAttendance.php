<?php

namespace App\Models\Recruitment;

use App\Enums\Recruitment\AttendanceMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentAttendance extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'recruitment_application_id',
        'recruitment_interview_session_id',
        'method',
        'checked_in_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'method' => AttendanceMethod::class,
            'checked_in_at' => 'datetime',
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

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
