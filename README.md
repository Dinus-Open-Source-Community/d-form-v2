# D-Form

## Apa itu D-Form

project dari Dinus Open Source Community dimana project ini merupakan sebuah website untuk menangani event-event yang diselenggarakan oleh DOSCOM, Open Recruitment DOSCOM, dan juga fitur presensi menggunakan QR code.

## Requirement

### Stack utama

| Teknologi   | Versi |
| ----------- | ----- |
| laravel     | 12.x  |
| tailwindcss | 4.x   |
| vue js      | 3.x   |
| inertia js  | 2.x   |
| shadcn-vue  | ...   |
| mysql       | 9.0   |
| redis       | 8.0   |
| nodejs      | 24+   |

### Tools development

| Nama tool      | Versi  | Keterangan                    |
| -------------- | ------ | ----------------------------- |
| npm            | 11+    | package manager js            |
| docker         | -      | containerization              |
| frankenphp     | php8.3 | web server                    |
| laravel pint   | ^1.25  | formatter php                 |
| prettier       | ^3.6.2 | formatter blade, vue, ts, dll |
| laravel octane | ^2.13  | bootstrapper app              |

## Disclaimer: baca ini sebelum ngoding

- [Product Requirements (PRD)](docs/prd.md) — lingkup fitur, stack (Vue + Inertia), dan kriteria sukses
- [Milestone & fase pengiriman](docs/milestone.md) — tahapan kerja tim dan migrasi Livewire → Vue
- [Pedoman Front-end](docs/rules/front-end.md)
- [Pedoman Back-end](docs/rules/back-end.md)

## Email antrean dan konfirmasi pendaftaran (M5)

Setelah pendaftaran berhasil, [`SendRegistrationConfirmationJob`](app/Jobs/SendRegistrationConfirmationJob.php) mengantre email konfirmasi (QR + ringkasan jawaban) dan menulis audit ke tabel `email_logs`.

- **Antrean:** pastikan `QUEUE_CONNECTION` di `.env` sesuai lingkungan (misalnya `redis` atau `database`) dan worker berjalan dengan `php artisan queue:work`, atau di lokal gunakan `composer run dev` (sudah menyertakan `queue:listen`).
- **SMTP:** untuk pengiriman nyata, set `MAIL_MAILER=smtp` dan isi `MAIL_HOST`, `MAIL_USERNAME`, dan `MAIL_PASSWORD`; untuk pengembangan, `MAIL_MAILER=log` atau Mailpit sesuai port di `MAIL_PORT`.
- **Migrasi:** jalankan `php artisan migrate` agar tabel `email_logs` tersedia.
- **QR di email:** paket [`endroid/qr-code`](https://github.com/endroid/qr-code) membuat PNG lewat **GD** (`extension=gd` di PHP). Tidak memerlukan Imagick.

## E2E Playwright

Spec berada di `tests/e2e/`. Playwright tidak menyalakan server sendiri: jalankan aplikasi lebih dulu (`podman compose up -d`, plus `npm run dev` bila butuh HMR) dan sediakan kredensial admin.

```bash
E2E_BASE_URL=http://localhost:3000 \
E2E_ADMIN_EMAIL=stress-user-1@stress.test \
E2E_ADMIN_PASSWORD=password \
npm run test:e2e
```

| Script                     | Fungsi                                                  |
| -------------------------- | ------------------------------------------------------- |
| `npm run test:e2e`         | Seluruh project (setup + desktop + mobile)              |
| `npm run test:e2e:setup`   | Login admin sekali, simpan `tests/e2e/.auth/admin.json` |
| `npm run test:e2e:desktop` | Hanya project `chromium-desktop` (1440×900)             |
| `npm run test:e2e:mobile`  | Hanya project `chromium-mobile` (Pixel 7)               |
| `npm run test:e2e:types`   | Typecheck spec (`tsconfig.e2e.json`)                    |

Variabel environment: `E2E_BASE_URL` (default `http://localhost:8000`), `E2E_ADMIN_EMAIL`, `E2E_ADMIN_PASSWORD` (wajib), `E2E_ARTISAN` (default `podman exec -i -w /app d_form_app php artisan`), `E2E_SESSION_COOKIE` bila nama cookie sesi berubah, `E2E_ADMIN_STATE` (default `tests/e2e/.auth/admin.json`; pakai path lain untuk run paralel/server berbeda), dan `E2E_FEATURE_BROADCAST=true` hanya bila server dijalankan dengan `FEATURE_BROADCAST=true`. Spec memakai fixture ber-marker dan membersihkan barisnya; artefak kegagalan (trace/screenshot) ditulis ke `test-results/`.

## Daftar isi

- [01. Instalasi projek](docs/01-installation.md)
- [02. Struktur folder projek](docs/02-directory-structure.md)
- [PRD — D-Form v2](docs/prd.md)
- [Milestone — D-Form v2](docs/milestone.md)
