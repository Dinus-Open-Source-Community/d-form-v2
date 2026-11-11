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

    'forms' => [
        'upload_max_size_kb' => max(1, min(5120, (int) env('FORMS_UPLOAD_MAX_SIZE_KB', 5120))),
        /*
         * Cap piksel 12 juta dan dimensi 8000 px diperiksa dari header gambar
         * sebelum decode. Worst case decode ~48 MB (4 byte/piksel) + buffer
         * sumber/encode ≤5 MB masing-masing.
         */
        'upload_max_pixels' => max(1, min(12_000_000, (int) env('FORMS_UPLOAD_MAX_PIXELS', 12_000_000))),
        'upload_max_dimension' => max(1, min(8000, (int) env('FORMS_UPLOAD_MAX_DIMENSION', 8000))),
        /* CSV dataset broadcast diparse penuh: batas ukuran dan jumlah baris sendiri. */
        'dataset_csv_max_size_kb' => max(1, min(5120, (int) env('DATASET_CSV_MAX_SIZE_KB', 2048))),
        'dataset_csv_max_rows' => max(1, min(100000, (int) env('DATASET_CSV_MAX_ROWS', 10000))),
        'upload_allowed_mimes' => env('FORMS_UPLOAD_ALLOWED_MIMES', 'pdf,jpg,jpeg,png,webp,gif'),
        /*
         * Submit form per user per form per menit. Submit normal 1×; sisa kuota
         * menampung retry validasi (field wajib terlewat, unggahan terlalu besar).
         */
        'submit_rate_limit_per_minute' => (int) env('FORMS_SUBMIT_RATE_LIMIT_PER_MINUTE', 10),
        /*
         * TTL URL bertanda tangan untuk berkas jawaban yang dikirim lewat email
         * atau CSV. Halaman web memakai route terautentikasi tanpa kedaluwarsa.
         */
        'upload_temporary_url_ttl_minutes' => max(1, (int) env('FORMS_UPLOAD_URL_TTL_MINUTES', 10080)),
        /* Batas unduhan berkas jawaban per user/IP per menit. */
        'file_download_rate_limit_per_minute' => max(1, (int) env('FORMS_FILE_DOWNLOAD_RATE_LIMIT_PER_MINUTE', 120)),
    ],
];
