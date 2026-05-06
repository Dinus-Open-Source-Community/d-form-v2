<?php

namespace App\Models;

use App\Enums\EmailLogStatus;
use App\Enums\EmailNotificationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'form_answer_id',
        'event_id',
        'user_id',
        'recipient_email',
        'status',
        'notification_type',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailLogStatus::class,
            'notification_type' => EmailNotificationType::class,
            'sent_at' => 'datetime',
        ];
    }

    public function formAnswer(): BelongsTo
    {
        return $this->belongsTo(FormAnswer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
