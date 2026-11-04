<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_logs', 'delivery_key')) {
                // Nullable so historical rows remain valid; unique so one delivery identity
                // cannot be reserved twice by a retrying worker.
                $table->string('delivery_key', 64)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('email_logs', 'delivery_key')) {
                $table->dropUnique(['delivery_key']);
                $table->dropColumn('delivery_key');
            }
        });
    }
};
