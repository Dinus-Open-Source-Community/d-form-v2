<?php

namespace Tests\Unit\Security;

use App\Support\Security\ProductionSecurityPolicy;
use App\Support\Security\SessionCookieSettings;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductionSecurityPolicyTest extends TestCase
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

    public function test_production_with_secure_httponly_and_lax_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertProductionSafe(
            new SessionCookieSettings(true, true, true, 'lax'),
        );
    }

    public function test_production_without_secure_cookie_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/SESSION_SECURE_COOKIE/');

        (new ProductionSecurityPolicy())->assertProductionSafe(
            new SessionCookieSettings(true, false, true, 'lax'),
        );
    }

    public function test_production_without_httponly_cookie_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/SESSION_HTTP_ONLY/');

        (new ProductionSecurityPolicy())->assertProductionSafe(
            new SessionCookieSettings(true, true, false, 'lax'),
        );
    }

    public function test_production_with_same_site_none_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/SESSION_SAME_SITE/');

        (new ProductionSecurityPolicy())->assertProductionSafe(
            new SessionCookieSettings(true, true, true, 'none'),
        );
    }

    public function test_production_with_null_same_site_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertProductionSafe(
            new SessionCookieSettings(true, true, true, null),
        );
    }

    public function test_non_production_never_throws(): void
    {
        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertProductionSafe(
            new SessionCookieSettings(false, false, false, 'none'),
        );
    }

    public function test_csp_off_in_production_fails(): void
    {
        $this->configureAppEnv('production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/SECURITY_CSP_MODE/');

        (new ProductionSecurityPolicy())->assertCspModeSafe('off', false);
    }

    public function test_csp_off_in_production_is_accepted_when_explicitly_allowed(): void
    {
        $this->configureAppEnv('production');

        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertCspModeSafe('off', true);
    }

    public function test_csp_report_only_in_production_is_accepted(): void
    {
        $this->configureAppEnv('production');

        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertCspModeSafe('report-only', false);
    }

    public function test_csp_off_outside_production_is_accepted(): void
    {
        $this->configureAppEnv('local');

        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertCspModeSafe('off', false);
    }

    public function test_csp_report_uri_on_the_app_host_is_accepted(): void
    {
        $this->configureAppEnv('production');

        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertCspReportUriFirstParty('https://seeyu.doscom.org/csp-report');
    }

    public function test_csp_report_uri_with_a_relative_path_is_accepted(): void
    {
        $this->configureAppEnv('production');

        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertCspReportUriFirstParty('/csp-report');
    }

    public function test_empty_csp_report_uri_is_accepted(): void
    {
        $this->configureAppEnv('production');

        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertCspReportUriFirstParty(null);
        (new ProductionSecurityPolicy())->assertCspReportUriFirstParty('');
    }

    public function test_csp_report_uri_on_a_foreign_host_fails(): void
    {
        $this->configureAppEnv('production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/SECURITY_CSP_REPORT_URI/');

        (new ProductionSecurityPolicy())->assertCspReportUriFirstParty('https://collector.example.net/csp-report');
    }

    public function test_csp_report_uri_on_a_foreign_host_is_accepted_outside_production(): void
    {
        $this->configureAppEnv('local');

        $this->expectNotToPerformAssertions();

        (new ProductionSecurityPolicy())->assertCspReportUriFirstParty('https://collector.example.net/csp-report');
    }

    /**
     * Install a minimal container whose only binding is the config repository.
     */
    private function configureAppEnv(string $env): void
    {
        $container = new Container();
        $container->instance('config', new Repository([
            'app' => ['env' => $env, 'url' => 'https://seeyu.doscom.org'],
        ]));

        Container::setInstance($container);
    }
}
