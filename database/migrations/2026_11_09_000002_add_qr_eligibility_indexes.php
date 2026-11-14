<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Index untuk hitungan eligibility QR interview OpRec (BE-P2-003). */
return new class () extends Migration {
    public function up(): void
    {
        $this->addIndexIfMissing(
            'email_logs',
            'email_logs_rec_app_type_status_idx',
            ['recruitment_application_id', 'notification_type', 'status'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_stage_cancelled_idx',
            ['recruitment_period_id', 'stage', 'cancelled_at'],
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('email_logs', 'email_logs_rec_app_type_status_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_stage_cancelled_idx');
    }

    /**
     *
     * @param  list<string>  $columns
     *
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        // Cek berdasarkan kolom (bukan nama) supaya index identik yang sudah ada
        // dengan nama lain tidak terduplikasi saat migrate dijalankan ulang.
        if (Schema::hasIndex($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }
};
