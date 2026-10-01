<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastAttachment extends Model
{
    use HasUuids;

    public const MAX_PER_BROADCAST = 3;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'broadcast_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    /** Induk broadcast pemilik lampiran. */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }
}
