{{ $pinRecovery ? 'Kode pemulihan PIN DForm' : 'Verifikasi email DForm' }}

Halo {{ $recipientName }},

Masukkan kode berikut untuk {{ $pinRecovery ? 'mengatur ulang PIN login' : 'memverifikasi email akun' }} DForm kamu:

    {{ $code }}

Kode ini berlaku {{ $ttlMinutes }} menit sejak email dikirim dan hanya bisa dipakai sekali.

{{ $pinRecovery ? 'Kalau kamu tidak merasa meminta pemulihan PIN, abaikan email ini. PIN dan akses akun kamu tidak berubah.' : 'Kalau kamu tidak merasa membuat akun atau meminta verifikasi ini, abaikan email ini. Email akun akan tetap seperti sebelumnya.' }}

────────────────────────
{{ __('This message was sent by :app.', ['app' => config('app.name')]) }}
