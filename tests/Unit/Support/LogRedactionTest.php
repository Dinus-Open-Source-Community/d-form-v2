<?php

namespace Tests\Unit\Support;

use App\Support\LogRedaction;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LogRedactionTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function emailCases(): array
    {
        return [
            'normal address' => ['applicant@example.com', 'a***@example.com'],
            'single character local part' => ['a@example.com', 'a***@example.com'],
            'empty string' => ['', ''],
            'null' => [null, null],
            'malformed without at sign' => ['not-an-email', '[redacted]'],
        ];
    }

    #[DataProvider('emailCases')]
    public function test_masks_the_local_part_of_an_email(?string $email, ?string $expected): void
    {
        self::assertSame($expected, LogRedaction::email($email));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function textCases(): array
    {
        return [
            'email in sentence' => ['Send failed for applicant@example.com.', 'Send failed for [email].'],
            'password pair' => ['password=hunter2', 'password=[redacted]'],
            'token pair' => ['token=abc', 'token=[redacted]'],
            'bearer token' => ['Bearer xyz', 'Bearer [redacted]'],
            'url with userinfo' => ['https://user:pass@host/path', 'https://[credentials]@host/path'],
            'json quoted credential pair' => ['"password":"hunter2"', '"password":"[redacted]"'],
            'basic authorization value' => ['Authorization: Basic dXNlcjpwYXNz', 'Authorization: Basic [redacted]'],
            'quoted password with spaces' => ['password="my secret"', 'password="[redacted]"'],
            'userinfo without scheme' => ['user:pass@host', '[credentials]@host'],
            'userinfo password containing at sign' => ['https://user:p@ss@host', 'https://[credentials]@host'],
            'unicode local part in text' => ['josé@example.com', '[email]'],
            'quoted local part with space in text' => ['"john doe"@example.com', '[email]'],
            'ratio dimension pin' => ['ratio 3:4@2x', 'ratio [credentials]@2x'],
            'prefixed client secret' => ['client_secret=abc', 'client_secret=[redacted]'],
            'prefixed auth token' => ['auth_token=abc', 'auth_token=[redacted]'],
            'prefixed api key header' => ['x-api-key: abc123', 'x-api-key:[redacted]'],
            'prefixed secret key' => ['SECRET_KEY=abc', 'SECRET_KEY=[redacted]'],
        ];
    }

    #[DataProvider('textCases')]
    public function test_redacts_pii_and_credentials_from_free_text(string $value, string $expected): void
    {
        self::assertSame($expected, LogRedaction::text($value));
    }

    public function test_empty_text_never_throws(): void
    {
        self::assertSame('', LogRedaction::text(''));
    }

    public function test_truncates_free_text_to_the_limit(): void
    {
        $result = LogRedaction::text(str_repeat('a', 600), 20);

        self::assertLessThanOrEqual(23, mb_strlen($result));
        self::assertStringEndsWith('...', $result);
        self::assertStringNotContainsString(str_repeat('a', 21), $result);
    }

    public function test_exception_summary_omits_free_text_that_may_contain_unrecognized_pii(): void
    {
        $result = LogRedaction::exception(new RuntimeException(
            'failed for Synthetic Person; nim=EP02-NIM-482915; phone=+62 812-3456-7890; applicant@example.com password=hunter2',
        ));

        self::assertSame(RuntimeException::class.': [message redacted]', $result);
        self::assertStringNotContainsString('Synthetic Person', $result);
        self::assertStringNotContainsString('EP02-NIM-482915', $result);
        self::assertStringNotContainsString('+62 812-3456-7890', $result);
        self::assertStringNotContainsString('applicant@example.com', $result);
        self::assertStringNotContainsString('hunter2', $result);
    }

    public function test_exception_summary_respects_a_short_limit(): void
    {
        $result = LogRedaction::exception(new RuntimeException(str_repeat('b', 600)), 10);

        self::assertSame('RuntimeExc...', $result);
        self::assertLessThanOrEqual(13, mb_strlen($result));
    }
}
