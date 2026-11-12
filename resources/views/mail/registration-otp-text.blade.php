Kode verifikasi DForm

Halo {{ $recipientName }},

Masukkan kode berikut untuk menyelesaikan pendaftaran akun DForm kamu:

    {{ $code }}

Kode ini berlaku {{ $ttlMinutes }} menit sejak email dikirim dan hanya bisa dipakai sekali.

Kalau kamu tidak merasa mendaftar di DForm, abaikan email ini — tanpa kode tersebut akun tidak akan dibuat.

────────────────────────
{{ __('This message was sent by :app.', ['app' => config('app.name')]) }}
