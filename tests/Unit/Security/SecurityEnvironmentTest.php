<?php

namespace Tests\Unit\Security;

use App\Support\Security\SecurityEnvironment;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SecurityEnvironmentTest extends TestCase
{
    private ?Container $previousContainer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $this->configureAppEnv('testing');
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    public function test_environment_normalizes_the_configured_value(): void
    {
        $this->configureAppEnv('PRODUCTION ');

        $this->assertSame('production', SecurityEnvironment::environment());
        $this->assertTrue(SecurityEnvironment::isProduction());
    }

    public function test_environment_returns_the_configured_app_env(): void
    {
        $this->configureAppEnv('staging');

        $this->assertSame('staging', SecurityEnvironment::environment());
        $this->assertFalse(SecurityEnvironment::isProduction());
        $this->assertFalse(SecurityEnvironment::isLocal());
    }

    public function test_environment_falls_back_to_the_env_variable_when_config_is_blank(): void
    {
        // phpunit.xml sets APP_ENV=testing in $_SERVER, which the env repository reads.
        $this->configureAppEnv('');

        $this->assertSame('testing', SecurityEnvironment::environment());
    }

    public function test_environment_falls_back_to_the_env_variable_when_app_config_is_missing(): void
    {
        $this->configureAppEnv(null);

        $this->assertSame('testing', SecurityEnvironment::environment());
    }

    public function test_environment_treats_a_blank_env_variable_as_production(): void
    {
        // `env('APP_ENV', 'production')` returns '' when the variable exists but is empty.
        // Blank means "unset", never "not production" - otherwise every guard is skipped.
        $previousServer = $_SERVER['APP_ENV'] ?? null;
        $previousEnv = $_ENV['APP_ENV'] ?? null;
        $previousPutenv = getenv('APP_ENV');

        $_SERVER['APP_ENV'] = '';
        $_ENV['APP_ENV'] = '';
        putenv('APP_ENV=');

        try {
            $this->configureAppEnv(null);

            $this->assertSame('production', SecurityEnvironment::environment());
            $this->assertTrue(SecurityEnvironment::isProduction());
        } finally {
            $this->restoreEnvVariable('APP_ENV', $previousServer, $previousEnv, $previousPutenv);
        }
    }

    public function test_is_local_only_matches_local(): void
    {
        $this->configureAppEnv('local');
        $this->assertTrue(SecurityEnvironment::isLocal());

        $this->configureAppEnv('production');
        $this->assertFalse(SecurityEnvironment::isLocal());

        $this->configureAppEnv(' LOCAL ');
        $this->assertTrue(SecurityEnvironment::isLocal());
    }

    public function test_flag_uses_default_for_null_and_blank_values(): void
    {
        $this->assertTrue(SecurityEnvironment::flag(null, true));
        $this->assertFalse(SecurityEnvironment::flag(null, false));
        $this->assertTrue(SecurityEnvironment::flag('', true));
        $this->assertFalse(SecurityEnvironment::flag('', false));
        $this->assertTrue(SecurityEnvironment::flag('   ', true));
        $this->assertFalse(SecurityEnvironment::flag('   ', false));
    }

    public function test_flag_preserves_booleans(): void
    {
        $this->assertTrue(SecurityEnvironment::flag(true, false));
        $this->assertFalse(SecurityEnvironment::flag(false, true));
    }

    public function test_flag_parses_recognized_boolean_spellings(): void
    {
        $this->assertTrue(SecurityEnvironment::flag('true', false));
        $this->assertFalse(SecurityEnvironment::flag('false', true));
        $this->assertTrue(SecurityEnvironment::flag('1', false));
        $this->assertFalse(SecurityEnvironment::flag('0', true));
        $this->assertTrue(SecurityEnvironment::flag('on', false));
        $this->assertFalse(SecurityEnvironment::flag('off', true));
        $this->assertTrue(SecurityEnvironment::flag('yes', false));
        $this->assertFalse(SecurityEnvironment::flag('no', true));
        $this->assertTrue(SecurityEnvironment::flag(1, false));
        $this->assertFalse(SecurityEnvironment::flag(0, true));
    }

    public function test_flag_falls_back_to_default_for_unrecognized_values(): void
    {
        $this->assertTrue(SecurityEnvironment::flag('garbage', true));
        $this->assertFalse(SecurityEnvironment::flag('garbage', false));
    }

    public function test_same_site_returns_default_for_null_and_blank(): void
    {
        $this->assertSame('lax', SecurityEnvironment::sameSite(null));
        $this->assertSame('lax', SecurityEnvironment::sameSite(''));
        $this->assertSame('lax', SecurityEnvironment::sameSite('   '));
        $this->assertSame('strict', SecurityEnvironment::sameSite(null, 'strict'));
    }

    public function test_same_site_normalizes_case_and_whitespace(): void
    {
        $this->assertSame('lax', SecurityEnvironment::sameSite(' Lax '));
        $this->assertSame('strict', SecurityEnvironment::sameSite('STRICT'));
        $this->assertSame('none', SecurityEnvironment::sameSite('None'));
    }

    public function test_same_site_rejects_typos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SESSION_SAME_SITE/');

        SecurityEnvironment::sameSite('laks');
    }

    public function test_same_site_rejects_non_string_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SESSION_SAME_SITE/');

        SecurityEnvironment::sameSite(['lax']);
    }

    public function test_csp_mode_returns_the_default_for_null_and_blank(): void
    {
        $this->assertSame('enforce', SecurityEnvironment::cspMode(null));
        $this->assertSame('enforce', SecurityEnvironment::cspMode(''));
        $this->assertSame('enforce', SecurityEnvironment::cspMode('   '));
        $this->assertSame('report-only', SecurityEnvironment::cspMode(null, 'report-only'));
    }

    public function test_csp_mode_normalizes_case_and_whitespace(): void
    {
        $this->assertSame('enforce', SecurityEnvironment::cspMode(' ENFORCE '));
        $this->assertSame('report-only', SecurityEnvironment::cspMode('Report-Only'));
        $this->assertSame('off', SecurityEnvironment::cspMode('Off'));
    }

    public function test_csp_mode_rejects_unknown_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SECURITY_CSP_MODE/');

        SecurityEnvironment::cspMode('enforced');
    }

    /**
     * Laravel's Env mapper turns SECURITY_CSP_MODE=false/true into booleans; those must
     * fail loudly instead of producing an empty string that could disable CSP.
     */
    public function test_csp_mode_rejects_non_string_values(): void
    {
        foreach ([false, true, 0, 1, ['enforce']] as $value) {
            try {
                SecurityEnvironment::cspMode($value);
                $this->fail('Non-string CSP mode must throw: '.get_debug_type($value));
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('SECURITY_CSP_MODE', $exception->getMessage());
            }
        }
    }

    /**
     * Restore one environment variable across the $_SERVER, $_ENV and putenv adapters.
     */
    private function restoreEnvVariable(string $name, mixed $server, mixed $env, string|false $putenv): void
    {
        $this->restoreArrayValue($_SERVER, $name, $server);
        $this->restoreArrayValue($_ENV, $name, $env);

        if ($putenv === false) {
            putenv($name);
        } else {
            putenv($name.'='.$putenv);
        }
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function restoreArrayValue(array &$target, string $name, mixed $value): void
    {
        if ($value === null) {
            unset($target[$name]);

            return;
        }

        $target[$name] = $value;
    }

    /**
     * Install a minimal container whose only binding is the config repository.
     *
     * Null leaves `app.env` unset, exercising the env() fallback.
     */
    private function configureAppEnv(?string $env): void
    {
        $appConfig = $env === null ? [] : ['env' => $env];

        $container = new Container();
        $container->instance('config', new Repository(['app' => $appConfig]));

        Container::setInstance($container);
    }
}
