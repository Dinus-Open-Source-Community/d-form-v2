<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tantangan verifikasi email per user (spec §6.1): maksimal satu baris per akun.
 * Kode disimpan sebagai HMAC-SHA256 sehingga plaintext tidak pernah tersimpan.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // Nullable karena plaintext hanya hidup di dalam job pengirim: HMAC satu arah,
            // jadi baris dibuat saat terbit lalu job menulis hash tepat sebelum mengirim email.
            $table->string('code_hash', 64)->nullable();
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('sends')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
