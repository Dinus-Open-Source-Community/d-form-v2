# Sapto Changes — 27 Oktober 2026 (DFORM-203)

Memindahkan 12 validasi inline di 9 controller ke Form Request (aturan P1 nomor 5). Tujuh validasi statis pindah ke Form Request baru; lima tetap di controller dengan alasan tertulis (empat aturan dinamis + satu validasi yang urutan responsnya bergantung precondition token). Kontrak request/response ke frontend tidak berubah.

> **Nomor tiket:** `DFORM-203`.

| Kolom  | Isi |
| ------ | --- |
| Issue  | DFORM-203 |
| Commit | `refactor(http): pindahkan validasi inline controller ke Form Request` |
| Branch | `task-203-pindahkan-12-validasi-inline-di-9-controller-ke` |

---

## Ringkasan (TL;DR)

| Area | Sebelum | Sesudah |
| ---- | ------- | ------- |
| Validasi statis di badan controller | 8 kemunculan | 1 (undangan tim, urutan precondition) |
| Validasi yang sengaja tetap di controller | — | 5 (4 dinamis + 1 urutan precondition) |
| Form Request baru | — | 7 |
| Key error & pesan validasi | — | identik (dibuktikan test) |
| Whitelist upload gambar | `image\|mimes:png,jpg,jpeg,gif,webp\|max:2048` | sama |

## Peta 12 kemunculan

| File | Baris awal | Bentuk | Keputusan |
| ---- | ---------- | ------ | --------- |
| `EventsController` | 20 | `$request->validate` | pindah → `PublicEventSearchRequest` |
| `Dashboard/Broadcasts/BroadcastController` | 198 | `$request->validate` | pindah → `Broadcast/SendBroadcastTestRequest` |
| `Dashboard/Broadcasting/BroadcastInlineImageController` | 29 | `$request->validate` | pindah → `Broadcasting/StoreBroadcastInlineImageRequest` |
| `Dashboard/User/TeamInvitationController` | 112 | `$request->validate` | **tetap** — lihat "Validasi undangan tim yang dipertahankan" di bawah |
| `Dashboard/User/TeamInvitationController` | 174 | `Validator::make` | **tetap** — aturan dibangun dari `form_fields` appendable milik form (runtime) |
| `Dashboard/Events/Forms/FormImageUploadController` | 20 | `$request->validate` | pindah → `StoreFormImageRequest` |
| `Dashboard/Events/Forms/FormAnswerReviewController` | 45 | `$request->validate` | pindah → `ReviewFormAnswerRequest` |
| `Dashboard/Events/Forms/FormSubmissionController` | 96 | `Validator::make` | **tetap** — aturan dibangun dari field form (runtime) |
| `Dashboard/Events/Forms/FormSubmissionController` | 225 | `Validator::make` | **tetap** — `size:{memberSlots}` dari `team_size` form (runtime) |
| `Dashboard/Events/Forms/FormSubmissionController` | 326 | `Validator::make` | **tetap** — idem, jalur tim |
| `Dashboard/Recruitment/RecruitmentPeriodController` | 532 | `$request->validate` | pindah → `Recruitment/SendPeriodQrRequest` |
| `Dashboard/Recruitment/RecruitmentInterviewExportController` | 89 | `$request->validate` | pindah → `Recruitment/ExportInterviewReportRequest` |

Hasil akhir `grep -rn -e "->validate(" -e "Validator::make" app/Http/Controllers` → 5 baris, semuanya terdaftar dan beralasan di atas.

## Validasi undangan tim yang dipertahankan

`TeamInvitationController::update` (baris 112) **tidak** dipindah ke Form Request. Payload (`invitation_decision`, `decline_reason`) memang statis, tetapi validasi di endpoint ini hanya dijalankan setelah tiga precondition runtime: token harus milik user (403), invitation harus `registration_role = Member` dan form-nya tim/bundle (404), lalu status harus `Pending` (redirect 302 ke halaman show). Form Request selalu memvalidasi lebih dulu, sehingga non-owner dengan payload rusak akan menerima 422/redirect berisi error, bukan 403, dan undangan yang sudah dijawab akan menerima 422, bukan redirect.

Mengembalikan urutan itu di `authorize()` tidak mungkin tanpa mengubah perilaku: `authorize()` hanya bisa mengembalikan boolean (403), sedangkan precondition status hari ini me-redirect (302). Karena itu alasan satu baris: **validasi bergantung pada precondition runtime yang belum diketahui saat validasi, dan urutan 403/404/302 sebelum 422 adalah kontrak respons yang ada.** Regression test `FormRegistrationTest::test_invitation_checks_ownership_and_pending_state_before_validation` mengunci urutan ini (merah saat endpoint sempat memakai Form Request: non-owner menerima 302 berisi error, bukan 403).

