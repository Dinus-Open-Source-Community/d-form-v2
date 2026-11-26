# DFORM-XXX — Atribusi referral untuk form single, team, dan bundle

| Issue                                            | Commit                                                              |
| ------------------------------------------------ | ------------------------------------------------------------------- |
| DFORM-XXX (direct request PM; Jira key pending)  | Uncommitted at authoring; final hash recorded in the session report |

Klasifikasi: `feature`.

## Ringkasan (TL;DR)

Form pendaftaran (mode `single`, `team`, `bundle`) kini bisa mencatat atribusi peserta lewat
kode referral. Fitur mati secara default dan tidak mengubah perilaku form yang sudah ada.
Kode dikelola pemilik event/super-admin di tab **Pengaturan Form**; pemakaian kode dilaporkan
di tab **Jawaban** dengan filter per kode dan filter `Tanpa referral`. Satu unit submission
(jawaban tunggal, satu tim, satu grup bundle) maksimal menukar satu kode. Yang dibekukan saat
penukaran adalah snapshot peserta, atribusi, unit, dan waktu — riwayat tidak berubah ketika
user atribusi diganti namanya atau dihapus. Diskon/komisi belum ada di scope ini.

## Keputusan dan alternatif yang ditolak

| #   | Keputusan                                                                               | Alternatif ditolak                                                                   |
| --- | --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| 1   | Tabel khusus `referral_codes` + `referral_redemptions`.                                 | Menyimpan kode di `forms.metadata`: normalisasi metadata menulis ulang key dan tidak memberi unique index per form. |
| 2   | Kolom boolean `forms.referral_enabled`.                                                 | Key metadata: alasan sama dengan #1.                                                 |
| 3   | Unit `team`/`bundle` = baris ketua; `group_token` disimpan untuk bundle.                 | Redemption per anggota: satu tim akan menghitung pemakaian berkali-kali.             |
| 4   | Unique index `referral_redemptions.form_answer_id` sebagai satu-satunya dedupe unit.    | Pengecekan aplikasi saja: balapan dua submit bisa mencatat dua redemption.           |
| 5   | Redemption memakai transaksi submission yang sudah ada (lock event → form → kode).       | Transaksi terpisah: kuota dan `usage_count` bisa lepas dari jawaban yang tersimpan.  |
| 6   | Endpoint kode memakai limiter sendiri `form-referral-write` (user+event+form, 30/menit). | Tanpa limiter: akun sah bisa membuat kode tanpa batas; memakai ulang `form-submit`: bucket peserta dan bucket pengelola bercampur. |
| 7   | Guest submission dikecualikan (`GuestFormAccess` hanya untuk form non-registrasi).      | Menambah redemption guest: perlu tabel jangkar jawaban guest yang belum ada.         |
| 8   | Filter memakai query `referral=<kode>` / `none`; kode selalu memuat hyphen.             | Parameter terpisah untuk "tanpa referral": menambah state ganda di URL.              |
| 9   | Invarian atribusi eksklusif dijaga Form Request + service; yang dijaga CHECK database hanya `usage_count <= usage_limit`. | CHECK eksklusif pada `assigned_user_id`: MySQL menolak kolom FK `ON DELETE SET NULL` di CHECK (error 3823), dan setelah user dihapus kedua kolom memang NULL sehingga CHECK akan gagal saat delete. |
| 10  | Kartu referral dipasang di halaman `Show.vue`, bukan di dalam `FormSettingsPanel.vue`.  | Prop-drilling payload + 4 event melewati panel yang tidak memilikinya.              |
| 11  | Assign user memakai input UUID (`assigned_user_id`) di dialog.                          | Picker user baru: belum ada komponen pencarian user lintas role di builder.          |

## Perubahan

### Backend

