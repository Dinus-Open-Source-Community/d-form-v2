<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index ronde-2 untuk query daftar yang sebelumnya full scan + filesort
 * (dibuktikan EXPLAIN di laporan docs/big-changes/event/).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'users_created_at_id_index');
        });

        Schema::table('recruitment_activity_logs', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'rec_activity_created_id_index');
        });

        Schema::table('recruitment_correction_requests', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'rec_correction_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_created_at_id_index');
        });

        Schema::table('recruitment_activity_logs', function (Blueprint $table): void {
            $table->dropIndex('rec_activity_created_id_index');
        });

        Schema::table('recruitment_correction_requests', function (Blueprint $table): void {
            $table->dropIndex('rec_correction_status_created_idx');
        });
    }
};
