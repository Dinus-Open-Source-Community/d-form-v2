<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class RequestCorrelationTest extends TestCase
{
    private const PROBE = '/__test/request-correlation';

    private const EXCEPTION_PROBE = '/__test/request-correlation-exception';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get(self::PROBE, static function (Request $request) {
            Log::info('request_correlation_probe');

            return response()->json([
                'request_id' => $request->attributes->get('request_id'),
            ]);
        });

        Route::get(self::EXCEPTION_PROBE, static function (): never {
            throw new RuntimeException('synthetic request correlation failure');
        });
    }

    public function test_generates_request_id_when_header_is_absent_AUDIT_P2_005(): void
    {
        $observedContexts = [];
        Log::listen(static function (MessageLogged $event) use (&$observedContexts): void {
            if ($event->message === 'request_correlation_probe') {
                $observedContexts[] = $event->context;
            }
        });

        $response = $this->get(self::PROBE);
        $requestId = $response->headers->get('X-Request-ID');

        self::assertIsString($requestId);
        self::assertTrue(Str::isUuid($requestId));
        $response->assertOk()->assertJsonPath('request_id', $requestId);
        self::assertCount(1, $observedContexts);
        self::assertSame($requestId, $observedContexts[0]['request_id'] ?? null);
    }

    public function test_preserves_valid_request_id_AUDIT_P2_005(): void
    {
        $requestId = (string) Str::uuid();

        $response = $this->withHeader('X-Request-ID', $requestId)->get(self::PROBE);

        $response->assertOk()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('request_id', $requestId);
    }

    public function test_replaces_invalid_request_id_AUDIT_P2_005(): void
    {
        $invalidRequestId = 'invalid-request-id';

        $response = $this->withHeader('X-Request-ID', $invalidRequestId)->get(self::PROBE);
        $requestId = $response->headers->get('X-Request-ID');

        self::assertIsString($requestId);
        self::assertTrue(Str::isUuid($requestId));
        self::assertNotSame($invalidRequestId, $requestId);
        self::assertStringNotContainsString($invalidRequestId, (string) $response->getContent());
        $response->assertOk()->assertJsonPath('request_id', $requestId);
    }

    public function test_exception_report_contains_global_request_id_AUDIT_P2_005(): void
    {
        Log::spy();

        $response = $this->get(self::EXCEPTION_PROBE);
        $requestId = $response->headers->get('X-Request-ID');

        self::assertIsString($requestId);
        self::assertTrue(Str::isUuid($requestId));
        Log::shouldHaveReceived('error')->once()->withArgs(
            static function (string $event, array $context) use ($requestId): bool {
                return $event === 'unhandled_exception'
                    && ($context['correlation_id'] ?? null) === $requestId;
            },
        );
    }
}
