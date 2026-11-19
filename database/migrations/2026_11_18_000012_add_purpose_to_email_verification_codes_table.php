<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tabel tantangan email dipakai dua alur: verifikasi alamat akun dan pemulihan PIN.
 * `purpose` memisahkannya tanpa tabel kedua (spec §6.2).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table): void {
            $table->string('purpose', 32)->default('email_verification')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table): void {
            $table->dropColumn('purpose');
        });
    }
};
