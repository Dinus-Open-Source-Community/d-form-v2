<?php

namespace App\Models;

use App\Enums\EmailBroadcastRecipientStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailBroadcastRecipient extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'broadcast_id',
        'name',
        'email',
        'status',
        'attempts',
        'sent_at',
        'failed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailBroadcastRecipientStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(EmailBroadcast::class, 'broadcast_id');
    }
}
