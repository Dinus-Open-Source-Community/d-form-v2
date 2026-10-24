<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CanResetPassword;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    /**
     * Kolom yang dibaca pada setiap request terautentikasi (guard sesi).
     * Password TIDAK ikut: hanya boolean `has_local_password` yang dihitung di SQL.
     */
    public const AUTH_SESSION_COLUMNS = [
        'id',
        'name',
        'email',
        'email_verified_at',
        'avatar',
        'created_at',
        'updated_at',
    ];

    /** Kolom sesi + remember_token untuk pemulihan lewat cookie "remember me". */
    public const AUTH_REMEMBER_COLUMNS = [
        ...self::AUTH_SESSION_COLUMNS,
        'remember_token',
    ];

    /** Kolom login (validasi hash butuh password) — hanya dipakai saat kredensial dicek. */
    public const AUTH_CREDENTIAL_COLUMNS = [
        ...self::AUTH_SESSION_COLUMNS,
        'password',
    ];

    protected $primaryKey = 'id';

    public $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'avatar',
        'google_id',
        'github_id',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_local_password' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Attendance scans recorded by this user (as operator).
     *
     * @return HasMany<EventAttendance, $this>
     */
    public function attendanceScansRecorded(): HasMany
    {
        return $this->hasMany(EventAttendance::class, 'scanned_by_user_id');
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] mixed $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
