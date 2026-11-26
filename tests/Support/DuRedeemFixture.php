<?php

namespace Tests\Support;

/** Documented DU redeem success payload shared by issuance test fakes. */
final class DuRedeemFixture
{
    /** @return array{uid: string, code: string, course_uid: string, course_title: string, target_email: string, max_uses: int, used_count: int, is_active: bool, expires_at: string, created_at: string} */
    public static function success(string $code = 'DU-TEST-CODE', string $courseUid = 'course-test'): array
    {
        return [
            'uid' => 'redeem-'.$code,
            'code' => $code,
            'course_uid' => $courseUid,
            'course_title' => 'Test Course',
            'target_email' => 'recipient@example.test',
            'max_uses' => 1,
            'used_count' => 0,
            'is_active' => true,
            'expires_at' => '2026-12-31T23:59:59Z',
            'created_at' => '2026-11-24T00:00:00Z',
        ];
    }
}
