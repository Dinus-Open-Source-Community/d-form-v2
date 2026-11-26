<?php

namespace App\Models;

use App\Enums\ReferralDiscountType;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralCode extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'form_id',
        'code',
        'prefix',
        'body',
        'generation_mode',
        'created_by',
        'assigned_user_id',
        'attribution_name',
        'usage_limit',
        'usage_count',
        'starts_at',
        'expires_at',
        'is_active',
        'discount_type',
        'discount_value',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'discount_value' => 'integer',
            'discount_type' => ReferralDiscountType::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(ReferralRedemption::class);
    }

    /** Aktif, di dalam jendela tanggal, dan masih punya sisa kuota. */
    public function isUsableAt(DateTimeInterface $moment): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $instant = CarbonImmutable::instance($moment);

        if ($this->starts_at !== null && $instant->lessThan($this->starts_at)) {
            return false;
        }

        if ($this->expires_at !== null && $instant->greaterThanOrEqualTo($this->expires_at)) {
            return false;
        }

        return $this->usage_limit === null || $this->usage_count < $this->usage_limit;
    }

    public function remainingUsage(): ?int
    {
        return $this->usage_limit === null ? null : max(0, $this->usage_limit - $this->usage_count);
    }

    /** Nama atribusi untuk tampilan; snapshot redemption tetap sumber laporan historis. */
    public function attributionLabel(): string
    {
        return $this->assignedUser?->name ?? $this->attribution_name ?? '';
    }

    /** Identitas dan atribusi terkunci setelah pemakaian pertama. */
    public function attributionIsLocked(): bool
    {
        return $this->usage_count > 0;
    }
}
