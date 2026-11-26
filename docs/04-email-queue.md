# Email Queue & Registration Confirmation

## Overview

Setelah proses pendaftaran berhasil, sistem akan mengantrekan email konfirmasi melalui `SendRegistrationConfirmationJob`.

Email konfirmasi berisi:

- QR Code peserta.
- Ringkasan jawaban pendaftaran.

Sistem juga mencatat aktivitas pengiriman email ke tabel `email_logs` sebagai audit log.

**Implementation:** `app/Jobs/SendRegistrationConfirmationJob.php`

## 1. Queue Configuration

Pastikan konfigurasi `QUEUE_CONNECTION` pada file `.env` sesuai dengan lingkungan yang digunakan.

Contoh:

```env
QUEUE_CONNECTION=redis
```

Alternatif lainnya adalah menggunakan `database`.

Pastikan queue worker berjalan agar email yang masuk ke antrean dapat diproses.

```bash
php artisan queue:work
```

Untuk pengembangan lokal, gunakan:

```bash
composer run dev
```

Perintah tersebut sudah menyertakan `queue:listen`.

## 2. SMTP Configuration

Untuk mengirim email secara nyata, gunakan konfigurasi SMTP.

```env
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_USERNAME=
MAIL_PASSWORD=
```

Untuk lingkungan pengembangan, tersedia alternatif:

- `MAIL_MAILER=log` untuk mencatat email ke log aplikasi.
- Mailpit untuk pengujian email lokal sesuai konfigurasi port pada `MAIL_PORT`.

## 3. Database Migration

Jalankan migrasi untuk membuat tabel `email_logs`.

```bash
php artisan migrate
```

Tabel tersebut digunakan untuk menyimpan audit pengiriman email.

## 4. QR Code Generation

Sistem menggunakan package [`endroid/qr-code`](https://github.com/endroid/qr-code) untuk menghasilkan QR Code dalam format PNG.

Package ini menggunakan ekstensi PHP **GD**.

Pastikan ekstensi berikut aktif pada konfigurasi PHP:

```ini
extension=gd
```

**Catatan:**

- Tidak memerlukan Imagick.
- Pastikan ekstensi GD tersedia pada lingkungan PHP yang menjalankan queue worker.
