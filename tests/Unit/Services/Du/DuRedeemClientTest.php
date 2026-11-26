<?php

namespace Tests\Unit\Services\Du;

use App\Enums\DuRedeemStatus;
use App\Services\Du\DuRedeemClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DuRedeemFixture;
use Tests\TestCase;

class DuRedeemClientTest extends TestCase
{
    public function test_sends_exact_server_side_request_and_accepts_201(): void
    {
        config(['services.du.base_url' => 'https://du.example.test/', 'services.du.api_key' => 'secret']);
        Http::fake(['*' => Http::response(['success' => true, 'data' => DuRedeemFixture::success('CODE', 'course')], 201)]);
        $result = (new DuRedeemClient())->issue('course', 'person@example.test');
        $this->assertSame(DuRedeemStatus::Issued, $result->status);
        $this->assertSame('CODE', $result->code);
        $this->assertSame('CODE', $result->data?->code);
        $this->assertSame('course', $result->data?->courseUid);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://du.example.test/api/external/redeem-codes' && $request->data() === ['course_uid' => 'course', 'target_email' => 'person@example.test', 'max_uses' => 1] && $request->header('X-API-Key')[0] === 'secret' && !array_key_exists('expires_at', $request->data()));
    }

    #[DataProvider('rejectionStatuses')]
    public function test_4xx_are_definite_rejections(int $status): void
    {
        Http::fake(['*' => Http::response([], $status)]);
        $this->assertSame(DuRedeemStatus::Failed, (new DuRedeemClient())->issue('c', 'e')->status);
    }

    public static function rejectionStatuses(): array
    {
        return [[400], [401], [404], [409]];
    }

    public function test_5xx_timeout_and_malformed_success_are_unknown_and_redacted(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response('raw-secret-body', 500)]);
        $this->assertSame(DuRedeemStatus::Unknown, (new DuRedeemClient())->issue('c', 'e')->status);
        Http::fake(fn (): never => throw new ConnectionException('timeout'));
        $this->assertSame(DuRedeemStatus::Unknown, (new DuRedeemClient())->issue('c', 'e')->status);
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 201)]);
        $this->assertSame(DuRedeemStatus::Unknown, (new DuRedeemClient())->issue('c', 'e')->status);
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['code' => 'CODE']], 201)]);
        $malformed = (new DuRedeemClient())->issue('c', 'e');
        $this->assertSame(DuRedeemStatus::Unknown, $malformed->status);
        $this->assertNull($malformed->code);
        $this->assertNull($malformed->data);
        Http::fake(['*' => Http::response(['success' => false, 'data' => DuRedeemFixture::success()], 201)]);
        $this->assertSame(DuRedeemStatus::Unknown, (new DuRedeemClient())->issue('c', 'e')->status);
        Log::assertNotLogged('raw-secret-body');
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => !str_contains((string) json_encode($context), 'secret') && !str_contains((string) json_encode($context), 'CODE'));
    }
}
