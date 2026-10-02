<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailBroadcastAttachment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'broadcast_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(EmailBroadcast::class, 'broadcast_id');
    }
}
