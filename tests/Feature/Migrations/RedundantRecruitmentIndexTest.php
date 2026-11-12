<?php

namespace Tests\Feature\Migrations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Bukti HYG-002 + HYG-004 (termasuk H1 dari `2026_11_10_000005`) pada schema hasil migrasi.
 *
 * - `rec_apps_period_regno_idx` sudah dimiliki migration `2026_11_08_000002`; test ini
 *   memastikan `migrate` benar-benar membentuknya (tidak perlu migration baru).
 * - Drop index redundan dijaga approval flag: default tidak menghapus apa pun, dan saat
 *   disetujui, index pengganti (left prefix) tetap ada sehingga FK `email_logs_rec_app_fk`
 *   masih punya index pendukung. `down()` mengembalikan index dengan nama/kolom yang sama.
 * - `email_logs_notification_type_index` dikecualikan (plan driver `qrStatusSummary()`).
 *
 * Test berjalan di SQLite `:memory:` terpisah, tidak menyentuh koneksi aplikasi/DB dev.
 */
final class RedundantRecruitmentIndexTest extends TestCase
{
    private const CONNECTION = 'recruitment_index_check_sqlite';

    private const APPROVAL_FLAG = 'APPLY_REDUNDANT_INDEX_DROP';

    private const REDUNDANT_INDEXES = [
        ['recruitment_applications', 'rec_apps_period_stage_idx'],
        ['email_logs', 'email_logs_rec_app_idx'],
        ['recruitment_applications', 'rec_apps_period_regno_idx'],
        ['recruitment_applications', 'rec_apps_period_full_name_idx'],
    ];

    private const COVERING_INDEXES = [
        ['recruitment_applications', 'rec_apps_period_stage_cancelled_idx'],
        ['email_logs', 'email_logs_rec_app_type_status_idx'],
        ['recruitment_applications', 'rec_apps_period_regno_cover_idx'],
        ['recruitment_applications', 'rec_apps_period_full_name_submitted_idx'],
    ];

    /**
     * Dipakai sebagai plan driver `qrStatusSummary()`; tidak boleh ikut terhapus.
     */
    private const RETAINED_INDEX = ['email_logs', 'email_logs_notification_type_index'];

    public function test_migrate_creates_period_registration_number_index_without_approval(): void
    {
        $this->migrate();

        foreach (self::REDUNDANT_INDEXES as [$table, $index]) {
            $this->assertTrue(
                $this->schema()->hasIndex($table, $index),
                "Drop of {$index} must stay opt-in, so {$index} must still exist after a plain migrate."
            );
        }

        $this->assertTrue($this->schema()->hasIndex('recruitment_applications', 'rec_apps_period_regno_idx'));
        $this->assertTrue($this->schema()->hasIndex(...self::RETAINED_INDEX));
    }

    public function test_approved_migration_drops_only_redundant_indexes_and_rollback_restores_them(): void
    {
        $this->migrate(true);

        foreach (self::REDUNDANT_INDEXES as [$table, $index]) {
            $this->assertFalse(
                $this->schema()->hasIndex($table, $index),
                "{$index} is redundant and must be dropped when the drop is approved."
            );
        }

        // Index pengganti tetap ada: left prefix-nya melayani query (period, stage/full_name/
        // registration_number) dan FK `email_logs_rec_app_fk` (kolom terkiri
        // recruitment_application_id).
        foreach (self::COVERING_INDEXES as [$table, $index]) {
            $this->assertTrue(
                $this->schema()->hasIndex($table, $index),
                "{$index} must survive; dropping it would leave the covering queries and FK unindexed."
            );
        }

        // C1: cardinality rendah tetapi plan driver `qrStatusSummary()` (0,8 ms vs 4,2 s).
        $this->assertTrue(
            $this->schema()->hasIndex(...self::RETAINED_INDEX),
            'email_logs_notification_type_index must be retained; it drives the QR summary plan.'
        );

        $this->assertSame(0, Artisan::call('migrate:rollback', [
            '--database' => self::CONNECTION,
            '--path' => 'database/migrations/2026_11_10_000004_drop_redundant_recruitment_indexes.php',
            '--force' => true,
        ]), 'Rollback of the drop migration failed: '.Artisan::output());

        foreach (self::REDUNDANT_INDEXES as [$table, $index]) {
            $this->assertTrue(
                $this->schema()->hasIndex($table, $index),
                "Rollback must recreate {$index} exactly."
            );
        }

        $this->assertTrue($this->schema()->hasIndex(...self::RETAINED_INDEX));
    }

    /**
     * Finding L-3: boot produksi tidak boleh men-drop index walau flag env terpasang.
     */
    public function test_production_boot_never_drops_indexes_even_with_flag(): void
    {
        $this->migrate(true, true);

        foreach ([...self::REDUNDANT_INDEXES, self::RETAINED_INDEX] as [$table, $index]) {
            $this->assertTrue(
                $this->schema()->hasIndex($table, $index),
                "{$index} must survive a production boot, flag or not."
            );
        }

        foreach (self::COVERING_INDEXES as [$table, $index]) {
            $this->assertTrue($this->schema()->hasIndex($table, $index));
        }
    }

    /**
     * Jalankan seluruh migration di koneksi SQLite sekali pakai.
     *
     * @param  bool  $production  true = boot dengan `APP_ENV=production` (hard guard harus menahan drop)
     */
    private function migrate(bool $approved = false, bool $production = false): void
    {
        config([
            'database.connections.'.self::CONNECTION => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge(self::CONNECTION);

        if ($approved) {
            $this->setApproval(true);
        }

        $previousEnvironment = $this->app['env'];

        if ($production) {
            $this->app['env'] = 'production';
        }

        try {
            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => self::CONNECTION,
                '--force' => true,
            ]), 'Initial migrate failed: '.Artisan::output());
        } finally {
            $this->app['env'] = $previousEnvironment;

            if ($approved) {
                $this->setApproval(false);
            }
        }
    }

    /**
     * Flag dibaca migration lewat `env()`, jadi ketiga sumber Env diisi dan dibersihkan lagi.
     */
    private function setApproval(bool $approved): void
    {
        $value = $approved ? 'true' : 'false';
        putenv(self::APPROVAL_FLAG.'='.$value);
        $_ENV[self::APPROVAL_FLAG] = $value;
        $_SERVER[self::APPROVAL_FLAG] = $value;
    }

    private function schema(): \Illuminate\Database\Schema\Builder
    {
        return Schema::connection(self::CONNECTION);
    }
}
