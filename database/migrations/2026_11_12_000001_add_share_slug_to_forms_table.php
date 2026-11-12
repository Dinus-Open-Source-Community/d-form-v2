<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add the nullable public alias used to resolve a form share link.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->string('share_slug', 50)->nullable()->unique();
        });
    }

    /**
     * Remove only the index and column introduced by this migration.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->dropUnique('forms_share_slug_unique');
            $table->dropColumn('share_slug');
        });
    }
};
