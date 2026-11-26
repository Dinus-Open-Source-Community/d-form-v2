<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralRedemption extends Model
{
    use HasUuids;

    public const STATUS_VALID = 'valid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'referral_code_id',
        'form_id',
        'form_answer_id',
        'group_token',
        'user_id',
        'participant_snapshot',
        'attribution_snapshot',
        'redeemed_at',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'participant_snapshot' => 'array',
            'attribution_snapshot' => 'array',
            'redeemed_at' => 'datetime',
        ];
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function formAnswer(): BelongsTo
    {
        return $this->belongsTo(FormAnswer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
