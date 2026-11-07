# Dokumentasi developer — Sapto

- [saptoChanges08-11-2026-DFORM-AUDIT-P2-006.md](./saptoChanges08-11-2026-DFORM-AUDIT-P2-006.md) | Redaksi PII dan raw exception terpusat di `LogRedaction`; job broadcast/OTP memakai konteks terstruktur dengan email ter-mask, `error_message` publik aman, unit/feature/regression dan 3 E2E browser happy/failure/anomali (AUDIT-P2-006).
- [saptoChanges08-11-2026-DFORM-BE-P3-009.md](./saptoChanges08-11-2026-DFORM-BE-P3-009.md) | Cookie sesi HTTPS-only dengan default production + fail-fast, middleware CSP/HSTS/XFO/nosniff/Referrer-Policy/Permissions-Policy, feature test konfigurasi produksi dan E2E browser + anomaly (BE-P3-009).
- [saptoChanges07-11-2026-DFORM-AUDIT-P2-010.md](./saptoChanges07-11-2026-DFORM-AUDIT-P2-010.md) | Terapkan `max_size` per field pada validasi upload (clamp ke batas global), pesan dinamis, default builder 5120 KB, feature test dan E2E browser happy/anomali (AUDIT-P2-010).
- [saptoChanges05-11-2026-DFORM-AUDIT-P1-002.md](./saptoChanges05-11-2026-DFORM-AUDIT-P1-002.md) | Upload jawaban form dan lampiran tim pindah ke disk privat dengan route berotorisasi + signed URL, fallback legacy public, command migrasi dry-run, regression test dan E2E happy/anomali (AUDIT-P1-002).
- [saptoChanges04-11-2026-DFORM-AUDIT-P1-007.md](./saptoChanges04-11-2026-DFORM-AUDIT-P1-007.md) | Idempotency, retry, timeout, dan `delivery_key` untuk seluruh job email; log direkonsiliasi sebelum send, `failed()` PII-free, feature regression dan E2E browser happy/anomali (AUDIT-P1-007).
- [saptoChanges04-11-2026-DFORM-BE-P3-007.md](./saptoChanges04-11-2026-DFORM-BE-P3-007.md) | Structured error logging public-safe untuk exception callback OAuth Google/GitHub dengan correlation ID dan regression feature/E2E (BE-P3-007).
- [saptoChanges04-11-2026-DFORM-BE-P1-005.md](./saptoChanges04-11-2026-DFORM-BE-P1-005.md) | Pisahkan validation `422` dari exception internal `500` pada penyimpanan field form, dengan request ID, logging public-safe, feature regression, dan browser anomaly E2E (BE-P1-005).
- [saptoChanges04-11-2026-DFORM-BE-P1-006.md](./saptoChanges04-11-2026-DFORM-BE-P1-006.md) | Throttle tiga mutation portal tracking OpRec (update, correction, feedback) dengan kunci sesi tracking + fallback IP, regression 429 dan E2E flood browser (BE-P1-006).
- [saptoChanges04-11-2026-DFORM-BE-P1-004.md](./saptoChanges04-11-2026-DFORM-BE-P1-004.md) | Claim broadcast atomik dengan conditional update + `affected rows`; job hanya di-queue oleh pemenang claim, retry recipient memakai conditional reset (BE-P1-004).
- [saptoChanges04-11-2026-DFORM-BE-P1-003.md](./saptoChanges04-11-2026-DFORM-BE-P1-003.md) | Pastikan job recruitment hanya di-enqueue setelah transaction commit dengan `afterCommit()` dan regression rollback/commit (BE-P1-003).
- [saptoChanges04-11-2026-DFORM-BE-P1-002.md](./saptoChanges04-11-2026-DFORM-BE-P1-002.md) | Perketat OAuth linking: subject ID utama, email hanya jika provider verified, hapus email sintetis `@github.local`, regression feature + E2E (BE-P1-002).
- [saptoChanges04-11-2026-DFORM-BE-P1-001.md](./saptoChanges04-11-2026-DFORM-BE-P1-001.md) | Patch `symfony/mime` ke `v7.4.22` untuk CVE-2026-45070, regression MIME test, dan evidence verifikasi (BE-P1-001).
- [saptoChanges04-11-2026-DFORM-I7E-REVERT.md](./saptoChanges04-11-2026-DFORM-I7E-REVERT.md) | Revert redesign tab Jawaban ke baseline `b9c3edf` dan rapikan tipe file lama sesuai prefix `I`/`G`/`T` (dform-i7e).
- [saptoChanges03-11-2026-DFORM-216.md](./saptoChanges03-11-2026-DFORM-216.md) | Tab Jawaban Tabel/Kartu, toolbar pencarian/filter/paginasi, dan review tanpa full reload (dform-i7e).
- [saptoChanges03-11-2026-DFORM-E62.md](./saptoChanges03-11-2026-DFORM-E62.md) | Deferred: runtime prod bukan scope agent. Fakta dikoreksi — queue worker sudah berjalan di container app, yang hilang hanya scheduler; 3 task terjadwal termasuk `otp:prune` (dform-e62).
- [saptoChanges03-11-2026-DFORM-WK3.md](./saptoChanges03-11-2026-DFORM-WK3.md) | Pertahankan entri beads/Dolt di `.gitignore` dengan alasan tertulis; kredensial `.beads-credential-key` tidak tercakup exclude lokal (dform-wk3).
- [saptoChanges03-11-2026-DFORM-7ZP.md](./saptoChanges03-11-2026-DFORM-7ZP.md) | Hapus modul broadcast legacy menyeluruh (route, controller, model, policy, service, job, factory, UI mati, test skip); modul `EmailBroadcast` tetap utuh (dform-7zp).
- [saptoChanges03-11-2026-DFORM-09W.md](./saptoChanges03-11-2026-DFORM-09W.md) | Keunikan `form_answers` portabel lewat `is_active` nullable + dua unique index komposit, expand/backfill/contract, dan audit jalur insert (dform-09w, dform-kzc).
- [saptoChanges03-11-2026-DFORM-AOA.md](./saptoChanges03-11-2026-DFORM-AOA.md) | Guard budget bundle di CI: entry ≤ 350 kB gzip, chunk non-entry ≤ 200 kB gzip, baseline entry 26,5 kB dan chunk terbesar 143,8 kB (dform-aoa).
- [saptoChanges03-11-2026-DFORM-19O.md](./saptoChanges03-11-2026-DFORM-19O.md) | Verifikasi baseline E2E Playwright terhadap kode aktual dan dokumentasi script `test:e2e*` di `README.md` (dform-19o).
- [saptoChanges01-11-2026-DFORM-ZBN.md](./saptoChanges01-11-2026-DFORM-ZBN.md) | Menyelaraskan `CONTEXT.md` dengan hooks, lazy bundle, komponen SEO, dan jumlah test class aktual di `dev-zapp`.
- [saptoChanges30-10-2026-DFORM-XXX.md](./saptoChanges30-10-2026-DFORM-XXX.md) | Konsolidasi engineering rules, workflow planning, test matrix, error contract, frontend boundaries, dan reliability rules.
- [saptoChanges29-10-2026-DFORM-SEC-UPLOAD.md](./saptoChanges29-10-2026-DFORM-SEC-UPLOAD.md) | Sentralisasi validasi upload 5 MB, pemeriksaan isi, dan re-encode gambar.
- [saptoChanges30-10-2026-DFORM-AUTH-GUARD.md](./saptoChanges30-10-2026-DFORM-AUTH-GUARD.md) | Guard root `/auth` agar user aktif langsung diarahkan ke `/` tanpa masuk history halaman auth.
- [saptoChanges30-10-2026-DFORM-FORM-SETTINGS-TAB.md](./saptoChanges30-10-2026-DFORM-FORM-SETTINGS-TAB.md) | Memindahkan aturan global form edit ke tab tingkat atas `Pengaturan Form` tanpa menduplikasi panel.
- [saptoChanges30-10-2026-DFORM-FORM-BUILDER-SCROLL.md](./saptoChanges30-10-2026-DFORM-FORM-BUILDER-SCROLL.md) | Mengunci shell editor form dan memusatkan scroll pada area field form.
- [saptoChanges30-10-2026-DFORM-FORM-SECTIONS.md](./saptoChanges30-10-2026-DFORM-FORM-SECTIONS.md) | Menambah section form bebas jumlah field, navigasi antarsection, dan validasi field wajib per section.
- [saptoChanges30-10-2026-DFORM-FORM-SECTION-ACTION.md](./saptoChanges30-10-2026-DFORM-FORM-SECTION-ACTION.md) | Memindahkan aksi tambah section ke boundary konten agar tetap mudah dijangkau tanpa sticky UI.
- [saptoChanges30-10-2026-DFORM-CI-EXTENSIONS.md](./saptoChanges30-10-2026-DFORM-CI-EXTENSIONS.md) | Memperbaiki quality gate GitHub Actions dengan runtime Node.js 24 dan ekstensi PHP lengkap.

