<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Builder;
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
        'email_normalized',
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
     * Restrict the query to users whose generated normalized email is listed.
     *
     * `email_normalized` is a database-maintained generated column (see
     * 2026_11_10_000008_users_email_lookup_index) so the predicate stays
     * index-usable while remaining case-insensitive on every supported engine.
     *
     * @param  list<string>  $normalizedEmails  lowercase, trimmed emails
     */
    public function scopeWhereNormalizedEmailIn(Builder $query, array $normalizedEmails): Builder
    {
        return $query->whereIn('email_normalized', $normalizedEmails);
    }

    /**
     * Resolve users from emails that are already lowercase and trimmed.
     *
     * The fast path uses the indexed generated column. Inputs that only match
     * through the database collation (accent-insensitive or PAD SPACE on
     * MySQL/MariaDB) fall back to the historical `LOWER(email) = ?` predicate,
     * so every account that resolved before EP-04 still resolves.
     *
     * @param  array<array-key, string>  $emails  lowercase, trimmed emails
     * @return array<string, self> input email => historical first matching user
     */
    public static function resolveByNormalizedEmails(array $emails, bool $withTrashed = false): array
    {
        $unique = [];

        foreach ($emails as $email) {
            $value = mb_strtolower(trim((string) $email));

            if ($value !== '') {
                $unique[$value] = true;
            }
        }

        if ($unique === []) {
            return [];
        }

        $newQuery = static fn (): Builder => $withTrashed ? static::withTrashed() : static::query();

        $resolved = [];
        $hasCollationOnlyMatch = false;

        foreach ($newQuery()->whereNormalizedEmailIn(array_keys($unique))->orderBy('id')->get() as $user) {
            $key = mb_strtolower(trim((string) $user->email));

            // A candidate whose key is not an input can only match through the
            // database collation (accent/PAD SPACE), which is the only case that
            // needs the historical fallback below.
            if (! isset($unique[$key])) {
                $hasCollationOnlyMatch = true;
            }

            // Deterministic tie-break: rows arrive ordered by id, so a
            // collation-equal duplicate can never flip between requests.
            if (! isset($resolved[$key])) {
                $resolved[$key] = $user;
            }
        }

        if (! $hasCollationOnlyMatch) {
            return $resolved;
        }

        // Rare collation-only matches: the old predicate is preserved verbatim,
        // with the same deterministic id ordering for the first match.
        foreach (array_diff(array_keys($unique), array_keys($resolved)) as $email) {
            $match = $newQuery()->whereRaw('LOWER(email) = ?', [$email])->orderBy('id')->first();

            if ($match !== null) {
                $resolved[$email] = $match;
            }
        }

        return $resolved;
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
