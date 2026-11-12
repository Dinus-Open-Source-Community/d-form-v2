# Sapto Changes, 12 November 2026 (DFORM-FORM-ANSWERS-BULK)

## Ringkasan

Tab `Jawaban` halaman detail form tidak pernah menyambungkan seleksi checkbox ke
pipeline bulk review yang sudah ada di backend. Service, controller, request, route,
adapter, dan tipe sudah tersedia sebagai file untracked, tetapi halaman hanya
menyimpan `Set` id terpilih tanpa toolbar, tanpa hitungan, tanpa konfirmasi, dan
tanpa pemanggil endpoint. Perbaikan ini menambahkan satu hook alur bulk, toolbar
aksi massal, guard kelayakan yang sama dengan server, dan test unit + integrasi +
E2E.

| Issue | Commit |
| --- | --- |
| Tanpa tiket backlog, permintaan pemilik, 12-11-2026 | `PENDING` |

## Akar masalah

`resources/js/pages/Dashboard/Events/Forms/Show.vue` hanya memakai
`getVisibleSubmissionSelectionState`/`setVisibleSubmissionSelection` untuk
mengumpulkan pilihan. Tidak ada pemanggilan `reviewFormSubmissionsBulk`, tidak ada
UI aksi, dan tidak ada konfirmasi. Endpoint `PATCH
/admin/events/{event}/forms/{form}/submissions/bulk-review` sudah ada dan teruji
(`BulkFormAnswerReviewTest`, 25 tests), sehingga gap-nya murni di sisi UI.

Saat verifikasi E2E pertama, ditemukan bug kedua: `AlertDialogAction` dari Reka UI
membungkus `DialogClose`, sehingga `update:open(false)` terpicu di sekitar klik
konfirmasi. Implementasi awal menyimpan keputusan di state yang sama dengan
visibilitas dialog, sehingga keputusan terhapus sebelum handler `confirm` berjalan
dan tidak ada request yang terkirim. Hook sekarang memisahkan `pendingDecision`
dari `isConfirmOpen`, dan test regresi menahan perilaku ini.

## Perubahan

- `resources/js/hooks/useFormSubmissionBulkReview.ts` — satu alur stateful: seleksi
  per halaman (mempertahankan pilihan halaman lain), hitungan pilihan, state
  checked/indeterminate, guard baris tidak eligible, status dialog, satu request
  atomik, reload prop `submissions` parsial, dan pembersihan state di semua jalur.
- `resources/js/components/modules/dashboard/FormAnswerBulkReviewBar.vue` — toolbar
  `N jawaban dipilih` dengan `Terima semua`, `Tolak semua`, `Batalkan pilihan`, hint
  kegagalan guard, serta `ConfirmationModal` untuk kedua keputusan. Aksi memakai
  token `success`/`destructive` bergaya outline (sama dengan sheet detail): mobile
  berupa grid dua kolom selebar penuh + aksi sekunder penuh di bawahnya, desktop
  inline dengan lebar intrinsik dan `flex-wrap` agar tidak meluber.
- `resources/js/lib/formSubmissionSelection.ts` — `isSubmissionBulkReviewEligible`
  menyaring baris yang tidak boleh masuk batch: hanya status menunggu review dan
  pendaftaran tunggal (bukan tim/bundle). Aturan ini mencerminkan validasi server.
- `resources/js/constants/formAnswerReview.ts` — batas batch 100 sebagai konstanta
  runtime, sejalan dengan `FormAnswerReviewService::MAX_BULK_IDS`.
- `resources/js/pages/Dashboard/Events/Forms/Show.vue` — memakai hook dan toolbar;
  inline selection refs/fungsi dihapus. `FormAnswerMobileList.vue` menerima
  `ReadonlySet<string>`.
- Error handling klien: `403/404/409/422/429` lewat override toast; `409` memuat
  ulang daftar dan membersihkan pilihan, `422` mempertahankan pilihan agar bisa
  diperbaiki. Request ganda diblokir `submitting`.
- Test: 9 unit test hook, 8 unit test helper seleksi/eligibility, dan 3 skenario
  E2E (`form-answers-bulk-review.spec.ts`) yang berjalan di desktop dan mobile.

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `npm run typecheck` | PASS, exit 0 |
| `npm run typecheck:unit` | PASS, exit 0 |
| `npm run test:unit` | **979 passed**, 88 file (exit 0) |
| Unit scope bulk (`useFormSubmissionBulkReview` + `formSubmissionSelection`) | **17 passed**, 2 file |
| `npm run lint` | PASS, exit 0, satu warning lama `SAMPLE_FIELDS` tidak terpakai |
| `npx prettier --check "resources/js/**/*.{ts,vue,js}" "tests/e2e/**/*.ts" playwright.config.ts eslint.config.js "scripts/**/*.{js,mjs,ts}"` | PASS, exit 0 |
| `npm run test:e2e:types` | PASS, exit 0 |
| `podman exec d_form_app ./vendor/bin/pint --test` | PASS, 810 file |
| `podman exec d_form_app php artisan test --testsuite=Feature` | **1431 passed, 12 skipped**, 9161 assertions (exit 0) |
| `npx playwright test tests/e2e/form-answers-bulk-review.spec.ts` | **7 passed** (setup + 3 desktop + 3 mobile) |

## Batasan verifikasi

E2E berjalan terhadap server lokal yang sudah hidup (`http://localhost:3000`) dengan
admin seed `stress-user-1@stress.test`; spec memakai `storageState` admin dari
project `setup`, bukan login per test, agar tidak menabrak rate limit login
(5 percobaan/menit). Fixture dibuat lewat `tinker` di container dan dibersihkan di
`afterEach`. Antrean memakai Redis tanpa worker, sehingga email review hanya masuk
outbox/queue, tidak benar-benar terkirim.

## Rollback

- Tidak ada commit atau push.
- Perubahan hanya frontend; rollback cukup mengembalikan `Show.vue` dan menghapus
  hook/toolbar/konstanta baru. Endpoint backend tidak berubah sehingga tidak ada
  rollback schema.
