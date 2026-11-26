<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('referral_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('referral_code_id');
            $table->uuid('form_id');
            $table->uuid('form_answer_id')->nullable();
            $table->string('group_token', 32)->nullable();
            $table->uuid('user_id')->nullable();
            $table->json('participant_snapshot');
            $table->json('attribution_snapshot');
            $table->dateTime('redeemed_at');
            $table->string('status', 16)->default('valid');
            $table->timestamps();

            $table->foreign('referral_code_id')
                ->references('id')
                ->on('referral_codes')
                ->cascadeOnDelete();

            $table->foreign('form_id')
                ->references('id')
                ->on('forms')
                ->cascadeOnDelete();

            $table->foreign('form_answer_id')
                ->references('id')
                ->on('form_answers')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Satu unit submission hanya boleh punya satu redemption. NULL berulang
            // diizinkan (group row tanpa referensi jawaban) sesuai perilaku index unik.
            $table->unique('form_answer_id', 'referral_redemptions_answer_unique');
            $table->unique(['form_id', 'group_token'], 'referral_redemptions_form_group_unique');
            $table->index(['form_id', 'redeemed_at'], 'referral_redemptions_form_redeemed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_redemptions');
    }
};