## Form Request baru & otorisasi

| Form Request | `authorize()` (sama dengan controller sebelumnya) |
| ------------ | ------------------------------------------------ |
| `Requests/PublicEventSearchRequest` | `true` — halaman publik; proteksi tetap dari middleware `throttle:event-search` (controller tidak punya otorisasi eksplisit, perilaku dipertahankan) |
| `Requests/Broadcast/SendBroadcastTestRequest` | `can('view', broadcast)` |
| `Requests/Broadcasting/StoreBroadcastInlineImageRequest` | `can('update', broadcast)` |
| `Requests/StoreFormImageRequest` | `can('update', event)` |
| `Requests/ReviewFormAnswerRequest` | `can('review', formAnswer)` **dan** `can('update', event)` |
| `Requests/Recruitment/SendPeriodQrRequest` | `can('sendQr', period)` |
| `Requests/Recruitment/ExportInterviewReportRequest` | `can('recruitment.interviews.schedule')` |

Penempatan mengikuti pola yang ada: `Broadcast/`, `Broadcasting/`, `Users/`, `Recruitment/` sudah ada. Dua request domain event (`PublicEventSearchRequest`, `StoreFormImageRequest`, `ReviewFormAnswerRequest`) diletakkan di root `app/Http/Requests/` karena seluruh request event/form yang sudah ada memang di root (`StoreEventRequest`, `StoreEventFormRequest`, `IndexEventRequest`); tidak dibuat folder baru supaya domain event tidak terpecah dua lokasi.

`ExportInterviewReportRequest::COLUMN_KEYS` menjadi satu sumber daftar kolom sah; controller memakai konstanta itu untuk default dan filter kolom, sehingga daftar tidak diduplikasi.

## Perilaku yang dipertahankan

- **Key error & pesan**: `q`, `page`, `email`, `file`, `invitation_decision`, `decline_reason`, `review_status`, `include_sent`, `scope`, `columns.*` tidak berubah. Pesan kustom inline image dipindah apa adanya; `decline_reason` tetap di-trim di controller sebelum `$request->validate` (validasi endpoint itu tetap inline).
- **Upload tidak melemah**: `required|image|mimes:png,jpg,jpeg,gif,webp|max:2048` (form image) dan aturan inline image dengan `BroadcastInlineImageService::MAX_UPLOAD_KB` tetap sama.
- **Urutan validasi vs 404**: pada review submission & unggah gambar form, Form Request memvalidasi sebelum controller memeriksa kecocokan `form_id`/`event_id`. Payload tidak valid pada URL yang ids-nya tidak cocok kini 422 (bukan 404); frontend tidak pernah mengirim kombinasi itu, dan perilaku untuk payload valid tetap 404.
- **Urutan validasi vs status draft (inline image)**: cek `status === Draft` tetap di controller (bukan aturan validasi), sehingga payload file tidak valid pada broadcast non-draft kini menghasilkan 422 validasi `file`, bukan 422 pesan "Gambar hanya dapat diunggah saat draft". Pesan status tetap muncul untuk file yang valid; frontend hanya menampilkan editor pada broadcast draft.

## Test (gate AGENTS.md)

Test ditulis lebih dulu, dijalankan terhadap implementasi inline lama (hijau = mengunci perilaku), lalu dipakai sebagai bukti merah lewat mutasi aturan setelah refactor (satu aturan dilonggarkan → test merah → dipulihkan → hijau).

| Kelompok | Test | Bukti merah (mutasi) |
| -------- | ---- | -------------------- |
| Events CRUD (publik) | `tests/Feature/EventListValidationTest.php` | `max:100` dihapus → `q` 101 karakter jadi 200 |
| Form submission/review | `FormRegistrationTest::test_review_rejects_unknown_status_on_review_status_key` | `Rule::enum` → `string` → status `unknown-status` lolos |
| Recruitment period/export | `RecruitmentInterviewExportTest::test_export_rejects_unknown_{scope,column}...`, `RecruitmentQrBulkServiceTest::test_bulk_qr_send_route_rejects_non_boolean_include_sent` | `in:` / `boolean` dihapus → filter tidak valid lolos |
| Team invitation | `FormRegistrationTest::test_invitation_keeps_invitation_decision_and_decline_reason_error_keys`, `test_invitation_checks_ownership_and_pending_state_before_validation` | `Rule::requiredIf` dihapus → reject tanpa alasan lolos; endpoint dipindah ke Form Request → urutan 403/302 berubah jadi 422 (test merah, lalu validasi dikembalikan ke controller) |
| Broadcast/image | `FormImageUploadValidationTest`, `BroadcastInlineImageValidationTest` | `image`+`mimes` dihapus → PDF diterima |

