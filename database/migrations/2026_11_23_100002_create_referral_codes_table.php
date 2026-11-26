<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('form_id');
            $table->string('code', 29);
            $table->string('prefix', 12);
            $table->string('body', 16);
            $table->string('generation_mode', 8);
            $table->uuid('created_by')->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('attribution_name', 150)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('form_id')
                ->references('id')
                ->on('forms')
                ->cascadeOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('assigned_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(['form_id', 'code'], 'referral_codes_form_code_unique');
            $table->index(['form_id', 'is_active'], 'referral_codes_form_active_index');
        });

        /*
         * Atribusi eksklusif hanya dijaga service + Form Request: CHECK pada
         * `assigned_user_id` bentrok dengan FK `ON DELETE SET NULL` (MySQL 3823),
         * dan setelah user dihapus kedua kolom memang menjadi NULL. Kuota tetap
         * dijaga database karena tidak terikat aksi FK. SQLite tidak bisa menambah
         * CHECK ke tabel yang sudah dibuat, jadi di sana kuota dijaga service.
         */
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement(
                'ALTER TABLE referral_codes ADD CONSTRAINT referral_codes_usage_within_limit '
                . 'CHECK (usage_limit IS NULL OR usage_count <= usage_limit)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
