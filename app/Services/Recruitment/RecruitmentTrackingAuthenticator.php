<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use Illuminate\Support\Facades\Hash;

final class RecruitmentTrackingAuthenticator
{
    public const INVALID_CREDENTIALS_MESSAGE = 'Nomor pendaftaran atau token tidak valid.';

    public function attempt(string $registrationNumber, string $trackingToken): ?RecruitmentApplication
    {
        $application = RecruitmentApplication::query()
            ->where('registration_number', $registrationNumber)
            ->first();

        if ($application === null) {
            return null;
        }

        if (! Hash::check($trackingToken, $application->tracking_token_hash)) {
            return null;
        }

        return $application;
    }
}
