<?php

namespace App\Services\Form;

use App\Enums\FormPurpose;
use App\Models\Form;
use App\Models\ReferralCode;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Satu pintu tulis untuk kode referral: normalisasi, invarian atribusi, dan batas kuota. */
final class ReferralCodeService
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(Form $form, string $creatorId, array $data): ReferralCode
    {
        $prefix = (string) $this->stringValue($data, 'prefix');
        $generationMode = $this->stringValue($data, 'generation_mode') ?? 'manual';
        $body = $this->stringValue($data, 'body') ?? '';
        $attempts = $generationMode === 'random'
            ? (int) config('events.forms.referral.generate_attempts', 8)
            : 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $candidate = $generationMode === 'manual' ? $body : ReferralCodeNormalizer::generateBody();

            try {
                return ReferralCode::query()->create([
                    'form_id' => $form->id,
                    'code' => ReferralCodeNormalizer::codeFor($prefix, $candidate),
                    'prefix' => strtoupper($prefix),
                    'body' => strtoupper($candidate),
                    'generation_mode' => $generationMode,
                    'created_by' => $creatorId,
                    'usage_limit' => $this->intValue($data, 'usage_limit'),
                    'usage_count' => 0,
                    'discount_type' => $this->stringValue($data, 'discount_type'),
                    'discount_value' => $this->intValue($data, 'discount_value'),
                    'starts_at' => $this->toUtc($this->stringValue($data, 'starts_at')),
                    'expires_at' => $this->toUtc($this->stringValue($data, 'expires_at')),
                    'is_active' => $this->boolValue($data, 'is_active', true),
                    ...$this->attributionColumns($data),
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw ValidationException::withMessages([
            'body' => 'That code already exists for this form.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(ReferralCode $code, array $data): ReferralCode
    {
        return DB::transaction(function () use ($code, $data): ReferralCode {
            $locked = ReferralCode::query()->lockForUpdate()->find($code->id);

            if ($locked === null) {
                throw new ModelNotFoundException();
            }

            $this->assertWindowValid($locked, $data);
            $this->assertIdentityAllowed($locked, $data);

            if ($this->touchesIdentity($data)) {
                $prefix = $this->stringValue($data, 'prefix') ?? $locked->prefix;
                $body = $this->stringValue($data, 'body') ?? $locked->body;

                $locked->fill([
                    'prefix' => strtoupper($prefix),
                    'body' => strtoupper($body),
                    'code' => ReferralCodeNormalizer::codeFor($prefix, $body),
                    ...$this->attributionColumns($data, $locked),
                ]);
            }

            if (array_key_exists('generation_mode', $data)) {
                $locked->generation_mode = (string) $this->stringValue($data, 'generation_mode');
            }

            if (array_key_exists('usage_limit', $data)) {
                $locked->usage_limit = $this->intValue($data, 'usage_limit');
            }

            if (array_key_exists('discount_type', $data) || array_key_exists('discount_value', $data)) {
                $locked->discount_type = $this->stringValue($data, 'discount_type');
                $locked->discount_value = $this->intValue($data, 'discount_value');
            }

            if (array_key_exists('starts_at', $data)) {
                $locked->starts_at = $this->toUtc($this->stringValue($data, 'starts_at'));
            }

            if (array_key_exists('expires_at', $data)) {
                $locked->expires_at = $this->toUtc($this->stringValue($data, 'expires_at'));
            }

            if (array_key_exists('is_active', $data)) {
                $locked->is_active = $this->boolValue($data, 'is_active', true);
            }

            $this->assertWindowValid($locked, []);

            try {
                $locked->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'body' => 'That code already exists for this form.',
                ]);
            }

            return $locked;
        });
    }

    /** Kode yang sudah dipakai tidak boleh dihapus: audit trail harus tetap utuh. */
    public function delete(ReferralCode $code): void
    {
        if ($code->attributionIsLocked()) {
            throw new HttpException(409, 'A code with redemptions can only be deactivated.');
        }

        $code->delete();
    }

    /** @throws ValidationException */
    public function setEnabled(Form $form, bool $enabled): void
    {
        if ($enabled && ! in_array($form->purpose()->value, FormPurpose::registrationValues(), true)) {
            throw ValidationException::withMessages([
                'enabled' => 'Referral hanya tersedia untuk form pendaftaran atau D-Kelas.',
            ]);
        }

        $form->update(['referral_enabled' => $enabled]);
    }

    /** @return array<string, mixed> */
    public function payload(ReferralCode $code): array
    {
        $assigned = $code->assignedUser;

        return [
            'id' => $code->id,
            'code' => $code->code,
            'prefix' => $code->prefix,
            'body' => $code->body,
            'generation_mode' => $code->generation_mode,
            'attribution_mode' => $code->assigned_user_id === null ? 'name' : 'user',
            'assigned_user' => $assigned === null ? null : [
                'id' => $assigned->id,
                'name' => $assigned->name,
                'email' => $assigned->email,
            ],
            'attribution_name' => $code->attribution_name,
            'usage_limit' => $code->usage_limit,
            'usage_count' => $code->usage_count,
            'discount_type' => $code->discount_type?->value,
            'discount_value' => $code->discount_value,
            'remaining' => $code->remainingUsage(),
            'starts_at' => $this->toLocalString($code->starts_at),
            'expires_at' => $this->toLocalString($code->expires_at),
            'is_active' => $code->is_active,
            'can_edit_identity' => ! $code->attributionIsLocked(),
            'can_delete' => ! $code->attributionIsLocked(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function payloads(Form $form): array
    {
        return $form->referralCodes()
            ->with('assignedUser:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ReferralCode $code): array => $this->payload($code))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data */
    private function touchesIdentity(array $data): bool
    {
        foreach (['prefix', 'body', 'generation_mode', 'attribution_mode', 'assigned_user_id', 'attribution_name'] as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identitas dan atribusi terkunci setelah pemakaian pertama; nilai yang sama
     * tetap diterima agar UI boleh mengirim blok penuh.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertIdentityAllowed(ReferralCode $locked, array $data): void
    {
        if (! $locked->attributionIsLocked() || ! $this->touchesIdentity($data)) {
            return;
        }

        $errors = [];
        $prefix = $this->stringValue($data, 'prefix');
        $body = $this->stringValue($data, 'body');
        $mode = $this->stringValue($data, 'attribution_mode');

        if ($prefix !== null && strtoupper($prefix) !== $locked->prefix) {
            $errors['prefix'] = 'The prefix is locked after the first redemption.';
        }

        if ($body !== null && strtoupper($body) !== $locked->body) {
            $errors['body'] = 'The body is locked after the first redemption.';
        }

        if ($mode === 'name' && $this->stringValue($data, 'attribution_name') !== $locked->attribution_name) {
            $errors['attribution_name'] = 'The attribution is locked after the first redemption.';
        }

        if ($mode === 'user' && $this->stringValue($data, 'assigned_user_id') !== $locked->assigned_user_id) {
            $errors['assigned_user_id'] = 'The attribution is locked after the first redemption.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertWindowValid(ReferralCode $locked, array $data): void
    {
        $startsAt = array_key_exists('starts_at', $data)
            ? $this->toUtc($this->stringValue($data, 'starts_at'))
            : $locked->starts_at;
        $expiresAt = array_key_exists('expires_at', $data)
            ? $this->toUtc($this->stringValue($data, 'expires_at'))
            : $locked->expires_at;

        if ($startsAt !== null && $expiresAt !== null && $startsAt->greaterThanOrEqualTo($expiresAt)) {
            throw ValidationException::withMessages([
                'expires_at' => 'The end date must be after the start date.',
            ]);
        }

        $usageLimit = array_key_exists('usage_limit', $data)
            ? $this->intValue($data, 'usage_limit')
            : $locked->usage_limit;

        if ($usageLimit !== null && $usageLimit < $locked->usage_count) {
            throw ValidationException::withMessages([
                'usage_limit' => 'The usage limit cannot be lower than the usage count.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{assigned_user_id: string|null, attribution_name: string|null}
     */
    private function attributionColumns(array $data, ?ReferralCode $existing = null): array
    {
        $mode = $this->stringValue($data, 'attribution_mode');

        if ($mode === 'user') {
            return [
                'assigned_user_id' => $this->stringValue($data, 'assigned_user_id'),
                'attribution_name' => null,
            ];
        }

        if ($mode === 'name') {
            return [
                'assigned_user_id' => null,
                'attribution_name' => $this->stringValue($data, 'attribution_name'),
            ];
        }

        if ($existing === null) {
            return ['assigned_user_id' => null, 'attribution_name' => null];
        }

        return [
            'assigned_user_id' => $existing->assigned_user_id,
            'attribution_name' => $existing->attribution_name,
        ];
    }

    private function toUtc(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timezone = (string) config('events.forms.referral.timezone', 'Asia/Jakarta');
        $parsed = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, $timezone);

        return $parsed instanceof CarbonImmutable ? $parsed->utc() : null;
    }

    private function toLocalString(?DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::instance($value)
            ->setTimezone((string) config('events.forms.referral.timezone', 'Asia/Jakarta'))
            ->format('Y-m-d\TH:i');
    }

    /** @param array<string, mixed> $data */
    private function stringValue(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** @param array<string, mixed> $data */
    private function intValue(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $data */
    private function boolValue(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }
}
