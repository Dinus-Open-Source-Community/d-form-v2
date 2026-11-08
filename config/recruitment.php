<?php

return [
    /*
    | Comma-separated staff emails for correction request notifications.
    | Falls back to users with recruitment.corrections.review permission when empty.
    */
    'staff_notification_email' => env('RECRUITMENT_STAFF_EMAIL'),

    /*
    | Batas mutation portal tracking OpRec (request per menit). Dipisah per
    | endpoint karena biayanya berbeda: update mengunggah berkas dan menulis
    | beberapa tabel, correction memicu job email ke staff, feedback menulis
    | penilaian. Kunci limiter adalah sesi tracking (id aplikasi), fallback IP.
    */
    'tracking_mutation_throttle' => [
        'update' => (int) env('RECRUITMENT_TRACK_UPDATE_RATE_LIMIT', 12),
        'correction' => (int) env('RECRUITMENT_TRACK_CORRECTION_RATE_LIMIT', 5),
        'feedback' => (int) env('RECRUITMENT_TRACK_FEEDBACK_RATE_LIMIT', 5),
    ],

    /*
    | Ukuran halaman keyset pagination export CSV interview. Naikkan untuk
    | mengurangi jumlah query, turunkan bila memori per halaman jadi masalah.
    | Controller membatasi nilai efektif ke 1..2000 (satu halaman ±25 MB per 500).
    */
    'interview_export_page_size' => (int) env('RECRUITMENT_INTERVIEW_EXPORT_PAGE_SIZE', 500),
];
