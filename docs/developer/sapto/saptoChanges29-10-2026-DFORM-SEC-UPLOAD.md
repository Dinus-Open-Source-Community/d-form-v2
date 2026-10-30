# Sapto Changes — 29 Oktober 2026 (DFORM-SEC-UPLOAD)

## Ringkasan

Seluruh validasi upload aplikasi memakai `SafeUploadFile` dengan batas global 5 MB dan pemeriksaan isi berdasarkan signature/decoder. Gambar yang disimpan melalui jalur publik di-re-encode lewat `SafeUploadStorage` agar data tambahan dan metadata tidak ikut tersimpan.

| Issue | Commit |
| ----- | ------ |
| SEC-01/SEC-02 upload hardening | `fix(security): sentralisasi validasi konten upload` |

## Perubahan

- Form Request event, recruitment, avatar, form image, form dinamis, broadcast attachment/inline image, dan dataset CSV memakai validator yang sama.
- PDF wajib memiliki signature `%PDF-`; gambar wajib cocok dengan MIME hasil deteksi dan dapat didekode; CSV/TXT wajib berupa teks UTF-8 tanpa byte NUL.
- Ukuran upload global dibatasi 5 MB melalui `config/events.php` (`FORMS_UPLOAD_MAX_SIZE_KB`); daftar ekstensi default dipakai bersama lewat `SafeUploadFile::DEFAULT_FORMATS`, tidak lagi disalin di tiga tempat.
- Gambar event, recruitment, form submission, team invitation, avatar, form builder, broadcast inline, dan dokumen lokal recruitment/broadcast diolah melalui target storage yang sama sebelum disimpan.
- Validasi frontend dipusatkan di `resources/js/lib/uploadValidation.ts` dan dipakai oleh form fill, avatar, builder image, broadcast attachment, broadcast editor, dan OpRec edit.
- Route debug `GET /flush` (`Cache::flush()` saat `APP_ENV=local`) dihapus dari `routes/web/index.php`: route itu tidak punya pemakai dan hanya menyediakan jalur penghapusan cache lewat HTTP.

## Perbaikan tipe pada jalur upload (LSP)

Enam file di jalur yang sama masih memakai nilai yang bisa `null`/`false` sebagai `string`. Diperbaiki di akarnya, tanpa supresi:

| File | Penyebab | Perbaikan |
| ---- | -------- | --------- |
| `Broadcasting/BroadcastAttachmentService.php` | `preg_replace()` bisa `null` → masuk `explode()` | `?? ''`; jalur gagal tetap mengembalikan `Inline image tidak valid.` |
| `Broadcasting/BroadcastInlineImageService.php` | `storeAs()` mengembalikan `string\|false` | `RuntimeException` bila `false` |
| `Event/EventService.php` | `cursorPaginate()` bertipe kontrak; `json_encode()` bisa `false`; `fresh()` bisa `null` | anotasi `@var`/`@return` CursorPaginator, `JSON_THROW_ON_ERROR`, `refresh()` |
| `Recruitment/RecruitmentPeriodService.php` | `fresh()` bisa `null` | `refresh()` (objek sama, non-null) |
| `Form/RulesBuilder.php` | docblock `array<int, string>` padahal memuat rule objek | docblock mengikuti kontrak baru |
| `User/UserAvatarService.php` | guard path tidak menyempitkan tipe | `@phpstan-assert-if-true string $value` |

`tests/Feature/RulesBuilderTest.php` disesuaikan: `build()` sekarang mengeluarkan `SafeUploadFile`, bukan lagi `mimes:`/`max:`.

## Verifikasi

Semua angka diukur ulang di container `d_form_app` pada 30 Oktober 2026, bukan dikutip dari sesi sebelumnya.

| Pemeriksaan | Perintah | Hasil |
| ----------- | -------- | ----- |
| Unit suite | `php artisan test --testsuite=Unit` | 56 passed (330 assertions) |
| Feature terdampak | `RulesBuilder`, `Forms`, `Broadcasting`, `Broadcast`, `Dashboard`, `Recruitment`, `OprecFormDefinition`, `OprecApplySubmit` | 546 passed, 31 skipped, 0 failed (130 s) |
| PHP style | `pint --test` 38 file berubah | PASS |
| ESLint | 12 file frontend berubah | exit 0 |
| Typecheck | `npm run typecheck` (`vue-tsc --noEmit`, vue-tsc 3.3.13 dari `package-lock.json`) | exit 0 |
| Build | `npm run build` (container) | exit 0, 15,22 s; entry `app-*.js` 26,57 kB gzip; chunk terbesar 145,58 kB gzip (budget 200 kB) |

Catatan: `vue-tsc` sempat tidak ada di `node_modules` meski tercatat di `package-lock.json`; dijalankan `npm install` (tanpa paket baru) agar gate typecheck benar-benar bisa dieksekusi. `package-lock.json` hanya berubah pada field `name` akibat normalisasi npm dan tidak ikut di-commit.

## Batasan

- File CSV/TXT divalidasi sebagai teks, bukan diproses ulang; isinya tetap harus diparse oleh service pemakainya.
- Validasi browser hanya preflight; server tetap menjadi sumber keputusan keamanan.
- Pemeriksaan isi memakai decoder PHP (`getimagesize`, signature PDF); file yang valid secara format tetapi berbahaya setelah diparse oleh konsumen lain (mis. PDF dengan JavaScript) tidak diblokir di sini.
- 106 file lain di root checkout masih memuat error tipe pra-ada (tanpa PHPStan, hanya dari LSP). Tidak disentuh karena di luar perubahan ini.