| File                                                                                   | Perubahan                                                                                     |
| -------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `database/migrations/2026_11_23_100001_add_referral_enabled_to_forms_table.php`         | Kolom `forms.referral_enabled` default `false`.                                                 |
| `database/migrations/2026_11_23_100002_create_referral_codes_table.php`                 | Tabel kode: unique `(form_id, code)`, FK form cascade, FK user null-on-delete, CHECK kuota untuk mysql/mariadb/pgsql. |
| `database/migrations/2026_11_23_100003_create_referral_redemptions_table.php`           | Tabel redemption: unique `form_answer_id` + `(form_id, group_token)`, snapshot JSON, FK cascade. |
| `app/Models/ReferralCode.php`, `app/Models/ReferralRedemption.php`                      | Relasi, cast, `isUsableAt()`, `remainingUsage()`, `attributionIsLocked()`, `STATUS_VALID`.        |
| `app/Services/Form/ReferralCodeNormalizer.php`                                         | Format `PREFIX-BODY`, CSPRNG body dari alfabet bebas karakter ambigu.                           |
| `app/Services/Form/ReferralCodeService.php`                                            | Satu pintu tulis: create/update/delete, kunci identitas setelah pemakaian, batas kuota.         |
| `app/Services/Form/ReferralRedemptionService.php`                                      | Validasi kode terkunci + pencatatan redemption + increment `usage_count` dalam transaksi submit. |
| `app/Services/Form/ReferralRedemptionContext.php`                                      | Data beku yang ditulis ke snapshot.                                                            |
| `app/Http/Requests/ReferralCodeRequest.php` (+ Store/Update)                            | Normalisasi input, otorisasi `update` event, validasi format/atribusi eksklusif.                |
| `app/Http/Requests/UpdateReferralSettingsRequest.php`                                   | Toggle `referral_enabled`.                                                                     |
| `app/Http/Controllers/Dashboard/Events/Forms/FormReferralController.php`                | 4 endpoint: toggle, create, update, delete (409 saat kode sudah dipakai).                        |
| `app/Http/Controllers/Dashboard/Events/Forms/FormSubmissionController.php`              | `referral_code` dibaca dari input mentah, resolve di dalam transaksi, redeem setelah jawaban dibuat. |
| `app/Services/Registration/TeamRegistrationSubmitter.php`                               | Parameter opsional `?string $referralCode`, resolve sebelum reserve, redeem pada baris ketua.    |
| `app/Services/Registration/BundleRegistrationSubmitter.php`                             | Sama, plus `group_token` disimpan di redemption.                                                |
| `app/Http/Controllers/Dashboard/Events/Forms/FormController.php`                        | Payload `referral` (kode, opsi filter, URL penggunaan), filter `referral` sebelum count/paginate, `referral` per baris. |
| `app/Models/FormAnswer.php`                                                             | `referralRedemption()` + scope `whereReferralCode()` / `whereNoReferral()` (anggota grup ikut ketua). |
| `app/Services/Form/PublicFormFillPayload.php`, `FormFillController.php`                 | `referral_enabled` dikirim ke halaman fill.                                                     |
| `config/events.php`                                                                     | Blok `forms.referral`: panjang prefix/body, panjang body acak, jumlah percobaan, zona `Asia/Jakarta`. |
| `routes/web/admin/event.php`                                                            | 4 route referral di grup admin event, semuanya ber-`throttle:form-referral-write`.               |
| `app/Providers/AppServiceProvider.php`                                                  | Limiter `form-referral-write`: per user+event+form, default 30/menit.                            |

### Frontend

