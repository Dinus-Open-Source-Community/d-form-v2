<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index keyset global scan feed: `ORDER BY scanned_at, id` / `ORDER BY checked_in_at, id`
 * tanpa filter domain. Tanpa index ini MySQL memakai index `(event_id, scanned_at)`
 * (atau `(session_id, checked_in_at)`) sebagai index scan penuh lalu filesort
 * seluruh tabel (EXPLAIN: 1.029.012 dan 732.832 baris, `Using filesort`).
 * Kedua index ini membuat range scan berhenti setelah LIMIT terpenuhi.
 *
 * Idempoten: MySQL tidak membungkus DDL dalam transaksi dan index bisa sudah
 * dibuat manual saat benchmark, sehingga kegagalan di tengah dapat meninggalkan
 * index tanpa baris migrasi.
 */
return new class () extends Migration {
    /**
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const INDEXES = [
        'event_attendances_scanned_at_id_index' => ['event_attendances', ['scanned_at', 'id']],
        'recruitment_attendances_checked_in_at_id_index' => ['recruitment_attendances', ['checked_in_at', 'id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => [$table, $columns]) {
            if (Schema::hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $name => [$table]) {
            if (! Schema::hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        }
    }
};
