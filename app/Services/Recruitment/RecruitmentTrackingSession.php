<?php

namespace App\Services\Recruitment;

use Illuminate\Support\Carbon;

final class RecruitmentTrackingSession
{
    public const APPLICATION_ID_KEY = 'oprec_tracking_application_id';

    public const AUTHENTICATED_AT_KEY = 'oprec_tracking_authenticated_at';

    public const TTL_HOURS = 24;

    public function store(string $applicationId): void
    {
        session()->regenerate();

        session([
            self::APPLICATION_ID_KEY => $applicationId,
            self::AUTHENTICATED_AT_KEY => now()->toIso8601String(),
        ]);
    }

    public function flush(): void
    {
        session()->forget([
            self::APPLICATION_ID_KEY,
            self::AUTHENTICATED_AT_KEY,
        ]);
    }

    public function applicationId(): ?string
    {
        $id = session(self::APPLICATION_ID_KEY);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function isValid(): bool
    {
        $applicationId = $this->applicationId();
        $authenticatedAt = session(self::AUTHENTICATED_AT_KEY);

        if ($applicationId === null || ! is_string($authenticatedAt) || $authenticatedAt === '') {
            return false;
        }

        return Carbon::parse($authenticatedAt)->addHours(self::TTL_HOURS)->isFuture();
    }

    public function assertMatchesApplication(string $applicationId): bool
    {
        return $this->isValid() && $this->applicationId() === $applicationId;
    }
}
