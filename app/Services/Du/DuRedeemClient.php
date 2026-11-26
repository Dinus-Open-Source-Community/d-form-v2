<?php

namespace App\Services\Du;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class DuRedeemClient
{
    public function issue(string $courseUid, string $targetEmail): DuRedeemResult
    {
        try {
            $response = Http::timeout((int) config('services.du.timeout', 10))
                ->acceptJson()->withHeaders(['X-API-Key' => (string) config('services.du.api_key')])
                ->post($this->url('/api/external/redeem-codes'), [
                    'course_uid' => $courseUid, 'target_email' => $targetEmail, 'max_uses' => 1,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('DU redeem request failed', ['exception' => $exception::class]);
            return DuRedeemResult::unknown();
        }

        if ($response->status() >= 400 && $response->status() < 500) {
            Log::warning('DU redeem rejected', ['status' => $response->status()]);
            return DuRedeemResult::rejected();
        }
        if ($response->status() !== 201) {
            Log::warning('DU redeem outcome ambiguous', ['status' => $response->status()]);
            return DuRedeemResult::unknown();
        }
        $data = $this->issuedData($response);
        if ($data === null) {
            Log::warning('DU redeem outcome ambiguous', ['status' => $response->status()]);
            return DuRedeemResult::unknown();
        }

        return DuRedeemResult::issued($data);
    }

    private function issuedData(Response $response): ?DuRedeemData
    {
        $payload = $response->json();
        if (! is_array($payload) || ($payload['success'] ?? false) !== true || ! is_array($payload['data'] ?? null)) {
            return null;
        }

        return DuRedeemData::fromArray($payload['data']);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.du.base_url'), '/') . '/' . ltrim($path, '/');
    }
}
