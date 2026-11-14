<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MySQL DDL is non-transactional; rollback may rebuild a million-row table, so use a maintenance window with backup and disk headroom. */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('recruitment_applications', 'tracking_token_generation')) {
            return;
        }

        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('tracking_token_generation')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recruitment_applications', 'tracking_token_generation')) {
            return;
        }

        Schema::table('recruitment_applications', function (Blueprint $table): void {
            $table->dropColumn('tracking_token_generation');
        });
    }
};
