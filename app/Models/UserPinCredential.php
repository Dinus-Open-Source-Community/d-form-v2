<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kredensial PIN faktor kedua milik satu user; PIN disimpan sebagai hash KDF. */
class UserPinCredential extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'pin_hash',
        'failed_attempts',
        'locked_until',
        'last_verified_at',
        'enabled_at',
    ];

    /** Hash PIN tidak boleh ikut terserialisasi (Inertia/JSON/log). */
    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_verified_at' => 'datetime',
            'enabled_at' => 'datetime',
        ];
    }

    /**
     *
     * @return BelongsTo<User, $this>
     *
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
