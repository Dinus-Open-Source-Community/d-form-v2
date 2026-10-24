<?php

// Batas percobaan per endpoint autentikasi; diatur lewat env agar bisa disetel tanpa ubah kode.
return [
    'login' => [
        'max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_LOGIN_DECAY_MINUTES', 1),
    ],
    'register' => [
        'max_attempts' => (int) env('AUTH_REGISTER_MAX_ATTEMPTS', 3),
        'decay_minutes' => (int) env('AUTH_REGISTER_DECAY_MINUTES', 1),
    ],
    'password_email' => [
        'max_attempts' => (int) env('AUTH_PASSWORD_EMAIL_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_PASSWORD_EMAIL_DECAY_MINUTES', 1),
    ],
    'password_link' => [
        'max_attempts' => (int) env('AUTH_PASSWORD_LINK_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_PASSWORD_LINK_DECAY_MINUTES', 1),
    ],
    'password_reset' => [
        'max_attempts' => (int) env('AUTH_PASSWORD_RESET_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_PASSWORD_RESET_DECAY_MINUTES', 1),
    ],
];
