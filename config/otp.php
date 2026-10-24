<?php

// Kebijakan OTP registrasi; diatur lewat env agar bisa disetel tanpa ubah kode.
return [
    'length' => (int) env('OTP_LENGTH', 6),
    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 3),
    'max_sends' => (int) env('OTP_MAX_SENDS', 3),
    'lock_minutes' => (int) env('OTP_LOCK_MINUTES', 15),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
];
