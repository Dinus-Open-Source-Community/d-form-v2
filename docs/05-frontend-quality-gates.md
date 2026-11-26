# Frontend Quality Gates

## Overview

Proyek D-Form menggunakan TypeScript dan Vitest untuk menjaga kualitas kode frontend melalui type checking dan unit testing.

Beberapa direktori frontend yang dihasilkan secara otomatis oleh Laravel Wayfinder adalah:

- `resources/js/routes`
- `resources/js/actions`
- `resources/js/wayfinder`

Direktori tersebut merupakan generated artifacts dan diabaikan oleh Git melalui konfigurasi `.gitignore` pada baris 47–49.

## 1. Automatic Wayfinder Generation

Agar clean checkout dapat langsung menjalankan quality gates, script berikut akan memeriksa ketersediaan generated artifacts sebelum menjalankan proses pemeriksaan.

```text
scripts/wayfinder/generateFrontendArtifacts.js
```

Script tersebut dipanggil melalui npm `pre*` hooks pada perintah:

```bash
npm run typecheck
npm run typecheck:unit
npm run test:unit
```

Mekanisme kerjanya:

1. Memeriksa apakah generated artifacts Wayfinder tersedia.
2. Jika belum tersedia, menjalankan `php artisan wayfinder:generate`.
3. Jika sudah tersedia, proses generate dilewati.
4. Melanjutkan proses TypeScript atau Vitest.

Perintah generate yang digunakan sama dengan perintah pada CI.

## 2. Manual Generation

Generated artifacts juga dapat dibuat secara manual.

Jika PHP tersedia pada host:

```bash
npm run wayfinder:generate
```

Jika PHP hanya tersedia di dalam container:

```bash
podman exec -w /app d_form_app php artisan wayfinder:generate
```

## 3. Custom Wayfinder Command

Jika PHP hanya tersedia di container, gunakan environment variable `WAYFINDER_COMMAND` untuk mengganti perintah generate.

```bash
WAYFINDER_COMMAND='podman exec -w /app d_form_app php artisan wayfinder:generate' npm run typecheck
```

Jika proses generate tidak dapat dilakukan, script akan berhenti dan menampilkan pesan error beserta instruksi perbaikan.

## 4. Available Commands

| Command                      | Fungsi                                   |
| ---------------------------- | ---------------------------------------- |
| `npm run wayfinder:generate` | Generate frontend artifacts Wayfinder    |
| `npm run typecheck`          | Type checking frontend                   |
| `npm run typecheck:unit`     | Type checking untuk unit test            |
| `npm run test:unit`          | Menjalankan unit test menggunakan Vitest |

## 5. Important Notes

- Generated artifacts tidak disimpan dalam Git.
- Pastikan Wayfinder berhasil melakukan generate sebelum proses type checking.
- Lingkungan CI juga menggunakan perintah `php artisan wayfinder:generate`.
- Jika PHP tidak tersedia pada host, gunakan konfigurasi `WAYFINDER_COMMAND`.
