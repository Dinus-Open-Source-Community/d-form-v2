<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recruitment_documents', 'instagram_follow_path')) {
            Schema::table('recruitment_documents', function (Blueprint $table): void {
                $table->string('instagram_follow_path', 500)->nullable()->after('portfolio_size_bytes');
            });
        }

        if (! Schema::hasColumn('recruitment_documents', 'instagram_follow_original_name')) {
            Schema::table('recruitment_documents', function (Blueprint $table): void {
                $table->string('instagram_follow_original_name')->nullable()->after('instagram_follow_path');
            });
        }

        if (! Schema::hasColumn('recruitment_documents', 'instagram_follow_mime')) {
            Schema::table('recruitment_documents', function (Blueprint $table): void {
                $table->string('instagram_follow_mime', 100)->nullable()->after('instagram_follow_original_name');
            });
        }

        if (! Schema::hasColumn('recruitment_documents', 'instagram_follow_size_bytes')) {
            Schema::table('recruitment_documents', function (Blueprint $table): void {
                $table->unsignedInteger('instagram_follow_size_bytes')->nullable()->after('instagram_follow_mime');
            });
        }

        if (! Schema::hasColumn('recruitment_documents', 'twibbon_url')) {
            Schema::table('recruitment_documents', function (Blueprint $table): void {
                $table->string('twibbon_url', 500)->nullable()->after('instagram_follow_size_bytes');
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            [
                'instagram_follow_path',
                'instagram_follow_original_name',
                'instagram_follow_mime',
                'instagram_follow_size_bytes',
                'twibbon_url',
            ],
            static fn (string $column): bool => Schema::hasColumn('recruitment_documents', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('recruitment_documents', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
