<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Index komposit untuk daftar pelamar pada tab "Peserta" halaman periode recruitment. */
return new class () extends Migration {
    private const TABLE = 'recruitment_applications';

    public function up(): void
    {
        $this->addIndexIfMissing('rec_apps_period_submitted_idx', ['recruitment_period_id', 'submitted_at']);
        $this->addIndexIfMissing('rec_apps_period_stage_submitted_idx', ['recruitment_period_id', 'stage', 'submitted_at']);
        $this->addIndexIfMissing('rec_apps_period_semester_submitted_idx', ['recruitment_period_id', 'semester', 'submitted_at']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('rec_apps_period_submitted_idx');
        $this->dropIndexIfExists('rec_apps_period_stage_submitted_idx');
        $this->dropIndexIfExists('rec_apps_period_semester_submitted_idx');
    }

    /**
     *
     * @param  list<string>  $columns
     *
     */
    private function addIndexIfMissing(string $indexName, array $columns): void
    {
        if (Schema::hasIndex(self::TABLE, $indexName)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (! Schema::hasIndex(self::TABLE, $indexName)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};
