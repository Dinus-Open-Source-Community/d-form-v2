<?php

namespace Tests\Feature\Stress;

use PDO;
use Tests\TestCase;

/**
 * Memastikan koneksi mysql memakai text protocol hanya saat profil stress,
 * karena performance_schema tidak mencatat digest native prepares.
 */
class DatabasePreparedStatementsTest extends TestCase
{
    protected function setUp(): void
    {
        // Diterapkan sebelum aplikasi dibuat agar config/database.php membaca flag ini saat boot.
        $_SERVER['DB_EMULATE_PREPARES'] = '1';
        $_ENV['DB_EMULATE_PREPARES'] = '1';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['DB_EMULATE_PREPARES'], $_ENV['DB_EMULATE_PREPARES']);

        parent::tearDown();
    }

    public function test_emulated_prepares_enabled_by_environment(): void
    {
        $options = config('database.connections.mysql.options');

        $this->assertIsArray($options);
        $this->assertTrue($options[PDO::ATTR_EMULATE_PREPARES] ?? false);
    }

    public function test_emulated_prepares_disabled_by_default(): void
    {
        unset($_SERVER['DB_EMULATE_PREPARES'], $_ENV['DB_EMULATE_PREPARES']);

        $this->refreshApplication();

        $options = config('database.connections.mysql.options');

        $this->assertIsArray($options);
        // Kunci boleh absen karena array_filter membuang nilai falsy; default connector Laravel juga false.
        $this->assertFalse($options[PDO::ATTR_EMULATE_PREPARES] ?? false);
    }
}
