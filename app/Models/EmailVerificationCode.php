<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tantangan verifikasi email milik satu user; kode disimpan sebagai HMAC, bukan plaintext. */
class EmailVerificationCode extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'purpose',
        'code_hash',
        'expires_at',
        'attempts',
        'sends',
        'last_sent_at',
        'locked_until',
    ];

    /** Rahasia tidak boleh ikut terserialisasi (Inertia/JSON/log). */
    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            'sends' => 'integer',
            'last_sent_at' => 'datetime',
            'locked_until' => 'datetime',
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
