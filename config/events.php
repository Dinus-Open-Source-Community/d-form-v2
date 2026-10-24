<?php

return [
    'search' => [
        /*
         * Batas eksekusi query pencarian (ms). Kata kunci yang cocok ratusan ribu
         * baris tetap dipotong di sini alih-alih menahan worker sampai selesai.
         * Diturunkan di test agar jalur timeout bisa diuji tanpa data 1 juta baris.
         */
        'max_execution_ms' => (int) env('EVENTS_SEARCH_MAX_EXECUTION_MS', 1500),

        /* TTL payload halaman pencarian (detik). */
        'cache_ttl_seconds' => (int) env('EVENTS_SEARCH_CACHE_TTL', 60),

        /* TTL marker "kata kunci terlalu umum" (detik), agar query berat tidak diulang. */
        'too_broad_ttl_seconds' => (int) env('EVENTS_SEARCH_TOO_BROAD_TTL', 60),

        /* Lock per halaman pencarian: TTL lock & lama menunggu sebelum menyerah (detik). */
        'lock_ttl_seconds' => (int) env('EVENTS_SEARCH_LOCK_TTL', 10),
        'lock_wait_seconds' => (int) env('EVENTS_SEARCH_LOCK_WAIT', 3),

        /* Kuota rate limit route /events per IP per menit. */
        'rate_limit_per_minute' => (int) env('EVENTS_SEARCH_RATE_LIMIT_PER_MINUTE', 60),

        /* Batas halaman hasil pencarian; daftar tanpa kata kunci tidak dibatasi. */
        'max_page' => (int) env('EVENTS_SEARCH_MAX_PAGE', 20),
    ],

    'admin' => [
        /* Batas halaman daftar event admin (OFFSET dalam sangat mahal). */
        'max_page' => (int) env('EVENTS_ADMIN_MAX_PAGE', 200),
    ],
];
