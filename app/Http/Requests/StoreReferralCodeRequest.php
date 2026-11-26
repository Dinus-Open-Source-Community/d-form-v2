<?php

namespace App\Http\Requests;

/** Membuat kode referral baru; identitas dan atribusi wajib lengkap. */
final class StoreReferralCodeRequest extends ReferralCodeRequest
{
    /** @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>> */
    public function rules(): array
    {
        return $this->codeRules(requireIdentity: true);
    }
}
