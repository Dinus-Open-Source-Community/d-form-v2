<?php

namespace App\Models\Recruitment;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentQrDispatchOutbox extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $primaryKey = 'email_log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'email_log_id',
        'status',
        'available_at',
        'delay_seconds',
        'dispatch_attempts',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'delay_seconds' => 'integer',
            'dispatch_attempts' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** Relate the durable QR dispatch intent to its queued delivery log. */
    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}
