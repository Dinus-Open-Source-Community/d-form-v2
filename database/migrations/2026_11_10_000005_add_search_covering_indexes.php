<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Covering index untuk scan substring pencarian (BE-P2-013, lanjutan 000001). */
return new class () extends Migration {
    public function up(): void
    {
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_nim_cover_idx',
            ['recruitment_period_id', 'nim', 'submitted_at'],
        );
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
    }

    public function down(): void
    {
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_nim_cover_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_regno_cover_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_full_name_submitted_idx');
    }

    /**
     *
     * @param  list<string>  $columns
     *
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
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
