# E2E Testing

## Overview

Proyek D-Form menggunakan **Playwright** untuk menjalankan pengujian end-to-end (E2E).

Seluruh test specification berada di direktori:

```text
tests/e2e/
```

Playwright tidak menjalankan application server secara otomatis. Developer harus menjalankan aplikasi terlebih dahulu sebelum mengeksekusi pengujian.

## 1. Prerequisites

Pastikan beberapa kebutuhan berikut sudah tersedia:

- Application server sudah berjalan.
- Container aplikasi sudah aktif.
- Kredensial akun admin tersedia.
- Konfigurasi environment E2E sudah disiapkan.

Jalankan container:

```bash
podman compose up -d
```

Jika membutuhkan Hot Module Replacement (HMR), jalankan juga:

```bash
npm run dev
```

## 2. Running E2E Tests

Gunakan environment variables berikut untuk menjalankan seluruh pengujian:

```bash
E2E_BASE_URL=http://localhost:3000 \
E2E_ADMIN_EMAIL=stress-user-1@stress.test \
E2E_ADMIN_PASSWORD=password \
npm run test:e2e
```

## 3. Available Scripts

| Script                     | Fungsi                                                            |
| -------------------------- | ----------------------------------------------------------------- |
| `npm run test:e2e`         | Menjalankan seluruh project (setup, desktop, dan mobile)          |
| `npm run test:e2e:setup`   | Login admin sekali dan menyimpan storage state                    |
| `npm run test:e2e:desktop` | Menjalankan project `chromium-desktop` dengan resolusi 1440×900   |
| `npm run test:e2e:mobile`  | Menjalankan project `chromium-mobile` menggunakan emulasi Pixel 7 |
| `npm run test:e2e:types`   | Type checking E2E menggunakan `tsconfig.e2e.json`                 |

## 4. Environment Variables

| Variable                  | Default                                         | Keterangan                                                      |
| ------------------------- | ----------------------------------------------- | --------------------------------------------------------------- |
| `E2E_BASE_URL`            | `http://localhost:8000`                         | URL aplikasi yang diuji                                         |
| `E2E_ADMIN_EMAIL`         | -                                               | Email akun admin (wajib)                                        |
| `E2E_ADMIN_PASSWORD`      | -                                               | Password akun admin (wajib)                                     |
| `E2E_ARTISAN`             | `podman exec -i -w /app d_form_app php artisan` | Perintah Artisan yang digunakan E2E                             |
| `E2E_SESSION_COOKIE`      | -                                               | Override nama cookie session                                    |
| `E2E_ADMIN_STATE`         | -                                               | Override path storage state                                     |
| `E2E_BASE_URL_TIMEOUT_MS` | `5000`                                          | Batas waktu probe HEAD/GET                                      |
| `E2E_FEATURE_BROADCAST`   | `false`                                         | Aktifkan hanya jika server menggunakan `FEATURE_BROADCAST=true` |
| `E2E_RUN_ID`              | -                                               | Identifier untuk storage state setiap run                       |
| `E2E_SESSION_DRIVER`      | -                                               | Driver session yang digunakan server E2E                        |

## 5. Storage State Management

Playwright menggunakan storage state untuk mempertahankan autentikasi admin selama pengujian.

Lokasi default:

```text
tests/e2e/.auth/admin-<runId>.json
```

`runId` diperoleh dari environment variable `E2E_RUN_ID`.

Mekanisme storage state:

1. Global setup melakukan login admin.
2. Hasil autentikasi disimpan ke file storage state.
3. Spec menggunakan file storage state dari run yang sama.
4. Setiap run memiliki file tersendiri sehingga tidak saling menimpa.
5. File run lain yang berusia lebih dari 24 jam dibersihkan saat global setup.

Untuk menggunakan path storage state secara manual, atur `E2E_ADMIN_STATE`.

Contoh:

```bash
E2E_ADMIN_STATE=tests/e2e/.auth/admin-8020.json
```

Pengaturan ini berguna ketika menjalankan pengujian terhadap server terpisah.

## 6. Test Isolation & Cleanup

Spec menggunakan fixture ber-marker untuk mengidentifikasi data yang dibuat selama pengujian.

Data tersebut akan dibersihkan setelah pengujian selesai.

Artefak kegagalan seperti trace dan screenshot disimpan di:

```text
test-results/
```

## 7. Redis Session Driver Limitation

**Perhatian:** Terdapat batasan ketika menjalankan E2E menggunakan Redis sebagai session driver.

Beberapa spec memanggil `Cache::flush()`. Operasi tersebut juga menghapus session store Redis sehingga sesi admin dapat terhapus dan menyebabkan spec berikutnya gagal.

Kondisi ini tidak terjadi pada server E2E yang menggunakan session driver `file`.

Untuk menjalankan E2E terhadap server dengan Redis session driver, gunakan:

```bash
E2E_SESSION_DRIVER=redis
```

Dengan konfigurasi tersebut, regresi terkait session isolation akan di-skip.

**Catatan penting:**

- Jangan menjalankan dua spec secara berurutan yang melakukan cache flush sebelum sesi admin dipulihkan.
- Spec `session-isolation.desktop.spec.ts` telah berjalan dengan baik menggunakan session driver `file`.
- Gunakan session driver `file` jika membutuhkan pengujian session isolation tanpa gangguan dari Redis cache flush.
