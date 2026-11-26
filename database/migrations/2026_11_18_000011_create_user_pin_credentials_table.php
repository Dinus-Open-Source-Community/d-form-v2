<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kredensial PIN faktor kedua per user (spec §6.1): PIN disimpan sebagai hash KDF,
 * bukan plaintext maupun ciphertext terbalikkan.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_pin_credentials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('pin_hash');
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('enabled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pin_credentials');
    }
};
