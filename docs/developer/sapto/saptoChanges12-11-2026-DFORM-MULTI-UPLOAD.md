# Sapto Changes — 12 November 2026 (DFORM-MULTI-UPLOAD)

## Ringkasan

Menambahkan regression E2E desktop/mobile untuk field upload tunggal dan
multiple. Cakupan meliputi toggle format builder, pratinjau vertikal gambar,
baris dokumen, penghapusan per berkas, penolakan format dan batas, submit,
pratinjau admin privat, serta pemeriksaan overflow horizontal.

| Issue                        | Commit                                               |
| ---------------------------- | ---------------------------------------------------- |
| `multi-upload-fields` Task 5 | Tidak dibuat; sesuai instruksi tidak ada commit/push |

## Perubahan

- `tests/e2e/multi-upload-fields.spec.ts` memakai owner fixture ber-marker dan
  `E2E_ARTISAN` dari environment container.
- Fixture memverifikasi jawaban single tetap scalar, jawaban multiple berupa
  array, dan upload baru tersimpan pada disk `local` tanpa salinan `public`.
- Admin preview memeriksa setiap URL file privat dapat diakses owner,
  ditolak untuk anonymous, dan tidak membocorkan path storage.
- Judul fixture dibuat singkat setelah pembuatan marker agar pemeriksaan mobile
  tidak menghasilkan false positive overflow dari marker yang tidak terputus.

## Deployment dan kompatibilitas

- Tidak ada migration database; deployment bersifat migration-free.
- Upload baru tetap memakai private local storage dan cleanup orphan dilakukan
  oleh jalur backend yang sudah diuji Task 2.
- Fallback public `form-uploads` untuk data legacy sengaja dipertahankan sesuai
  ruling kompatibilitas; tidak ada migrasi atau penghapusan otomatis.
- Validasi format tetap server-authoritative; E2E hanya memeriksa penolakan
  client-side sebelum submit dan tidak menyediakan bypass.

## Verifikasi

| Pemeriksaan             | Hasil                                                                      |
| ----------------------- | -------------------------------------------------------------------------- |
| Targeted desktop E2E    | **7 passed**                                                               |
| Targeted mobile E2E     | **7 passed**                                                               |
| PHPUnit penuh           | **1657 passed**, 12 skipped, 10.844 assertions                             |
| Vitest penuh            | **979 passed** dalam 88 file                                               |
| Typecheck dan E2E types | Passed                                                                     |
| ESLint                  | Passed; satu warning lama pada `formBuilderAutosave.test.ts:27`            |
| Pint penuh              | **810 files passed**                                                       |
| Build container         | Passed                                                                     |
| Bundle budget           | Entry **15,8 kB gzip**, chunk terbesar **143,8 kB gzip**; passed           |
| Prettier scoped         | Passed untuk spec dan changelog/README                                     |
| Prettier penuh          | Exit 1 karena drift format pre-existing pada 153 file; bukan blocker tiket |

Perintah browser dijalankan terhadap `http://localhost:3000` dengan artisan
`podman exec -i -w /app d_form_app php artisan`. Tidak ada blocker environment
pada verifikasi akhir.
