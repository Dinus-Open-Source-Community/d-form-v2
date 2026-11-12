<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregat jumlah event per token kategori: chart dashboard cukup membaca <=100 baris,
 * bukan `GROUP BY events.category` (kolom TEXT tanpa indeks, 7 detik pada 1 juta baris).
 * Tabel diisi EventObserver; setelah migrate/seed jalankan `analytics:rebuild-category-counts`.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('event_category_counts', function (Blueprint $table): void {
            // Binary: `Seminar` dan `seminar` tetap dua token berbeda, sama seperti
            // `GROUP BY events.category` sebelumnya (PK ci akan menabrak duplicate key).
            $table->string('category', 191)->primary();
            $table->unsignedInteger('total')->default(0);
            $table->collation('utf8mb4_bin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_category_counts');
    }
};
