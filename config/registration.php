<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Team / bundle invitation link lifetime
    |--------------------------------------------------------------------------
    |
    | Invitations expire at this many days after the leader submits. Expiry is
    | evaluated when the invitee opens the confirmation URL (no scheduler).
    |
    */
    'invitation_ttl_days' => (int) env('REGISTRATION_INVITATION_TTL_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Email send delay (seconds between each outgoing email)
    |--------------------------------------------------------------------------
    |
    | When multiple emails are dispatched at once (e.g. N bundle invitation
    | jobs), each subsequent job is delayed by this many seconds to avoid
    | triggering per-second SMTP rate limits. Set to 0 to disable staggering.
    |
    */
    'email_send_delay_seconds' => (int) env('REGISTRATION_EMAIL_SEND_DELAY_SECONDS', 7),

    /*
    |--------------------------------------------------------------------------
    | Per-email jitter before SMTP send (seconds)
    |--------------------------------------------------------------------------
    |
    | Each mail job waits a random duration in this inclusive range before
    | calling the mailer. Skipped when APP_ENV=testing. Set min or max to 0
    | to disable jitter in other environments.
    |
    */
    'email_jitter_min_seconds' => (int) env('REGISTRATION_EMAIL_JITTER_MIN_SECONDS', 5),
    'email_jitter_max_seconds' => (int) env('REGISTRATION_EMAIL_JITTER_MAX_SECONDS', 10),

    /*
    |--------------------------------------------------------------------------
    | Ukuran halaman keyset pagination export CSV registrasi event
    |--------------------------------------------------------------------------
    |
    | Naikkan untuk mengurangi jumlah query, turunkan bila memori per halaman
    | menjadi masalah. Controller membatasi nilai efektif ke 1..2000.
    |
    */
    'event_export_page_size' => (int) env('REGISTRATION_EVENT_EXPORT_PAGE_SIZE', 250),
];
