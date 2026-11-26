<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provider id harus unik lintas akun supaya satu identitas OAuth tidak dipakai dua user.
 * Preflight menolak data duplikat lebih dulu agar migration gagal jelas, bukan memilih pemenang.
 */
return new class () extends Migration {
    public function up(): void
    {
        $this->assertNoDuplicates('google_id');
        $this->assertNoDuplicates('github_id');

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('google_id', 'users_google_id_unique');
            $table->unique('github_id', 'users_github_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_google_id_unique');
            $table->dropUnique('users_github_id_unique');
        });
    }

    /** Hanya jumlah nilai duplikat yang dilaporkan, tanpa email atau data pribadi lain. */
    private function assertNoDuplicates(string $column): void
    {
        $duplicates = DB::table('users')
            ->select($column)
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();

        if ($duplicates > 0) {
            throw new \RuntimeException("users.{$column} has {$duplicates} duplicated values");
        }
    }
};
