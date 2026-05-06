<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'slug')) {
                $table->string('slug', 160)->nullable()->unique()->after('title');
            }
        });

        $rows = DB::table('events')->select('id', 'title')->orderBy('id')->get();
        foreach ($rows as $row) {
            $base = Str::slug((string) $row->title);
            if ($base === '') {
                $base = 'event';
            }
            $candidate = $base;
            $suffix = 2;
            while (
                DB::table('events')
                    ->where('slug', $candidate)
                    ->where('id', '!=', $row->id)
                    ->exists()
            ) {
                $candidate = $base.'-'.$suffix++;
            }
            DB::table('events')->where('id', $row->id)->update(['slug' => $candidate]);
        }

        Schema::table('events', function (Blueprint $table) {
            $table->string('slug', 160)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });
    }
};
