<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering index untuk scan substring pencarian (BE-P2-013, lanjutan 000001).
 *
 * Pencarian memakai `LIKE '%term%'` (substring, bukan prefix), jadi MySQL tidak
 * bisa range-scan; yang bisa dihindari adalah membaca baris tabel. `searchIdUnion()`
 * memilih `id, submitted_at` dari tiga cabang per kolom, dan index lama
 * `(recruitment_period_id, kolom)` tidak memuat `submitted_at`: cabang yang cocok
 * banyak (mis. `A11` pada seluruh NIM) membaca 20.000 baris (~4,1 detik per cabang,
 * total `paginate(search: 'A11')` ~7,9 detik).
 *
 * Dengan `(recruitment_period_id, kolom, submitted_at)` setiap cabang menjadi
 * covering (index-only scan) walau predikatnya substring: `paginate('A11')`
 * ~58 ms. `id` sudah implisit di secondary index InnoDB.
 * `(recruitment_period_id, full_name)` lama menjadi redundant, tetapi drop
 * diserahkan ke migration index-hygiene (approval-gated), bukan di sini.
 *
 * Non-destruktif: hanya ADD INDEX (InnoDB online DDL). Idempoten: dicek
 * berdasarkan daftar kolom, bukan hanya nama.
 */
return new class () extends Migration {
    public function up(): void
    {
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_nim_cover_idx',
            ['recruitment_period_id', 'nim', 'submitted_at'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_regno_cover_idx',
            ['recruitment_period_id', 'registration_number', 'submitted_at'],
        );
        $this->addIndexIfMissing(
            'recruitment_applications',
            'rec_apps_period_full_name_submitted_idx',
            ['recruitment_period_id', 'full_name', 'submitted_at'],
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_nim_cover_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_regno_cover_idx');
        $this->dropIndexIfExists('recruitment_applications', 'rec_apps_period_full_name_submitted_idx');
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (Schema::hasIndex($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }
};
