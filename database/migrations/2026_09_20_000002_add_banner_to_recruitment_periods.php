<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('recruitment_periods', function (Blueprint $table): void {
            $table->string('banner')->nullable()->after('landing_content');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_periods', function (Blueprint $table): void {
            $table->dropColumn('banner');
        });
    }
};
