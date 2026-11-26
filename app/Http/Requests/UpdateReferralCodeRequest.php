<?php

namespace App\Http\Requests;

/** Memperbarui kode referral; identitas hanya boleh berubah selama belum pernah dipakai. */
final class UpdateReferralCodeRequest extends ReferralCodeRequest
{
    /** @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>> */
    public function rules(): array
    {
        return $this->codeRules(requireIdentity: false);
    }
}