Catatan harness: modul broadcast nonaktif (`FEATURE_BROADCAST=false`) sehingga route tidak terdaftar dan seluruh test lamanya `markTestSkipped`. Test inline image baru menyalakan flag lewat `$_SERVER['FEATURE_BROADCAST']` di `setUpBeforeClass` (sebelum app boot) lalu membersihkannya di `tearDownAfterClass`; hanya berlaku di proses test.

Hasil nyata (`php artisan test`, dijalankan dari container terpisah yang me-mount worktree ke `/app` — lihat catatan di bawah):

- Suite penuh: **809 lulus, 1 gagal, 41 dilewati** (5034 assertion).
- Kegagalan: `RecruitmentSecurityTest::s05` (XSS di halaman tracking). Gagal karena harness tidak punya server SSR Inertia: tanpa SSR, `@inertia` memakai `<script type="application/json">` mentah (`config('inertia.use_script_element_for_initial_page') = true`), sedangkan di container dev halaman dirender SSR sehingga assertion lulus. Test ini tidak menyentuh kode yang diubah.
- 41 dilewati = suite broadcast (`FEATURE_BROADCAST=false`), test MySQL-only/FULLTEXT, dan kasus stress yang butuh MySQL.
- `./vendor/bin/pint --test` pada 21 file yang diubah: bersih.
- `lsp_diagnostics` pada 16 file produksi yang diubah: nol error yang berasal dari perubahan ini. Worktree tidak punya direktori `vendor/`, sehingga language server tidak bisa me-resolve simbol Laravel sama sekali: seluruh error yang muncul berupa `Function 'abort_unless'/'response'/'config'/'now'/'route' tidak ditemukan` (terjadi juga di file yang tidak diubah), ditambah turunannya — `FormRequest` dianggap bukan turunan `Illuminate\Http\Request`, dan `abort()` tidak dianggap menghentikan alur sehingga `$locked` masih terbaca `FormAnswer|null`. Tidak ada satu pun yang bertahan bila subclass dan helper diverifikasi langsung di vendor (`FormRequest extends Request`, `abort()` melempar `HttpException`).
- E2E: tidak ada flow yang bisa dijalankan. `e2e_list_flows` (sistem e2e workspace) mengembalikan **0 flow**; spec Playwright repo hanya berjalan di host terhadap checkout root (`:3000`), bukan worktree ini, dan binary browser tidak ada di container — aturan proyek melarang memasang dependensi browser baru. Perubahan backend-only tanpa perubahan markup, jadi tidak ada regresi UI yang perlu dibuktikan lewat browser.

### Verifikasi ulang (28-10-2026)

Dijalankan ulang dari worktree (container `d_form_app`, `vendor` disalin nyata + `database/seeders/Stress` dari root):

| Perintah | Hasil |
| -------- | ----- |
| `tests/Feature/{EventListValidation,Broadcasting/BroadcastInlineImageValidation,Forms/FormImageUploadValidation,Recruitment/RecruitmentInterviewExport}` | 16 lulus (85 assertion) |
| `FormRegistrationTest` filter review + invitation | 4 lulus (12 assertion) |
| `RecruitmentQrBulkServiceTest` filter bulk QR | 14 lulus (64 assertion) |
| `FormRegistrationTest` penuh + `EventListPaginationTest` + `Broadcast*` + `Broadcasting` | 97 lulus, 35 dilewati (broadcast nonaktif / MySQL-only) |
| Mutasi merah: `Rule::enum` → `string` pada `review_status` | test 422 gagal (bukti test benar-benar mengunci aturan), dipulihkan |
| Mutasi merah: `in:` dihapus pada `scope` | test 422 gagal, dipulihkan |
| Setelah review: `FormRegistrationTest` penuh | 86 lulus (683 assertion) |
| Setelah review: `EventListValidation` + `BroadcastInlineImageValidation` + `FormImageUploadValidation` + `RecruitmentInterviewExport` + `RecruitmentQrBulkService` | 30 lulus (149 assertion) |
| `pint --test` 16 file | bersih |
| `lsp_diagnostics` 16 file | 2 error **pre-existing**, bukan dari refactor: `FormImageUploadController:35` (`storeAs()` bertipe `string\|false`) dan `TeamInvitationController:51/55` (`__()` bertipe `string\|array` untuk argumen `HttpException`). Keduanya ada di base `1104063` dan tidak diubah tiket ini. |

### Cara menjalankan test worktree (dicatat untuk tiket berikutnya)

`vendor/composer/autoload_psr4.php` menghitung `$baseDir = dirname($vendorDir)`, jadi bila `vendor/` di worktree hanya **symlink** ke root, realpath-nya jatuh ke `/app` dan `php artisan test` diam-diam menguji checkout root. Resep yang terbukti (28-10-2026):

