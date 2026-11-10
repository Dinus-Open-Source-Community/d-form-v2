<?php

return [
    /*
     * Ukuran halaman keyset export attendance scan global
     * (`/admin/scan/export`). Dipakai per iterasi `(timestamp, id)`; nilai di
     * luar 1..5000 dipotong oleh controller.
     */
    'export_page_size' => (int) env('SCAN_EXPORT_PAGE_SIZE', 1000),
];
