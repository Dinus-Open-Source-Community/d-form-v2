<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destructive, approval-gated drop; production requires a manual DROP after backup and monitoring approval.
 * Keep `email_logs_notification_type_index` for the QR query plan; `down()` recreates the four dropped indexes.
 */
return new class () extends Migration {
    private const APPROVAL_FLAG = 'APPLY_REDUNDANT_INDEX_DROP';

    public function up(): void
    {
        if (app()->isProduction()) {
            echo 'Skipped: production never drops indexes during boot; run the approved DROP INDEX manually.'.PHP_EOL;

            return;
        }

        if (! $this->approved()) {
            echo 'Skipped: redundant index drop needs approval ('.self::APPROVAL_FLAG.'=true).'.PHP_EOL;

            return;
        }

        // Index pengganti wajib ada lebih dulu; tanpa itu drop meninggalkan query lambat
        // (dan di MySQL bisa ditolak karena FK kehilangan index pendukung).
        // `000004` berjalan SEBELUM `000005` (urutan nama file), sehingga pada fresh install
        // pasangan H1 belum punya index covering saat migration ini jalan. Pastikan prasyaratnya
        // ada dulu dengan definisi yang sama; `000005` kemudian menjadi no-op (guard by columns).
        // Di dev/prod yang sudah menjalankan `000005`, dua panggilan ini no-op.
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_regno_cover_idx',
            ['recruitment_period_id', 'registration_number', 'submitted_at'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_full_name_submitted_idx',
            ['recruitment_period_id', 'full_name', 'submitted_at'],
        );

        $this->dropIfCoveredBy(
            'recruitment_applications',
            'rec_apps_period_stage_idx',
            'rec_apps_period_stage_cancelled_idx',
        );
        $this->dropIfCoveredBy(
            'email_logs',
            'email_logs_rec_app_idx',
            'email_logs_rec_app_type_status_idx',
        );
        $this->dropIfCoveredBy(
            'recruitment_applications',
            'rec_apps_period_regno_idx',
            'rec_apps_period_regno_cover_idx',
        );
        $this->dropIfCoveredBy(
            'recruitment_applications',
            'rec_apps_period_full_name_idx',
            'rec_apps_period_full_name_submitted_idx',
        );
    }

    public function down(): void
    {
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_stage_idx',
            ['recruitment_period_id', 'stage'],
        );
        $this->addIndexIfMissing('email_logs', 'email_logs_rec_app_idx', ['recruitment_application_id']);
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_regno_idx',
            ['recruitment_period_id', 'registration_number'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_full_name_idx',
            ['recruitment_period_id', 'full_name'],
        );
    }

    /** Drop index lama hanya bila index penggantinya ada. */
    private function dropIfCoveredBy(string $table, string $indexName, string $coveringIndex): void
    {
        if (! Schema::hasIndex($table, $indexName) || ! Schema::hasIndex($table, $coveringIndex)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }

    /**
     *
     * @param  list<string>  $columns
     *
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
            $blueprint->index($columns, $indexName);
        });
    }

    /** Approval eksplisit; `env()` dibaca runtime karena file ini tidak memakai config ter-cache. */
    private function approved(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        $value = env(self::APPROVAL_FLAG);

        if (is_bool($value)) {
            return $value;
        }

        return is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
};
