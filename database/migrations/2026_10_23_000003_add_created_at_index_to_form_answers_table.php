<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks rentang waktu untuk laporan registrasi (tren bulanan) yang memfilter
 * `form_answers.created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_answers', function (Blueprint $table): void {
            $table->index('created_at', 'form_answers_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('form_answers', function (Blueprint $table): void {
            $table->dropIndex('form_answers_created_at_index');
        });
    }
};
