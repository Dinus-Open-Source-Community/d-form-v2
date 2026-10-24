<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Registrasi yang menunggu verifikasi OTP; baris dihapus setelah OTP terverifikasi.
 */
class PendingRegistration extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'otp_code',
        'otp_expires_at',
        'otp_attempts',
        'otp_sends',
        'otp_last_sent_at',
        'otp_locked_until',
    ];

    /** OTP disimpan terenkripsi; password pending tetap di-hash. */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'otp_code' => 'encrypted',
            'otp_expires_at' => 'datetime',
            'otp_attempts' => 'integer',
            'otp_sends' => 'integer',
            'otp_last_sent_at' => 'datetime',
            'otp_locked_until' => 'datetime',
        ];
    }
}
