<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class SafeExceptionReporter
{
    /** Bounded frame list: enough to locate the failure without turning one report into a dump. */
    private const MAX_FRAMES = 10;

    /** Log a redacted, argument-free report and stop Laravel's default logger.
     * @return false Always stops Laravel's default exception logger after this safe report.
     */
    public static function report(Throwable $exception): bool
    {
        $context = [
            'exception_class' => $exception::class,
            'message' => LogRedaction::exception($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'frames' => self::frames($exception),
        ];

        $requestId = request()->attributes->get('request_id');

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = request()->header('X-Request-ID');
        }

        if (is_string($requestId) && Str::isUuid($requestId)) {
            $context['correlation_id'] = $requestId;
        }

        Log::error('unhandled_exception', $context);

        return false;
    }

    /**
     *
     * Render trace frames as `#n class::method() @ file:line`, never including arguments.
     *
     * @return list<string>
     *
     */
    private static function frames(Throwable $exception): array
    {
        $frames = [];

        foreach (array_slice($exception->getTrace(), 0, self::MAX_FRAMES) as $index => $frame) {
            $frames[] = sprintf(
                '#%d %s%s%s() @ %s:%s',
                $index,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? '{main}',
                $frame['file'] ?? '[internal]',
                $frame['line'] ?? '0',
            );
        }

        return $frames;
    }
}