| File                                                                              | Perubahan                                                                                             |
| --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `resources/js/types/referral.ts`                                                   | `IReferralCode`, `IReferralPayload`, `ISubmissionReferral`, `IReferralCodeInput`.                       |
| `resources/js/lib/formReferral.ts`                                                 | Opsi filter (semua / tanpa referral / kode), nilai query, ringkasan kuota.                              |
| `resources/js/hooks/useFormReferralCodes.ts`                                       | Semua HTTP referral (Inertia): create/update/delete/toggle/filter; 409 ditahan dari modal Inertia.       |
| `resources/js/components/modules/builder/FormReferralSettingsCard.vue`             | Kartu Pengaturan: switch aktif, daftar kode, dialog create/edit; input identitas nonaktif saat terkunci; konfirmasi hapus, callback sukses, loading konsisten, dan relasi error/selection yang dapat dibaca assistive technology. |
| `resources/js/pages/Dashboard/Events/Forms/Show.vue`                               | Mount kartu (hanya purpose `registration`), filter Jawaban di luar daftar (tetap ada saat nol baris), kolom `Referral` (kode · atribusi · unit), dan empty state khusus filter aktif. |
| `resources/js/components/modules/dashboard/FormFillReferralField.vue`              | Input `Kode referral (opsional)` di halaman fill; nilai tidak diubah klien; helper/error terhubung dengan `aria-describedby`. |
| `resources/js/components/modules/dashboard/FormFillFieldsList.vue`, `Fill.vue`      | Render input hanya saat `referral_enabled` dan `allowed`, sekali pada section terakhir sebelum kirim.          |
| `resources/js/lib/formFillFields.ts`                                               | `createFormFillInitialValues` selalu menyemai `referral_code: ''` agar key ikut terkirim.               |
| `resources/js/types/form.ts`, `resources/js/types/event.d.ts`                      | `referral_enabled` pada payload fill; `referral` pada `IFormSubmission`.                                |

## Bukti verifikasi

| #   | Perintah                                                                                                                                                                                                                                  | Hasil                                  |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------- |
| 1   | `podman exec d_form_app php artisan test tests/Unit/Form/ReferralCodeNormalizerTest.php tests/Feature/Forms/ReferralSchemaTest.php tests/Feature/Forms/ReferralCodeManagementTest.php tests/Feature/Forms/ReferralRedemptionTest.php tests/Feature/Forms/ReferralReportingTest.php tests/Feature/Forms/ReferralConcurrencyTest.php` | `58 passed (435 assertions)`, exit 0   |
| 2   | `podman exec d_form_app php artisan test tests/Feature/Forms/FormRegistrationTest.php tests/Feature/Forms/GuestFormSubmissionTest.php tests/Feature/Forms/FormMultipleSubmissionTest.php tests/Feature/Forms/FormPurposeRulesTest.php tests/Feature/Forms/FormBuilderHttpTest.php tests/Feature/Forms/FormSubmissionControllerCharacterizationTest.php` | `153 passed (872 assertions)`, exit 0  |
| 2b  | `podman exec d_form_app php artisan test <6 file referral> tests/Feature/Forms/FormRegistrationTest.php tests/Feature/Forms/GuestFormSubmissionTest.php tests/Feature/Forms/FormMultipleSubmissionTest.php` | `163 passed (1120 assertions)`, exit 0  |
| 3   | `npm run test:unit`                                                                                                                                                                                                                       | `1290 passed (127 files)`, exit 0      |
| 4   | `npm run typecheck`                                                                                                                                                                                                                       | exit 0                                 |
| 5   | `npx eslint resources/js/components/modules/builder/FormReferralSettingsCard.vue resources/js/components/modules/dashboard/FormFillReferralField.vue resources/js/components/modules/dashboard/FormFillFieldsList.vue resources/js/pages/Dashboard/Events/Forms/Show.vue resources/js/hooks/useFormReferralCodes.ts tests/e2e/referral-attribution.desktop.spec.ts` | `No issues found`, exit 0 |
| 6   | `podman exec -w /app d_form_app npm run build`                                                                                                                                                                                            | exit 0, `built in 14.81s`; entry 16.19 kB gzip; largest chunk 145.66 kB gzip |
| 7   | `npx playwright test tests/e2e/referral-attribution.desktop.spec.ts --project=chromium-desktop`                                                                                                                                            | `2 passed`, exit 0; referral management, filter, and dialog groups/actions visible |
| 8   | `npx eslint resources/js/components/modules/builder/FormReferralSettingsCard.vue resources/js/pages/Dashboard/Events/Forms/Show.vue tests/e2e/referral-attribution.desktop.spec.ts`                                                      | `No issues found`, exit 0 |
| 9   | `npm run typecheck`                                                                                                                                                                                                                       | exit 0 |
| 10  | `npm run test:unit`                                                                                                                                                                                                                        | `1290 passed (127 files)`, exit 0 |
| 11  | `podman exec -w /app d_form_app npm run build`                                                                                                                                                                                            | exit 0, `built in 15.24s`; largest chunk 145.66 kB gzip |
| 12  | `/home/zappto/.config/opencode/skills/impeccable/scripts/impeccable detect --json resources/js/components/modules/builder/FormReferralSettingsCard.vue`                                                                                   | `[]`, exit 0 |
| 13  | `agent-browser` desktop/mobile snapshot after opening dialog                                                                                                                                                                             | headings `Atribusi`, `Format kode`, `Batas penggunaan`, `Periode berlaku`, plus `Batal` and `Simpan` visible at 390×844 |
| 7   | `podman exec d_form_app php artisan test tests/Feature/Forms/ReferralConcurrencyTest.php tests/Feature/Forms/FormSubmissionThrottleTest.php` (bagian dari run gabungan 63 test)                                                                                        | `63 passed (505 assertions)`, exit 0   |
| 8   | `E2E_BASE_URL=http://localhost:3000 E2E_ARTISAN='podman exec -i -w /app d_form_app php artisan' E2E_ADMIN_EMAIL=admin@gmail.com E2E_ADMIN_PASSWORD='admin password' npx playwright test tests/e2e/referral-attribution.desktop.spec.ts --project=chromium-desktop` | `2 passed` (setup + spec), exit 0 |
| 9   | `podman exec d_form_app php artisan migrate --path=<3 migrasi referral>` | 3 migrasi `DONE` di MySQL 9.0.1 setelah CHECK eksklusif dihapus |
| 10  | Runtime MySQL (tinker, rollback): buat kode ber-`assigned_user_id`, `forceDelete` user, lalu `usage_count = usage_limit + 1` | `after_delete_assigned=NULL`, `check_usage=ENFORCED` |

