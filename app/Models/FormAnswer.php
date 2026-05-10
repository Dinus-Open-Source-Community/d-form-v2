<?php

namespace App\Models;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormAnswer extends Model
{
    /** @use HasFactory<\Database\Factories\FormAnswerFactory> */
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'answers',
        'form_id',
        'user_id',
        'leader_form_answer_id',
        'registration_role',
        'member_confirmation_status',
        'invitation_token',
        'group_token',
        'invited_email',
        'member_confirmed_at',
        'invitation_expired_at',
        'review_status',
        'reviewed_at',
        'reviewed_by',
        'registration_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'registration_role' => RegistrationRole::class,
            'member_confirmation_status' => MemberConfirmationStatus::class,
            'review_status' => FormAnswerReviewStatus::class,
            'reviewed_at' => 'datetime',
            'member_confirmed_at' => 'datetime',
            'invitation_expired_at' => 'datetime',
        ];
    }

    public function isMemberPendingInvitation(): bool
    {
        return $this->registration_role === RegistrationRole::Member
            && $this->member_confirmation_status === MemberConfirmationStatus::Pending;
    }

    public function isInvitationTerminal(): bool
    {
        if ($this->registration_role !== RegistrationRole::Member) {
            return false;
        }

        return in_array($this->member_confirmation_status, [
            MemberConfirmationStatus::Rejected,
            MemberConfirmationStatus::Expired,
        ], true);
    }

    /**
     * Omit teammate rows whose invitation ended without participating (participant portal summaries only).
     */
    public function scopeExcludeTerminatedInvitationMembers(Builder $query): Builder
    {
        return $query->where(static function (Builder $w): void {
            $w->whereNull('registration_role')
                ->orWhere('registration_role', '!=', RegistrationRole::Member->value)
                ->orWhereNull('member_confirmation_status')
                ->orWhereNotIn('member_confirmation_status', [
                    MemberConfirmationStatus::Rejected->value,
                    MemberConfirmationStatus::Expired->value,
                ]);
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function teamLeaderSubmission(): BelongsTo
    {
        return $this->belongsTo(self::class, 'leader_form_answer_id');
    }

    /**
     * @return HasMany<FormAnswer, $this>
     */
    public function teamMemberSubmissions(): HasMany
    {
        return $this->hasMany(self::class, 'leader_form_answer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<EmailLog, $this>
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class, 'form_answer_id');
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }
}
