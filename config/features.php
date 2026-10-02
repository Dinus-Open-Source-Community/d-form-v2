<?php

return [
    /*
     * Fitur broadcast hub (email massal lintas event/periode).
     *
     * Dinonaktifkan (default): route /admin/broadcasts/* tidak didaftarkan,
     * policy menolak semua aksi, command scheduler jadi no-op, job yang
     * mengendap di antrean dibuang, dan seluruh UI disembunyikan. File-file
     * fitur tetap ada dan bisa diaktifkan lagi via FEATURE_BROADCAST=true.
     *
     * Email transaksional (screening, tracking, link grup WA) tidak terpengaruh.
     */
    'broadcast' => (bool) env('FEATURE_BROADCAST', false),
];