Ukuran bundel setelah perubahan (gzip):

- entry `app-*.js`: **16,20 kB** (budget ≤ 350 kB) — lulus.
- chunk terbesar: `index-DWb8NmWu.js` **145,66 kB** (batas chunk page 200 kB) — lulus.
- dua komponen baru ikut chunk halaman pemakainya (`Show-*` 43,72 kB gzip, `Fill-*` 9,03 kB gzip).

Catatan: angka **sebelum** perubahan tidak diambil di sesi ini karena build awal host gagal
(`php` tidak ada di host, build wajib di container). Klaim performa dibatasi pada angka sesudah
dan status budget, bukan perbandingan delta.

## Bug yang ditemukan dan diperbaiki selama verifikasi

1. **Scope referral hilang (Reproduced).** Filter Jawaban memanggil scope Eloquent yang belum ada,
   sehingga request berfilter menghasilkan 500. Scope `whereReferralCode` dan `whereNoReferral`
   ditambahkan ke `FormAnswer`, memakai `EXISTS` terhadap redemption dan relasi unit/team/bundle.
2. **Bulk selection mengabaikan filter referral (Reproduced).** ID lintas halaman tetap mengambil
   semua jawaban eligible. Query selectable kini memakai filter referral yang sama.
3. **Filter stale saat referral dinonaktifkan (Reproduced).** Query `?referral=` kini diabaikan
   saat fitur referral mati, dan payload mengembalikan filter `null`.