Folder ini berisi catatan perubahan dan desain yang dikerjakan oleh **Sapto** pada branch/sesi pengembangan dashboard & UI terkait.

## Isi folder

| File                                                                         | Deskripsi                                                                                                                                                                                                        |
| ---------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [saptoChanges29-10-2026-DFORM-215.md](./saptoChanges29-10-2026-DFORM-215.md) | `check:row-ownership` membuktikan tiga pemeriksaan lewat ledger per run: sisa baris, marker ilegal dalam window, dan teardown yang menyentuh baris run lain (menutup temuan P1 TASK-024)                         |
| [saptoChanges29-10-2026-DFORM-039.md](./saptoChanges29-10-2026-DFORM-039.md) | P0-1 diperbaiki: `hideTitles` tidak lagi menyembunyikan tombol Simpan/Pratinjau; jalur simpan dipusatkan di `submitForm()`; tiga baris P0-1 hijau di desktop (klik ganda ditahan `:disabled="processing"`)       |
| [saptoChanges29-10-2026-DFORM-036.md](./saptoChanges29-10-2026-DFORM-036.md) | Baris P0-1 jalur simpan Create form ditulis dan dijalankan merah (3 baris, tanpa perubahan kode produksi); akar: `hideTitles` menyembunyikan seluruh toolbar termasuk Simpan/Pratinjau                           |
| [saptoChanges28-10-2026-DFORM-009.md](./saptoChanges28-10-2026-DFORM-009.md) | Harness e2e: project desktop/mobile, login admin atomik, fixture ber-marker + sweep, helper observasi; 7 perbaikan harness + 2 regresi suite ditutup; 8 fault-injection (3.16–3.25) direkam merah lalu di-revert |
| [saptoChanges28-10-2026-DFORM-212.md](./saptoChanges28-10-2026-DFORM-212.md) | Tampilan jawaban mobile Kartu/Daftar, tersimpan di localStorage, dengan tabel desktop tetap dipertahankan (TASK-212)                                                                                             |
| [saptoChanges28-10-2026-DFORM-207.md](./saptoChanges28-10-2026-DFORM-207.md) | Laporan audit ESLint + LSP: 611 file, 0 diagnostic ESLint; 78 file dicoba, cakupan LSP 8,7% (TASK-207)                                                                                                           |
| [saptoChanges27-10-2026-DFORM-201.md](./saptoChanges27-10-2026-DFORM-201.md) | Hapus semua trik supresi & non-null assertion di frontend, pindahkan fungsi runtime `types/form.ts` ke `lib/`, tegakkan lewat ESLint                                                                             |
| [saptoChanges27-10-2026-DFORM-202.md](./saptoChanges27-10-2026-DFORM-202.md) | Rate limit endpoint submit form (10/menit per user per form) dan check-email (`auth-email-check`), beserta bukti merah-hijau                                                                                     |
| [saptoChanges27-10-2026-DFORM-203.md](./saptoChanges27-10-2026-DFORM-203.md) | Pindahkan 7 validasi statis dari 9 controller ke Form Request; 4 kasus dinamis + 1 urutan precondition tetap di controller dengan alasan                                                                         |
| [saptoChanges27-10-2026-DFORM-204.md](./saptoChanges27-10-2026-DFORM-204.md) | Gate CI `quality.yml`: ESLint + unit test blocking di `dev`/`dev-zapp`/`temp`, formatting report-only, perbaikan opsi `tsconfig` (TASK-204)                                                                      |
| [saptoChanges28-10-2026-DFORM-208.md](./saptoChanges28-10-2026-DFORM-208.md) | Tipe payload metadata form turunan `FormRegistrationMetadata` (bukan `Record<string, unknown>`), doc hook fill, dan `development` masuk trigger CI (TASK-208)                                                    |
| [saptoChanges27-10-2026-DFORM-TBD.md](./saptoChanges27-10-2026-DFORM-TBD.md) | Revert kolom pratinjau live form builder; sidebar dashboard tidak dirender di halaman builder (editor + tab Jawaban)                                                                                             |
| [saptoBundleBaseline-DFORM-1487.md](./saptoBundleBaseline-DFORM-1487.md)     | Baseline bundel (DFORM-1487): entry gzip 25,56 KiB, 58 page lazy + 227 chunk, tabel byte gzip per chunk, aturan komparabilitas untuk pengukuran ulang 15.8                                                       |
| [saptoChanges24-10-2026-events.md](./saptoChanges24-10-2026-events.md)       | Pencarian & paginasi `/events` + `/browse`: cache payload mentah, negative cache + lock, FULLTEXT, cursor, rate limit 429 ramah, index ronde-2                                                                   |
| [saptoChanges24-10-2026-DFORM-TBD.md](./saptoChanges24-10-2026-DFORM-TBD.md) | Optimasi auth: code splitting per halaman, lottie lazy, rate limit login/register, handler 429, font & favicon                                                                                                   |
| [saptoChanges21-10-2026.md](./saptoChanges21-10-2026.md)                     | Aturan proyek (14 aturan baru), `CONTEXT.md`, stress harness k6, dataset 1 juta baris, benchmark query                                                                                                           |
| [saptoChanges10-06-2026.md](./saptoChanges10-06-2026.md)                     | Bundle submissions, member dashboard, reusable routes, re-registrasi, password reset                                                                                                                             |
| [saptoChanges04-05-2026.md](./saptoChanges04-05-2026.md)                     | Konsolidasi M1–M4, hapus Livewire/Filament, publik event dari DB                                                                                                                                                 |
| [saptoChanges19-04-2026.md](./saptoChanges19-04-2026.md)                     | Dashboard admin, halaman event, landing navbar, data seed, Docker dev                                                                                                                                            |

- Bagian **Stress harness: load test, query capture, report** (§10) di `saptoChanges21-10-2026.md`
  merinci `make stress-load`, artefak per run, dan cara baca `report.html`.

## Cara membaca (untuk tim)

1. Buka changelog **terbaru** (`saptoChanges27-10-2026-DFORM-202.md`) untuk perubahan terakhir.
2. Bagian **Ringkasan (TL;DR)** di setiap dokumen cocok untuk sync singkat.
3. Bagian **Checklist** di akhir dokumen bisa dipakai sebelum merge atau saat onboarding anggota baru.

## Hubungan dengan dokumen lain

- Perubahan backend terkait **`registered_count`**, validasi tanggal event, dan endpoint `registration-status` dijelaskan di [nafanChanges17-04-2026.md](../nafan/nafanChanges17-04-2026.md). Desain dashboard Sapto memanfaatkan data tersebut (misalnya statistik & list event).
- Dokumen instalasi dan struktur proyek tetap mengacu ke root `docs/` (misalnya `01-installation.md`, `02-directory-structure.md`).

Jika ada pertanyaan spesifik tentang komponen UI atau alur dashboard, rujuk ke file changelog di atas atau tanyakan langsung di channel tim.
