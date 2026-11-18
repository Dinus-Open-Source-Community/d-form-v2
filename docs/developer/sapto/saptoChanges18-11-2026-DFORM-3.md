# DFORM-3 — Hapus tampilan "Kartu" jawaban di mobile + Export rata kanan

**Issue:** DFORM-3 (nomor tiket placeholder atas permintaan user)
**Tanggal:** 18-11-2026
**Klasifikasi:** `refactor` (penghapusan fitur) + `design` (penyelarasan toolbar)
**Commit:** `refactor: remove mobile card view for form answers`

## Ringkasan (TL;DR)

Di tab **Jawaban**, mobile sekarang hanya memakai daftar baris. Pemilih tampilan Kartu/Daftar, komponen kartu, hook view mode, tipe, storage key, dan seluruh test yang menunjuk ke sana dihapus. Tombol **Export** pada toolbar dipindah ke sisi kanan di mobile (`ml-auto`), sehingga baris aksi mengikuti pola "navigasi kiri, aksi kanan". Di desktop, tabel tidak berubah.

## Yang dihapus (sampai akar)

| Artefak | Alasan aman dihapus |
| --- | --- |
| `components/modules/dashboard/FormAnswersViewToggle.vue` | Hanya dipakai `Show.vue` (grep 0 pemakai lain). |
| `components/modules/dashboard/FormAnswerMobileCard.vue` | Hanya dipakai cabang `view === 'card'` di `Show.vue`. |
| `hooks/useFormAnswerMobileView.ts` | Hanya dipakai `Show.vue`; membaca/menulis `d-form:form-answers:mobile-view`. |
| `types/formAnswerMobileView.ts` | `TFormAnswerMobileView` hanya diimpor dua file di atas. |
| `Show.vue`: import toggle/kartu/hook, `mobileAnswerView`, `mobileAnswerKeys`, `mobileFileFieldNames`, blok `<FormAnswersViewToggle>`, cabang `v-if` kartu + `<FormAnswerMobileCard>`, `v-if="mobileAnswerView.isDesktop.value"` pada wrapper tabel | Semua hanya melayani fitur kartu. |
| `tests/e2e/form-answers-mobile.spec.ts`: `STORAGE_KEY`, selektor toggle, langkah `Kartu`/`Daftar`, `form-answer-mobile-card`, assertion `aria-pressed`/localStorage | Menguji fitur yang dihapus. |

Bukti audit: `grep -rn` untuk `FormAnswerMobileCard`, `FormAnswersViewToggle`, `useFormAnswerMobileView`, `TFormAnswerMobileView`, `mobileAnswerView`, `form-answer-mobile-card`, `Tampilan daftar jawaban`, `d-form:form-answers:mobile-view` di `resources/js` dan `tests/` → **0 hasil**.

Yang **tidak** dihapus karena dipakai jalur lain: `FormAnswerMobileList.vue`, `FormAnswerDetailSheet.vue`, `adapters/browser/storage` (`OpenRecruitment/Apply.vue`, `useGlobalQrScanPage`), `adapters/browser/mediaQuery` (`Navbar`, `SecretParty`, `Show.vue`), `FileTypeTile` (`FileUploadRow`, `ApplicantDetailContent`), `answerPreview`/`submissionFileUrl`/`formSubmissionReviewIsPending`/`submissionReviewBadge` (tabel desktop).

## Perubahan pendukung

1. **Pemisahan mobile/desktop memakai CSS**, bukan state JS: wrapper tabel `hidden overflow-x-auto md:block`, blok mobile tetap `md:hidden`. Konsekuensi: DOM mobile memuat tabel tersembunyi (≤ `per_page` baris), `display:none` tidak diekspos ke pembaca layar. Alternatif yang ditolak: menghidupkan kembali hook media-query khusus (menambah state baru justru setelah menghapus satu).
2. **Export rata kanan** di toolbar: kontainer aksi diberi `ml-auto`, jadi saat toolbar wrap di mobile tombol aksi menempel kanan; di desktop tidak berubah.
3. **Review per jawaban di mobile** kini lewat `FormAnswerDetailSheet` (tombol Terima/Tolak di sheet) dan aksi massal. Tombol inline hanya ada di kartu yang dihapus; keputusan ini disetujui user.

## Test

