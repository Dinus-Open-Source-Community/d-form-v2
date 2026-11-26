<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus agregat `event_category_counts`.
 *
 * Angka chart kategori sepenuhnya dapat diturunkan dari `events.category`; tabel
 * agregat ini hanya ada untuk mempercepat chart dashboard yang sudah dihapus, jadi
 * memeliharanya berarti menanggung drift tanpa pemakai.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('event_category_counts');
    }

    /**
     * Bentuk tabel dikembalikan identik dengan migrasi pembuatnya agar rollback
     * tidak menipu: kategori binary (utf8mb4_bin) supaya `Seminar` dan `seminar`
     * tetap dua token berbeda seperti `GROUP BY events.category`.
     */
    public function down(): void
    {
        Schema::create('event_category_counts', function (Blueprint $table): void {
            $table->string('category', 191)->primary();
            $table->unsignedInteger('total')->default(0);
            $table->collation('utf8mb4_bin');
        });
    }
};
