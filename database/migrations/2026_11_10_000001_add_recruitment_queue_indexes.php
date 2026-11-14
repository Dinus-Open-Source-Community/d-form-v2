<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Index pendukung EP-03 untuk halaman periode recruitment (20.000 applicant per periode pada dataset 1 juta baris). */
return new class () extends Migration {
    public function up(): void
    {
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_queue_counts_idx',
            ['recruitment_period_id', 'cancelled_at', 'stage', 'result', 'revision_required'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_secondary_division_idx',
            ['recruitment_period_id', 'secondary_division_id'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_full_name_idx',
            ['recruitment_period_id', 'full_name'],
        );
        $this->addIndexIfMissing(
            'recruitment_screenings',
            'rec_screenings_decision_app_idx',
            ['decision', 'recruitment_application_id'],
        );
        $this->addIndexIfMissing(
            'recruitment_correction_requests',
            'rec_correction_app_status_idx',
            ['recruitment_application_id', 'status'],
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_queue_counts_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_secondary_division_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_full_name_idx');
        $this->dropIndexIfExists('recruitment_screenings', 'rec_screenings_decision_app_idx');
        $this->dropIndexIfExists('recruitment_correction_requests', 'rec_correction_app_status_idx');
    }

    /**
     *
     * @param  list<string>  $columns
     *
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        // Bandingkan kolom, bukan nama: index identik dengan nama lain (mis. hasil
        // ALTER manual saat benchmark) tidak boleh diduplikasi.
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
