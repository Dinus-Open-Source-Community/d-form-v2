<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Covering index untuk agregat interview pada laporan recruitment: `WHERE recruitment_application_id IN (...period...) GROUP BY status`. */
return new class () extends Migration {
    private const TABLE = 'recruitment_interviews';

    private const INDEX = 'rec_interviews_app_status_idx';

    public function up(): void
    {
        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['recruitment_application_id', 'status'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
