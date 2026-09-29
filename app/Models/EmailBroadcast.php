<?php

namespace App\Models;

use App\Enums\EmailBroadcastStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailBroadcast extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'scheduled_at',
        'delay_min',
        'delay_max',
        'event_id',
        'subject',
        'content',
        'status',
        'datasets',
        'total_recipients',
        'total_sent',
        'total_failed',
        'started_at',
        'completed_at',
        'cancelled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'delay_min' => 'integer',
            'delay_max' => 'integer',
            'status' => EmailBroadcastStatus::class,
            'datasets' => 'array',
            'total_recipients' => 'integer',
            'total_sent' => 'integer',
            'total_failed' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return HasMany<EmailBroadcastRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(EmailBroadcastRecipient::class, 'broadcast_id');
    }

    /** @return HasMany<EmailBroadcastAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailBroadcastAttachment::class, 'broadcast_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return $this->status === EmailBroadcastStatus::Draft;
    }
}