| File | Perubahan |
| --- | --- |
| `tests/e2e/form-answer-mobile-list.desktop.spec.ts` (baru) | 3 test deterministik memakai fixture admin (tanpa login per test): mobile 375 px hanya daftar + baris membuka detail (klik & Enter) + tanpa overflow; tombol Export di separuh kanan viewport; desktop 1280 px memakai tabel dan menyembunyikan daftar. |
| `tests/e2e/form-answers-mobile.spec.ts` | Test mobile ditulis ulang untuk daftar saja; assertion kartu/toggle/storage dihapus; dua selektor usang diperbaiki (`Pilih semua jawaban` dan asumsi `aria-checked="mixed"` yang tidak dijamin urutan baris). |

## Bukti verifikasi

| Perintah | Exit | Hasil |
| --- | --- | --- |
| `npm run typecheck` | 0 | bersih |
| `npm run lint` | 0 | 0 error, 1 warning pre-existing (`formBuilderAutosave.test.ts`) |
| `npx prettier --check` (file tersentuh) | 0 | bersih |
| `npm run test:e2e:types` | 0 | bersih |
| `npx playwright test form-answer-mobile-list.desktop.spec.ts` | 0 | **4 passed** (setup + 3 test) |
| `npm run build` (php shim ke container) | 0 | entry `app-*.js` 50.513 B / 15.912 B gzip |
| `npm run test:unit` | 1 | **1028 passed, 1 failed**; kegagalan berasal dari file di luar tiket: `resources/js/lib/buildUserIndexUrl.ts` (untracked, dibuat 17:10 oleh pekerjaan lain) yang melanggar guard `importBoundaries`. Seluruh test lain hijau. |

Metrik visual (`/tmp/opencode/answers-shots/`): pada 375 px `overflow = −10` (hanya gutter scrollbar), titik tengah tombol Export di **308 dari 375 px** (separuh kanan), daftar tampil, tabel tidak terlihat; pada 1280 px tabel tampil dan daftar tersembunyi.

## Status E2E lama (`form-answers-mobile.spec.ts`)

Spec ini memakai akun hardcoded `stress-user-1@stress.test` dan login sendiri di setiap test (bukan storageState), sehingga:
- di environment ini akun tersebut tidak ada (dataset stress tidak di-seed) → sempat gagal total;
- setelah akun dibuat, 15 login berurutan memicu throttle/dev-tools sehingga hasilnya flaky.

Baseline sebelum perubahan ini (kode + spec lama, akun sudah dibuat): **9 lulus, 6 gagal**. Setelah perubahan: kegagalan yang tersisa berada di kelas yang sama (login/throttle), bukan pada assertion yang saya ubah. Perilaku yang berubah sekarang diverifikasi oleh spec baru yang deterministik. Spec lama dicatat sebagai utang teknis tersendiri (sebaiknya migrasi ke fixture harness).

## Perbaikan environment (data/tooling, bukan kode)

1. `chmod -R a+rwX storage/app/e2e` di dalam container: folder heartbeat E2E dimiliki `www-data` sehingga host tidak bisa menulis dan seluruh run gagal di `global.setup`.
2. Membuat ulang akun `stress-user-1@stress.test` (super-admin) + 1 event lewat factory, karena `form-answers-mobile.spec.ts` meng-hardcode akun itu dan dataset stress tidak ada. Tidak ada data lama yang dihapus.

## Checklist

- [x] Mobile hanya menampilkan daftar; tabel tidak terlihat di 375 px.
- [x] Desktop tetap memakai tabel; daftar mobile tidak terlihat.
- [x] Tombol Export berada di sisi kanan pada mobile.
- [x] Detail jawaban terbuka dari klik baris dan dari keyboard Enter.
- [x] Review per jawaban tetap tersedia (detail sheet) dan aksi massal.
- [x] Tidak ada sisa impor/tipe/hook/storage key/testid dari fitur kartu (audit grep 0 hasil).
- [x] Typecheck, lint, prettier, e2e-types, build hijau; test baru 4/4 hijau.

## Risiko & tindak lanjut

- DOM mobile memuat tabel tersembunyi (≤ `per_page` baris). Jika halaman sering dibuka dengan `per_page=100` di perangkat rendah, pertimbangkan mengembalikan pemisahan berbasis media query generik (tiket terpisah).
- `docs/developer/sapto/design/DFORM-answers-mobile-views.md` masih mendeskripsikan fitur Kartu/Daftar yang kini tidak ada. Dokumen historis (changelog DFORM-212/216) sengaja tidak diubah; penghapusan/penandaan dokumen desain menunggu keputusan user.
- Spec `form-answers-mobile.spec.ts` tetap flaky di environment ini karena desain login per test; bukan regresi dari tiket ini (bukti baseline di atas).
- `npm run test:unit` penuh gagal 1 karena file untracked milik pekerjaan lain (`lib/buildUserIndexUrl.ts`), bukan bagian commit ini.
