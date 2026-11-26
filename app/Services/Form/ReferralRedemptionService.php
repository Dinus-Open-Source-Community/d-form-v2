<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Validasi dan pencatatan redemption. `resolveLocked`/`redeem` mengasumsikan pemanggil
 * sudah berada di dalam transaksi submission dan memegang lock baris form; `check`
 * hanya membaca dan tidak pernah menulis.
 */
final class ReferralRedemptionService
{
    /** Alasan kode tidak bisa dipakai; pesannya berbeda per konteks pemanggil. */
    private const REASON_INACTIVE = 'inactive';
    private const REASON_NOT_STARTED = 'not_started';
    private const REASON_EXPIRED = 'expired';
    private const REASON_EXHAUSTED = 'exhausted';

    private const EXCEPTION_MESSAGES = [
        self::REASON_INACTIVE => 'This referral code is inactive.',
        self::REASON_NOT_STARTED => 'This referral code is not active yet.',
        self::REASON_EXPIRED => 'This referral code has expired.',
        self::REASON_EXHAUSTED => 'This referral code has reached its usage limit.',
    ];

    private const CHECK_MESSAGES = [
        self::REASON_INACTIVE => 'Kode referral tidak aktif.',
        self::REASON_NOT_STARTED => 'Kode referral belum aktif.',
        self::REASON_EXPIRED => 'Kode referral sudah kedaluwarsa.',
        self::REASON_EXHAUSTED => 'Kuota kode referral sudah habis.',
    ];

    /**
     * Kode yang sudah dinormalisasi dan dikunci; `null` berarti peserta tidak
     * mengirim kode sama sekali.
     *
     * @throws ValidationException
     */
    public function resolveLocked(Form $lockedForm, ?string $rawCode): ?ReferralCode
    {
        if ($rawCode === null || trim($rawCode) === '') {
            return null;
        }

        if (! $lockedForm->isReferralActive()) {
            throw $this->invalid('Referral is not enabled for this form.');
        }

        $normalized = ReferralCodeNormalizer::normalize($rawCode);

        if ($normalized === null) {
            throw $this->invalid('Referral code not found.');
        }

        $code = ReferralCode::query()
            ->where('form_id', $lockedForm->id)
            ->where('code', $normalized)
            ->lockForUpdate()
            ->first();

        if ($code === null) {
            throw $this->invalid('Referral code not found.');
        }

        $this->assertUsable($code);

        return $code;
    }

    /**
     * Pratinjau kode untuk halaman isi form. Tidak menulis apa pun: tidak ada
     * redemption, kenaikan usage, atau lock baris.
     *
     * @return array{valid: bool, message: string|null, code: ReferralCode|null}
     */
    public function check(Form $form, ?string $rawCode): array
    {
        if ($rawCode === null || trim($rawCode) === '') {
            return ['valid' => false, 'message' => null, 'code' => null];
        }

        if (! $form->isReferralActive()) {
            return ['valid' => false, 'message' => 'Kode referral tidak tersedia untuk form ini.', 'code' => null];
        }

        $normalized = ReferralCodeNormalizer::normalize($rawCode);

        $code = $normalized === null ? null : ReferralCode::query()
            ->where('form_id', $form->id)
            ->where('code', $normalized)
            ->first();

        if ($code === null) {
            return ['valid' => false, 'message' => 'Kode referral tidak ditemukan.', 'code' => null];
        }

        $reason = $this->unusableReason($code);

        return $reason === null
            ? ['valid' => true, 'message' => null, 'code' => $code]
            : ['valid' => false, 'message' => self::CHECK_MESSAGES[$reason], 'code' => null];
    }

    /**
     * @throws ValidationException
     */
    public function redeem(ReferralRedemptionContext $context): ReferralRedemption
    {
        $code = $context->code;

        try {
            $redemption = ReferralRedemption::query()->create([
                'referral_code_id' => $code->id,
                'form_id' => $context->form->id,
                'form_answer_id' => $context->answer->id,
                'group_token' => $context->groupToken,
                'user_id' => $context->answer->user_id,
                'participant_snapshot' => [
                    'name' => $context->participantName,
                    'email' => $context->participantEmail,
                    'members' => $context->members,
                ],
                'attribution_snapshot' => [
                    'assigned_user_id' => $code->assigned_user_id,
                    'assigned_name' => $code->assignedUser?->name,
                    'assigned_email' => $code->assignedUser?->email,
                    'attribution_name' => $code->attribution_name,
                    'code' => $code->code,
                ],
                'redeemed_at' => now(),
                'status' => ReferralRedemption::STATUS_VALID,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->invalid('This submission already has a referral.');
        }

        $code->increment('usage_count');

        Log::info('[referral] redemption recorded', [
            'form_id' => $context->form->id,
            'referral_code_id' => $code->id,
            'form_answer_id' => $context->answer->id,
            'unit' => $context->groupToken === null ? 'single' : 'group',
        ]);

        return $redemption;
    }

    /** @throws ValidationException */
    private function assertUsable(ReferralCode $code): void
    {
        $reason = $this->unusableReason($code);

        if ($reason === null) {
            return;
        }

        if ($reason === self::REASON_INACTIVE) {
            Log::warning('[referral] rejected inactive code', ['referral_code_id' => $code->id]);
        }

        throw $this->invalid(self::EXCEPTION_MESSAGES[$reason]);
    }

    /** Semantik periode & kuota bersama `check`; `null` berarti kode bisa dipakai. */
    private function unusableReason(ReferralCode $code): ?string
    {
        $now = now();

        if (! $code->is_active) {
            return self::REASON_INACTIVE;
        }

        if ($code->starts_at !== null && $now->lessThan($code->starts_at)) {
            return self::REASON_NOT_STARTED;
        }

        if ($code->expires_at !== null && $now->greaterThanOrEqualTo($code->expires_at)) {
            return self::REASON_EXPIRED;
        }

        if ($code->usage_limit !== null && $code->usage_count >= $code->usage_limit) {
            return self::REASON_EXHAUSTED;
        }

        return null;
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['referral_code' => $message]);
    }
}