4. **Migrasi gagal di MySQL (Reproduced).** CHECK `referral_codes_attribution_exclusive`
   bertabrakan dengan FK `assigned_user_id ... ON DELETE SET NULL` (MySQL 3823), sehingga
   `migrate` berhenti dan setiap halaman form 500. Perbaikan: CHECK eksklusif dihapus (invarian
   dijaga Form Request + service, sudah diuji), CHECK kuota dipertahankan. Diverifikasi di MySQL
   9.0.1: delete user menyisakan baris dengan `assigned_user_id = NULL`, dan pelanggaran kuota
   ditolak database.
5. **Filter tidak bisa dilepas saat hasil kosong (Reproduced via E2E).** Empty state
   menggantikan seluruh blok daftar termasuk select filter. Perbaikan: filter dipindah ke bar
   di atas daftar sehingga tetap tampil pada kedua keadaan; E2E kini menempuh jalur
   `UDIN-AB12` (kosong) → pilih `Tanpa referral` dari select.

## Penyimpangan yang dicatat

1. Kartu referral dipasang langsung di `Show.vue`, bukan di dalam `FormSettingsPanel.vue` seperti
   rencana (lihat keputusan #9).
2. Assign user memakai input UUID, bukan picker user (keputusan #10). Validasi server tetap
   `exists:users,id` dengan `deleted_at IS NULL`.
3. Race paralel (dua proses benar-benar bersamaan) tidak disimulasikan: PHPUnit memakai sqlite
   `:memory:` satu koneksi, sehingga `lockForUpdate` tidak bisa dibuktikan lewat blocking.
   Yang dibuktikan: keputusan kuota selalu membaca ulang baris kode (bukan state lama), unique
   index `form_answer_id` menolak redemption kedua, dan kegagalan kode menggagalkan seluruh
   transaksi submission (tidak ada jawaban/anggota tim yang tertinggal). Bukti `lockForUpdate`
   pada tingkat SQL: `Confirmed` dari source `ReferralRedemptionService::resolveLocked`.
4. E2E dijalankan dari host; direktori `storage/app/e2e` milik uid container, jadi hak tulis
   sementara dinaikkan ke 777 untuk run dan dikembalikan ke 775 setelah run selesai. Run E2E
   dari host pada worktree ini memerlukan langkah yang sama.
5. E2E dibatasi pada UI pengelolaan kode + filter Jawaban. Penukaran kode oleh peserta dibuktikan
   di PHPUnit (`ReferralRedemptionTest`, 10 kasus) karena alur peserta butuh login akun member
   yang tidak tersedia sebagai storage state default.

## Checklist

- [x] Referral hanya untuk form pendaftaran: toggle ditolak (422) pada form `other`, input fill tidak tampil, redemption tidak pernah terjadi.
- [x] Fitur default mati; form lama tidak berubah perilaku (`FormRegistrationTest`, `GuestFormSubmissionTest` hijau).
- [x] Kode unik per form, format `PREFIX-BODY`, body acak dari CSPRNG.
- [x] Atribusi eksklusif: user aktif **atau** nama custom.
- [x] Kode terpakai tidak bisa diubah identitas/atribusinya dan tidak bisa dihapus (409), hanya dinonaktifkan.
- [x] `usage_limit` kosong = tanpa batas; nilai tidak boleh turun di bawah `usage_count`.
- [x] Satu unit submission = satu redemption (unique index + transaksi submit).
- [x] Batas kuota diuji ulang setelah state berubah dari worker lain; kegagalan kode me-rollback jawaban (single dan team).
- [x] Submit dibatasi `throttle:form-submit`; tulis kode dibatasi `throttle:form-referral-write` (429 + `Retry-After`, tanpa baris tertulis).
- [x] Snapshot peserta/atribusi bertahan setelah user dihapus.
- [x] Purge event/form menghapus kode dan redemption.
- [x] Filter Jawaban dan deep link `?tab=jawaban&referral=<kode>`.
- [x] Lint, typecheck, unit test, build hijau.
- [x] E2E desktop referral: `2 passed` (setup login admin + spec), exit 0.
- [ ] Jira dipindah ke In Review oleh manusia.
