<?php

namespace App\Models;

use App\Enums\DuRedeemStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuRedeemIssuance extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'form_answer_id', 'course_uid', 'status', 'redeem_code', 'failure_reason',
        'attempted_at', 'attempt_count', 'issued_at',
        'du_uid', 'course_title', 'target_email', 'max_uses', 'used_count',
        'is_active', 'expires_at', 'du_created_at', 'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => DuRedeemStatus::class,
            'attempted_at' => 'datetime',
            'issued_at' => 'datetime',
            'attempt_count' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'du_created_at' => 'datetime',
            'response_payload' => 'array',
        ];
    }

    public function formAnswer(): BelongsTo
    {
        return $this->belongsTo(FormAnswer::class);
    }
}
