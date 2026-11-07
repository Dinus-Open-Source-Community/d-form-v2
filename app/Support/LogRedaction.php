<?php

namespace App\Support;

use Illuminate\Support\Str;
use Throwable;

/**
 * Deterministic redaction for log contexts and public error messages.
 *
 * Only formatting is performed here: nothing is written to logs, storage, or the database,
 * so the same value always produces the same output.
 */
final class LogRedaction
{
    private const EMAIL_PATTERN = '/(?:"[^"]+"|[\p{L}0-9._%+\-]+)@[\p{L}0-9.\-]+\.[\p{L}]{2,}/iu';

    private const USERINFO_PATTERN = '~([a-z][a-z0-9+.\-]*://)?[^/\s:@]+:[^/\s]*@~i';

    private const BEARER_PATTERN = '#Bearer\s+[A-Za-z0-9._~+/=\-]+#i';

    private const BASIC_PATTERN = '#\bBasic\s+[A-Za-z0-9+/]{8,}={0,2}#i';

    private const CREDENTIAL_NAME = '(?:[a-z0-9]+[_-])*(?:password|passwd|pwd|secret|secret[_-]?key|token|api[_-]?key|apikey|access[_-]?key|private[_-]?key|authorization)';

    private const QUOTED_CREDENTIAL_PATTERN = '/(["\']?)\b('.self::CREDENTIAL_NAME.')\b\1\s*([=:])\s*(["\'])(.*?)\4/i';

    private const BARE_CREDENTIAL_PATTERN = '/(["\']?)\b('.self::CREDENTIAL_NAME.')\b\1\s*([=:])\s*(?!\[redacted\]|Basic\b|Bearer\b)([^\s"\';&,]+)/i';

    /**
     * Mask the local part of an address while keeping the domain for operational grouping.
     *
     * Null and empty values are returned unchanged; values without a usable `@` are fully redacted.
     */
    public static function email(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return $email;
        }

        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return '[redacted]';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }

    /**
     * Redact emails, credentials, bearer/basic tokens, and URL userinfo from free text, then truncate.
     *
     * Never throws on empty input; the result is safe to place in a log context or error message.
     */
    public static function text(string $value, int $limit = 500): string
    {
        $redacted = preg_replace(self::USERINFO_PATTERN, '$1[credentials]@', $value) ?? '';
        $redacted = preg_replace(self::BEARER_PATTERN, 'Bearer [redacted]', $redacted) ?? '';
        $redacted = preg_replace(self::BASIC_PATTERN, 'Basic [redacted]', $redacted) ?? '';
        $redacted = preg_replace(self::QUOTED_CREDENTIAL_PATTERN, '$1$2$1$3$4[redacted]$4', $redacted) ?? '';
        $redacted = preg_replace(self::BARE_CREDENTIAL_PATTERN, '$1$2$1$3[redacted]', $redacted) ?? '';
        $redacted = preg_replace(self::EMAIL_PATTERN, '[email]', $redacted) ?? '';

        return Str::limit($redacted, $limit);
    }

    /**
     * Render an exception as `{class}: {redacted message}` bounded by the given limit.
     */
    public static function exception(Throwable $exception, int $limit = 500): string
    {
        return Str::limit(
            $exception::class.': '.self::text($exception->getMessage(), $limit),
            $limit,
        );
    }
}
