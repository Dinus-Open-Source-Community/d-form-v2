<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->string('discount_type', 10)->nullable();
            $table->unsignedInteger('discount_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
    }
};
