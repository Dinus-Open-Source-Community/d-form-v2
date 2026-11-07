# Sapto Changes — 08 November 2026 (AUDIT-P1-004)

## Ringkasan

Submit form sekarang memakai batas request eksplisit: validasi dinamis per mode
(`single`/`team`/`bundle`/`other`) dipusatkan di `FormSubmissionRules`, email
anggota tim/bundle punya satu implementasi (`MemberEmailListRules`), dan
`form_answers.answers` dibentuk oleh `FormAnswerInputMapper` hanya dari
`Validator::validated()`.

Dua celah tipe ditutup: array/objek yang dikirim ke field skalar ditolak
(`string` rule), dan elemen checkbox/multi-select wajib string (mencegah nested
array yang membuat ringkasan/ekspor 500). `team_member_emails` sekarang wajib
`list`, sehingga key sparse menjadi error validasi pada key `team_member_emails`
(redirect 302, seperti semua kegagalan validasi lain), bukan 500.

| Issue                                                   | Commit               |
| ------------------------------------------------------- | -------------------- |
| AUDIT-P1-004 — raw request dan inline validation submit | `8056209`, `94e48aa` |

## Perubahan

- `app/Enums/FormSubmissionMode.php` (baru): enum mode (`single`/`team`/`bundle`/`other`), tanpa dependensi Model/Service.
- `app/Services/Registration/FormSubmissionRules.php` (baru): `modeFor()` (null = konfigurasi kontradiktif), `forMode()` (private, rules per mode), `validate()` (dua guard team-size dengan urutan historis, lalu `validated()` saja; `validatePayload()` di controller dihapus).
- `app/Services/Registration/MemberEmailListRules.php` (baru): rules `required|array|list|size:N` + `email:rfc`, normalisasi lowercase/trim, guard self/duplikat; melempar `ValidationException` (key/pesan identik dengan sebelumnya).
- `app/Services/Registration/FormAnswerInputMapper.php` (baru): memetakan validated input ke `answers` (leader + slot bundle), hanya `UploadedFile` tervalidasi yang disimpan, field display-only dilewati, nama field bertitik dibaca dengan `Arr::get`.
- `app/Services/Form/RulesBuilder.php`: parameter opt-in `requireScalarString`; checkbox/multi-select selalu memberi rule `string` pada elemen.
- `app/Services/Registration/BundleSubmissionRules.php`: meneruskan flag scalar-string ke rules leader dan slot.
- `app/Http/Controllers/Dashboard/Events/Forms/FormSubmissionController.php`: guard tetap di depan; satu `try/catch (ValidationException)` untuk validasi + resolusi email; `array|RedirectResponse` dihapus; urutan side effect (team memetakan answers sebelum cek email, bundle setelah cek guest) dipertahankan.
- `tests/Feature/Forms/FormSubmissionBoundaryTest.php` (baru): 27 test / 121 assertion (single/team/bundle/other, field asing, array-ke-skalar, nama field bertitik, email sparse/non-list, checkbox nested array, guard precedence, upload, display-only).
- `tests/e2e/form-submission-boundary.desktop.spec.ts` (baru): 13 test browser + HTTP anomali dengan session nyata.

## Keputusan

- **Bukan `FormRequest`.** `FormRequest` divalidasi saat resolusi dependency, sebelum body controller, sehingga guard akses (blocked/closed/quota/duplikat) akan berjalan setelah validasi. Itu mengubah response untuk form tertutup + payload invalid. Boundary tetap berupa seam yang dipanggil setelah guard.
- **Dua tahap validasi dipertahankan.** Field divalidasi lebih dulu, lalu `team_member_emails`; menggabungkan keduanya akan menampilkan superset error pada payload yang gagal di dua tempat.
- **`string` skalar opt-in.** Jalur OpRec tidak mendapat guard skalar. Aturan elemen
  `string` untuk checkbox/multi-select berlaku di semua pemakai `RulesBuilder`
  (termasuk OpRec dan undangan tim); definisi OpRec yang di-seed belum punya field
  checkbox/multi, dan path undangan tim belum punya test (tiket lanjutan
  `AUDIT-P2-015`).
- **Whitelist checkbox dari `metadata.rules.in` tidak diaktifkan di tiket ini.** Hanya elemen `string` yang ditambahkan; mengubah accept-set checkbox existing adalah keputusan terpisah (tiket `AUDIT-P3-014`).

## Verifikasi

- Red: `FormSubmissionBoundaryTest` gagal untuk array-ke-field-skalar (tersimpan sebagai array, bukan ditolak); red tambahan untuk nama field bertitik, key email sparse (500), dan nested array checkbox (`Array to string conversion`).
- `php artisan test --filter=FormSubmissionBoundaryTest`: **27 passed (121 assertions)**.
- `php artisan test --filter=FormRegistrationTest`: **87 passed (627 assertions)**.
- `php artisan test tests/Feature/Forms tests/Unit/Services/MemberEmailListRulesTest.php`: **203 passed (1.158 assertions)**.
- `php artisan test tests/Feature/Recruitment/RecruitmentPublicApplyTest.php`: **17 passed (78 assertions)**.
- Full suite `php artisan test`: **1.074 passed, 7 skipped (6.240 assertions)**.
- E2E `--project=chromium-desktop --grep "AUDIT-P1-004"`: **14 passed** (1 setup + 13 test), dua run tanpa retry.
- `npx tsc -p tsconfig.e2e.json`: exit 0; Prettier spec: exit 0.
- Pint (22 file terdampak): PASS.

## Risiko residual

- `string` menolak nilai non-string untuk field skalar. Form browser selalu mengirim string; klien non-browser yang mengirim angka/array ke field teks sekarang menerima redirect dengan error validasi (302, bukan 422 JSON) — sama seperti kegagalan validasi lain di endpoint ini.
- N+1 lookup akun anggota tim tidak berubah (`AUDIT-P1-005`).
- Tiket lanjutan yang dibuka: `AUDIT-P2-017`, `AUDIT-P2-018`, `BE-P2-011`, `BE-P3-013`, `BE-P3-014`, `AUDIT-P3-012`, `AUDIT-P3-013`, `AUDIT-P3-014`, `BE-P3-015`.
