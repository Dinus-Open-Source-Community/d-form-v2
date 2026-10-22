<?php

namespace Tests\Feature\Stress;

use Illuminate\Database\Connectors\MySqlConnector;
use PDO;
use Tests\TestCase;

/**
 * Memastikan koneksi mysql memakai text protocol hanya saat profil stress,
 * karena performance_schema tidak mencatat digest native prepares.
 */
class DatabasePreparedStatementsTest extends TestCase
{
    private string $originalServerValue = '';

    private bool $originalServerExisted = false;

    private string $originalEnvValue = '';

    private bool $originalEnvExisted = false;

    protected function setUp(): void
    {
        $this->captureEmulatePreparesEnvironment();

        // Diterapkan sebelum aplikasi dibuat agar config/database.php membaca flag ini saat boot.
        $_SERVER['DB_EMULATE_PREPARES'] = '1';
        $_ENV['DB_EMULATE_PREPARES'] = '1';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->restoreEmulatePreparesEnvironment();

        parent::tearDown();
    }

    public function test_emulated_prepares_enabled_by_environment(): void
    {
        $this->assertTrue($this->resolvedEmulatePrepares());
    }

    public function test_false_like_strings_keep_native_prepares(): void
    {
        foreach (['false', 'no', 'off'] as $falsyValue) {
            $_SERVER['DB_EMULATE_PREPARES'] = $falsyValue;
            $_ENV['DB_EMULATE_PREPARES'] = $falsyValue;

            $this->refreshApplication();

            $this->assertFalse(
                $this->resolvedEmulatePrepares(),
                "DB_EMULATE_PREPARES={$falsyValue} tidak boleh mengaktifkan emulated prepares.",
            );
        }
    }

    public function test_emulated_prepares_disabled_by_default(): void
    {
        unset($_SERVER['DB_EMULATE_PREPARES'], $_ENV['DB_EMULATE_PREPARES']);

        $this->refreshApplication();

        $this->assertFalse($this->resolvedEmulatePrepares());
    }

    /**
     * Nilai efektif yang diterima koneksi: opsi config digabung default connector Laravel.
     */
    private function resolvedEmulatePrepares(): bool
    {
        $connectionOptions = config('database.connections.mysql.options');
        $this->assertIsArray($connectionOptions);

        $options = (new MySqlConnector)->getOptions(['options' => $connectionOptions]);
        $emulatePrepares = $options[PDO::ATTR_EMULATE_PREPARES] ?? null;

        $this->assertIsBool($emulatePrepares);

        return $emulatePrepares === true;
    }

    /**
     * Simpan nilai DB_EMULATE_PREPARES milik shell/CI agar tearDown memulihkannya apa adanya.
     */
    private function captureEmulatePreparesEnvironment(): void
    {
        $serverValue = $_SERVER['DB_EMULATE_PREPARES'] ?? null;
        $this->originalServerExisted = is_string($serverValue);
        $this->originalServerValue = is_string($serverValue) ? $serverValue : '';

        $envValue = $_ENV['DB_EMULATE_PREPARES'] ?? null;
        $this->originalEnvExisted = is_string($envValue);
        $this->originalEnvValue = is_string($envValue) ? $envValue : '';
    }

    /**
     * Kembalikan nilai lama, atau hapus hanya bila sebelumnya memang tidak ada.
     */
    private function restoreEmulatePreparesEnvironment(): void
    {
        if ($this->originalServerExisted) {
            $_SERVER['DB_EMULATE_PREPARES'] = $this->originalServerValue;
        } else {
            unset($_SERVER['DB_EMULATE_PREPARES']);
        }

        if ($this->originalEnvExisted) {
            $_ENV['DB_EMULATE_PREPARES'] = $this->originalEnvValue;
        } else {
            unset($_ENV['DB_EMULATE_PREPARES']);
        }
    }
}
