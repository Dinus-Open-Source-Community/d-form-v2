<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah link grup WA per periode oprec.
     */
    public function up(): void
    {
        Schema::table('recruitment_periods', function (Blueprint $table): void {
            $table->string('whatsapp_group_url')->nullable()->after('banner');
        });
    }

    /**
     * Hapus link grup WA (rollback Task 6a).
     */
    public function down(): void
    {
        Schema::table('recruitment_periods', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_group_url');
        });
    }
};
