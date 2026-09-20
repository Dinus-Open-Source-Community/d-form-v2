<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_documents', function (Blueprint $table): void {
            $table->string('instagram_follow_path', 500)->nullable()->after('portfolio_size_bytes');
            $table->string('instagram_follow_original_name')->nullable()->after('instagram_follow_path');
            $table->string('instagram_follow_mime', 100)->nullable()->after('instagram_follow_original_name');
            $table->unsignedInteger('instagram_follow_size_bytes')->nullable()->after('instagram_follow_mime');
            $table->string('twibbon_url', 500)->nullable()->after('instagram_follow_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_documents', function (Blueprint $table): void {
            $table->dropColumn([
                'instagram_follow_path',
                'instagram_follow_original_name',
                'instagram_follow_mime',
                'instagram_follow_size_bytes',
                'twibbon_url',
            ]);
        });
    }
};
