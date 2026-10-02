<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom konten broadcast (subject + body).
     */
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->string('subject')->nullable()->after('name');
            $table->mediumText('body_html')->nullable()->after('subject');
            $table->mediumText('body_text')->nullable()->after('body_html');
        });
    }

    /**
     * Hapus kolom konten broadcast (rollback Task 3a).
     */
    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropColumn(['subject', 'body_html', 'body_text']);
        });
    }
};
