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
    'email_check' => [
        'max_attempts' => (int) env('AUTH_EMAIL_CHECK_MAX_ATTEMPTS', 20),
        'decay_minutes' => (int) env('AUTH_EMAIL_CHECK_DECAY_MINUTES', 1),
    ],
    'otp_verify' => [
        'max_attempts' => (int) env('AUTH_OTP_VERIFY_MAX_ATTEMPTS', 10),
        'decay_minutes' => (int) env('AUTH_OTP_VERIFY_DECAY_MINUTES', 1),
    ],
    'otp_resend' => [
        'max_attempts' => (int) env('AUTH_OTP_RESEND_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_OTP_RESEND_DECAY_MINUTES', 1),
    ],
    'otp_address' => [
        'max_attempts' => (int) env('AUTH_OTP_ADDRESS_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_OTP_ADDRESS_DECAY_MINUTES', 60),
    ],
    'verify_email_send' => [
        'max_attempts' => (int) env('AUTH_VERIFY_EMAIL_SEND_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_VERIFY_EMAIL_SEND_DECAY_MINUTES', 10),
    ],
    'verify_email_confirm' => [
        'max_attempts' => (int) env('AUTH_VERIFY_EMAIL_CONFIRM_MAX_ATTEMPTS', 10),
        'decay_minutes' => (int) env('AUTH_VERIFY_EMAIL_CONFIRM_DECAY_MINUTES', 1),
    ],
    'two_factor_verify' => [
        'max_attempts' => (int) env('AUTH_TWO_FACTOR_VERIFY_MAX_ATTEMPTS', 10),
        'decay_minutes' => (int) env('AUTH_TWO_FACTOR_VERIFY_DECAY_MINUTES', 1),
    ],
    'two_factor_recovery' => [
        'max_attempts' => (int) env('AUTH_TWO_FACTOR_RECOVERY_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_TWO_FACTOR_RECOVERY_DECAY_MINUTES', 10),
    ],
    'oauth_connection' => [
        'max_attempts' => (int) env('AUTH_OAUTH_CONNECTION_MAX_ATTEMPTS', 10),
        'decay_minutes' => (int) env('AUTH_OAUTH_CONNECTION_DECAY_MINUTES', 1),
    ],
];
