<?php

namespace App\Http\Requests;

use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Requests\Concerns\ConfirmsTwoFactorCredentials;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Langkah pemulihan PIN: konfirmasi OTP email (`code`) lalu set PIN baru (`pin`).
 * Langkah PIN baru hanya boleh berjalan setelah OTP terbukti pada sesi yang sama.
 */
class RecoveryTwoFactorRequest extends FormRequest
{
    use ConfirmsTwoFactorCredentials;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if (! $this->isSettingNewPin()) {
            return true;
        }

        $confirmedUserId = $this->session()->get(EnsureTwoFactorVerified::SESSION_RECOVERY_USER_ID);

        return is_string($confirmedUserId) && $confirmedUserId === (string) $user->getAuthIdentifier();
    }

    /**
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     *
     */
    public function rules(): array
    {
        if ($this->isSettingNewPin()) {
            return ['pin' => $this->pinRules()];
        }

        return ['code' => ['required', 'string', 'digits:'.$this->codeLength()]];
    }

    /**
     *
     * @return array<string, string>
     *
     */
    public function messages(): array
    {
        return [
            ...$this->pinMessages(),
            'code.required' => 'Masukkan kode verifikasi.',
            'code.string' => 'Kode verifikasi harus berupa angka.',
            'code.digits' => 'Kode verifikasi harus terdiri dari '.$this->codeLength().' angka.',
        ];
    }

    /** Route `two-factor.recovery.pin` adalah langkah set PIN baru, bukan konfirmasi OTP. */
    private function isSettingNewPin(): bool
    {
        return $this->routeIs('two-factor.recovery.pin');
    }

    /** Panjang kode OTP mengikuti `config('otp.length')`. */
    private function codeLength(): int
    {
        return max(1, (int) config('otp.length'));
    }
}
