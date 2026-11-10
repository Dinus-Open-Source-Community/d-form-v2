<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index pagination laporan & export event (BE-P2-025, BE-P2-035).
 *
 * - `event_attendances (event_id, scanned_at, id)` melayani
 *   `WHERE event_id = ? ORDER BY scanned_at DESC, id DESC`. Kolom ketiga `id`
 *   ditulis eksplisit demi determinisme tie-breaker di SQLite (dan sebagai
 *   dokumentasi urutan yang dipakai query). Di MySQL/InnoDB dan MariaDB kolom PK
 *   sudah otomatis diappend ke secondary index, jadi index 2 kolom BE-P2-006
 *   pun sudah menghasilkan plan tanpa filesort; `EXPLAIN` sebelum/sesudah
 *   migration membuktikannya (tidak ada perubahan plan di MySQL 9.0.1).
 * - `form_answers (form_id, created_at, id)` melayani keyset export registrasi
 *   `ORDER BY created_at DESC, id DESC` per form (`answerPage()`); kolom `id`
 *   di sini adalah tie-breaker nyata karena `created_at` hanya presisi detik.
 *
 * Add-only: index 2 kolom BE-P2-006 tetap dibiarkan (drop-nya butuh approval
 * terpisah). Idempoten lewat guard kolom karena MySQL tidak membungkus DDL
 * dalam transaksi.
 */
return new class () extends Migration {
    private const ATTENDANCE_TABLE = 'event_attendances';

    private const ATTENDANCE_INDEX = 'event_attendances_event_id_scanned_at_id_index';

    private const ANSWER_TABLE = 'form_answers';

    private const ANSWER_INDEX = 'form_answers_form_id_created_at_id_index';

    public function up(): void
    {
        if (! Schema::hasIndex(self::ATTENDANCE_TABLE, ['event_id', 'scanned_at', 'id'])) {
            Schema::table(self::ATTENDANCE_TABLE, function (Blueprint $table): void {
                $table->index(['event_id', 'scanned_at', 'id'], self::ATTENDANCE_INDEX);
            });
        }

        if (! Schema::hasIndex(self::ANSWER_TABLE, ['form_id', 'created_at', 'id'])) {
            Schema::table(self::ANSWER_TABLE, function (Blueprint $table): void {
                $table->index(['form_id', 'created_at', 'id'], self::ANSWER_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::ATTENDANCE_TABLE, self::ATTENDANCE_INDEX)) {
            Schema::table(self::ATTENDANCE_TABLE, function (Blueprint $table): void {
                $table->dropIndex(self::ATTENDANCE_INDEX);
            });
        }

        if (Schema::hasIndex(self::ANSWER_TABLE, self::ANSWER_INDEX)) {
            Schema::table(self::ANSWER_TABLE, function (Blueprint $table): void {
                $table->dropIndex(self::ANSWER_INDEX);
            });
        }
    }
};
