<?php

namespace App\Services\Du;

/** Documented DU redeem-code response, with the raw array kept for auditing. */
final readonly class DuRedeemData
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        public string $uid,
        public string $code,
        public string $courseUid,
        public ?string $courseTitle,
        public ?string $targetEmail,
        public ?int $maxUses,
        public ?int $usedCount,
        public ?bool $isActive,
        public ?string $expiresAt,
        public ?string $createdAt,
        public array $payload,
    ) {
    }

    /**
     * Maps the documented 201 payload and returns null when any field does not match the contract.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): ?self
    {
        $uid = $payload['uid'] ?? null;
        $code = $payload['code'] ?? null;
        $courseUid = $payload['course_uid'] ?? null;
        $courseTitle = $payload['course_title'] ?? null;
        $targetEmail = $payload['target_email'] ?? null;
        $maxUses = $payload['max_uses'] ?? null;
        $usedCount = $payload['used_count'] ?? null;
        $isActive = $payload['is_active'] ?? null;
        $expiresAt = $payload['expires_at'] ?? null;
        $createdAt = $payload['created_at'] ?? null;

        if (! is_string($uid) || $uid === '' || ! is_string($code) || $code === '' || ! is_string($courseUid) || $courseUid === '') {
            return null;
        }

        if (($courseTitle !== null && ! is_string($courseTitle))
            || ($targetEmail !== null && ! is_string($targetEmail))
            || ($expiresAt !== null && ! is_string($expiresAt))
            || ($createdAt !== null && ! is_string($createdAt))
            || ($maxUses !== null && ! is_int($maxUses))
            || ($usedCount !== null && ! is_int($usedCount))
            || ($isActive !== null && ! is_bool($isActive))
        ) {
            return null;
        }

        return new self(
            uid: $uid,
            code: $code,
            courseUid: $courseUid,
            courseTitle: $courseTitle,
            targetEmail: $targetEmail,
            maxUses: $maxUses,
            usedCount: $usedCount,
            isActive: $isActive,
            expiresAt: $expiresAt,
            createdAt: $createdAt,
            payload: $payload,
        );
    }
}
