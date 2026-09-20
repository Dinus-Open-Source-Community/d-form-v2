<?php

namespace App\Services\Recruitment;

final class RecruitmentTrackingPortalUrlBuilder
{
    public function loginUrl(string $registrationNumber, string $trackingToken): string
    {
        $base = route('recruitment.track.login', absolute: false);

        return url($base.'?'.http_build_query([
            'reg' => $registrationNumber,
            'token' => $trackingToken,
        ]));
    }

    public function loginButtonHtml(string $url, string $label = 'Lihat progress pendaftaran'): string
    {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return '<p style="margin:28px 0;text-align:center;">'
            .'<a href="'.$safeUrl.'" '
            .'style="display:inline-block;background-color:#2563eb;color:#ffffff;font-weight:600;text-decoration:none;'
            .'padding:12px 28px;border-radius:8px;font-size:15px;line-height:1.4;">'
            .$safeLabel
            .'</a></p>';
    }
}
