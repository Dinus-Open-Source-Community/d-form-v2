<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('du_redeem_issuances', function (Blueprint $table): void {
            $table->string('du_uid')->nullable()->index();
            $table->string('course_title')->nullable();
            $table->string('target_email')->nullable();
            $table->unsignedSmallInteger('max_uses')->nullable();
            $table->unsignedSmallInteger('used_count')->nullable();
            $table->boolean('is_active')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('du_created_at')->nullable();
            $table->json('response_payload')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('du_redeem_issuances', function (Blueprint $table): void {
            $table->dropIndex(['du_uid']);
            $table->dropColumn([
                'du_uid',
                'course_title',
                'target_email',
                'max_uses',
                'used_count',
                'is_active',
                'expires_at',
                'du_created_at',
                'response_payload',
            ]);
        });
    }
};