1. `rm vendor` lalu `cp -a <root>/vendor ./vendor` (107 MB, gitignored) — salinan nyata, bukan symlink.
2. `cp -a <root>/database/seeders/Stress/. database/seeders/Stress/` (gitignored; classmap butuh file ini saat boot command stress).
3. `podman exec -i -w <worktree> d_form_app php artisan test <path>`.
4. Setelah selesai: hapus `vendor` salinan dan kembalikan symlink `vendor -> ../../../vendor`.

`bootstrap/ssr`, `public/build`, dan `public/hot` sudah ada di worktree sehingga tidak perlu di-mount. Container terpisah dengan worktree di `/app` (cara yang dipakai run pertama) tetap sah, tetapi menyalin vendor lebih sederhana dan tidak perlu me-restart container.

### Temuan di luar cakupan (tidak diperbaiki)

`BroadcastController::testSend` (baris 198) dan `app/Jobs/SendBroadcastJob.php:55` memanggil `new BroadcastMail($broadcast, $email)`, padahal konstruktor `BroadcastMail` butuh 4 argumen (`subjectLine`, `htmlBody`, `textBody`, `broadcastId`) → `TypeError` begitu endpoint/job itu benar-benar dijalankan. Sudah ada di base `1104063` dan tidak diubah tiket ini (bukan regresi), tapi tetap fatal. Pola yang benar ada di `BroadcastTestController`: `new BroadcastMail($subject, $html, $text, $broadcast->id)`. Tidak diperbaiki di sini karena menentukan subject/body email uji adalah keputusan produk, sedangkan tiket ini refactor validasi murni.

## Review keputusan (gate langkah 2)

- **Best practice?** Ya — aturan statis di Form Request adalah aturan repo (`docs/rules/back-end.md`, P1 nomor 5) dan otorisasi ikut pindah ke `authorize()`.
- **Optimal?** Ya — tidak ada layer baru: 7 file request tipis, tanpa service/abstraksi tambahan; daftar kolom export tetap satu konstanta.
- **Opsi lain:** membuat Form Request untuk kasus dinamis (form submission, appendable fields undangan) **ditolak** — aturannya bergantung data form yang baru diketahui saat runtime, sehingga request harus membaca model dan memaksa redirect sukses/gagal buatan; hasilnya lebih buruk daripada `Validator::make` yang sudah ada.
- **Opsi lain:** `RespondTeamInvitationRequest` sempat dibuat lalu **dibatalkan** setelah review — memindahkannya menggeser 403/404/302 menjadi 422 untuk non-owner dan undangan yang sudah dijawab (lihat "Validasi undangan tim yang dipertahankan").

### Perbaikan dari review (28-10-2026)

Reviewer menandai satu regresi respons yang nyata: `RespondTeamInvitationRequest` memvalidasi payload sebelum controller memeriksa kepemilikan token dan status pending.

- Bukti merah: `test_invitation_checks_ownership_and_pending_state_before_validation` gagal pada versi Form Request — `Expected response status code [403] but received 302` + error "The selected invitation decision is invalid."
- Perbaikan: validasi `invitation_decision`/`decline_reason` dikembalikan ke controller (inline `$request->validate`, trim tetap dijalankan sebelum validasi), file `app/Http/Requests/Users/RespondTeamInvitationRequest.php` dihapus.
- Sesudah perbaikan: kedua test undangan hijau (`2 passed, 9 assertions`), `FormRegistrationTest` penuh `86 passed (683 assertions)`.
- Nuansa inline image yang ditandai reviewer (file tidak valid pada broadcast non-draft → 422 validasi `file`, bukan 422 "draft only") **dipertahankan apa adanya**: keduanya 422 untuk user yang sama dan editor hanya membaca `message`; memaksa urutan itu berarti melempar 422 dari `authorize()`, pola yang tidak dipakai Form Request mana pun di repo ini.

## Checklist

- [x] `grep -rn -e "->validate(" -e "Validator::make" app/Http/Controllers` → 5 kasus (4 dinamis + 1 urutan precondition), terdaftar di atas
- [x] Setiap Form Request baru punya `authorize()` yang mempertahankan otorisasi lama + doc 1–2 baris
- [x] Key error & pesan identik (test)
- [x] Aturan upload tidak melemah
- [x] Test merah-sebelum-hijau (mutasi) untuk 7 aturan + `q`
- [x] `pint --test` bersih untuk file yang diubah
- [x] E2E — 0 flow tersedia di workspace (`e2e_list_flows`); spec Playwright repo tidak menyasar worktree & binary browser tidak ada di container (lihat Verifikasi)
- [x] Changelog + baris di `README.md`
