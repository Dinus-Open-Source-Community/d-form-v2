<?php

namespace Tests\Feature\Jobs;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Executable guard for AUDIT-P2-014 (failed_jobs.payload is stored verbatim).
 *
 * Redaction only covers the exception column, so a queue payload must never carry free-form
 * text unless the job implements ShouldBeEncrypted. This test enumerates every class under
 * app/Jobs and fails when a property is declared with a shape that can hold arbitrary text.
 */
class QueueJobPayloadPrivacyTest extends TestCase
{
    /**
     * Queue plumbing properties: retry policy from the shared trait and the Queueable trait's
     * transport state. They are framework-managed, never domain data.
     */
    private const PLUMBING_PROPERTIES = [
        'job',
        'connection',
        'queue',
        'messageGroup',
        'deduplicator',
        'delay',
        'afterCommit',
        'middleware',
        'chained',
        'chainConnection',
        'chainQueue',
        'chainCatchCallbacks',
        'tries',
        'backoff',
        'timeout',
        'maxExceptions',
        'failOnTimeout',
        'retryUntil',
    ];

    /**
     * String properties that are verified fixed identifiers rather than user-supplied text.
     * UUID-shaped values are additionally accepted through the `Id` suffix.
     */
    private const SAFE_STRING_PROPERTIES = [
        'deliveryKey' => true,
        'templateKey' => true,
        'type' => true,
        'decision' => true,
        'sentAt' => true,
        'uuid' => true,
        'ulid' => true,
    ];

    public function test_unencrypted_jobs_only_carry_identifier_shaped_payload_properties(): void
    {
        $jobClasses = $this->jobClasses();
        self::assertGreaterThanOrEqual(10, count($jobClasses), 'No job classes were discovered.');

        $violations = [];
        $encryptedJobs = 0;

        foreach ($jobClasses as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->implementsInterface(ShouldBeEncrypted::class)) {
                $encryptedJobs++;
                continue;
            }

            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() || in_array($property->getName(), self::PLUMBING_PROPERTIES, true)) {
                    continue;
                }

                if ($this->isPayloadSafe($property->getName(), $property)) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s::$%s (%s) can hold free-form text; implement ShouldBeEncrypted or use an identifier-shaped value',
                    $class,
                    $property->getName(),
                    (string) $property->getType(),
                );
            }
        }

        self::assertSame([], $violations, "Unencrypted queue payloads must contain no free-form text:\n".implode("\n", $violations));
        self::assertGreaterThanOrEqual(1, $encryptedJobs, 'Expected at least one encrypted job in the enumeration.');
    }

    private function isPayloadSafe(string $name, ReflectionProperty $property): bool
    {
        $type = $property->getType();

        if (! $type instanceof ReflectionNamedType) {
            // Union/intersection or untyped properties cannot be verified statically.
            return false;
        }

        if ($type->isBuiltin()) {
            return match ($type->getName()) {
                'int', 'float', 'bool' => true,
                'string' => str_ends_with($name, 'Id') || isset(self::SAFE_STRING_PROPERTIES[$name]),
                default => false,
            };
        }

        $class = $type->getName();

        return is_a($class, \BackedEnum::class, true)
            || is_a($class, \UnitEnum::class, true)
            || is_a($class, \DateTimeInterface::class, true);
    }

    /**
     * @return list<class-string>
     */
    private function jobClasses(): array
    {
        $classes = [];

        foreach (File::allFiles(app_path('Jobs')) as $file) {
            $relative = Str::of($file->getRelativePathname())
                ->replace('.php', '')
                ->replace(DIRECTORY_SEPARATOR, '\\')
                ->toString();
            $class = 'App\\Jobs\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isAnonymous()) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }
}
