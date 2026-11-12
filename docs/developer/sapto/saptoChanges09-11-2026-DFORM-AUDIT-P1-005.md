# Sapto Changes — 9 November 2026 (AUDIT-P1-005)

## Ringkasan

Pengecekan peserta team/bundle berubah dari loop per anggota menjadi query
batch. Team 11 peserta turun dari **11 → 1** query cek peserta; bundle 10
anggota guest turun dari **21 → 2** query (ditambah cek leader terpisah) dan
tidak lagi tumbuh terhadap jumlah anggota. Pesan validasi, error key
`team_member_emails.{index}`, urutan first-fail, dan
`excludeRejectedSubmissions()` tidak berubah.

| Issue | Commit |
| ----- | ------ |
| AUDIT-P1-005 | `966816b` — `perf: batch team and bundle participant duplicate checks` |

Laporan lengkap: `docs/big-changes/patching-ticket/AUDIT-P1-005.md`.
Plan: `docs/big-changes/patching-ticket/AUDIT-P1-005-plan.md`.

## Akar masalah (bukti)

- `TeamRegistrationSubmitter::submit()` menjalankan `FormAnswer ... exists()`
  + `lockForUpdate()` per peserta (leader + anggota).
- `BundleRegistrationSubmitter::submit()` memanggil
  `BundleGuestDuplicateChecker::assertEmailAvailableForForm()` per email;
  checker menjalankan `invited_email`, `users`, dan `user_id` per email.
- `FormSubmissionController::assertBundleGuestsAvailable()` mengulang loop yang
  sama sebelum submit.
- Red test: team 11 peserta menghasilkan **11** query `user_id = ?`; bundle tidak
  punya query batch sama sekali.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `app/Services/Registration/BundleGuestDuplicateChecker.php` | `assertEmailAvailableForForm()` diganti `assertEmailsAvailableForForm(Form, array $emails)`: normalisasi `mb_strtolower(trim())`, lewati email kosong, satu query `User::whereIn(LOWER(email), ...)`, satu query `FormAnswer` aktif (`LOWER(invited_email) IN ... OR user_id IN ...`) dengan `excludeRejectedSubmissions()`. Error key/pesan dan index pertama yang duplikat dipertahankan. |
| `app/Services/Registration/TeamRegistrationSubmitter.php` | `whereIn('user_id', $participantIds)->excludeRejectedSubmissions()->lockForUpdate()->pluck('user_id')`; `pluck` setelah lock agar row benar-benar ter-lock di MySQL. |
| `app/Services/Registration/BundleRegistrationSubmitter.php` | Loop per email diganti satu panggilan batch; cek leader dan loop pembuatan baris tidak diubah. |
| `app/Http/Controllers/Dashboard/Events/Forms/FormSubmissionController.php` | Pre-check bundle memakai batch yang sama; helper pass-through `assertBundleGuestsAvailable()` dihapus. |
| `tests/Feature/Forms/TeamBundleParticipantQueryCountTest.php` (baru) | 12 test: query count team 11 peserta, duplikat team, query count bundle 10 anggota, duplikat guest/akun per index, first-index-wins, submission `rejected` re-registrasi, direct-checker invariance (2 vs 10 email = 2 query), isolasi form lain, member `rejected`/`expired` re-registrasi, email kosong tanpa query. |
| `tests/e2e/form-submission-boundary.desktop.spec.ts` | _(diisi dari lane browser: team happy, team duplikat, bundle happy, bundle akun duplikat)_ |

Tidak ada perubahan route, response contract, schema, enum, otorisasi, atau
`AGENTS.md`/`SKILL.md`.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Batch `whereIn` + `pluck` (team) | Satu statement, lock row eksplisit, hasil mudah di-assert. |
| Satu method batch di checker (bundle) | Satu implementasi dipakai controller + submitter; menghindari duplikasi. |
| Dua query bundle (bukan join) | Memetakan email → user id dan `invited_email`/`user_id` secara terpisah; index error tetap tepat. |
| Normalisasi `mb_strtolower(trim())` kedua sisi | Menyamakan map PHP dengan nilai DB untuk lowercase/trailing space; residual accent-only duplicate ditangkap unique index (didokumentasikan). |
| Pertahankan `first()` user semantics | Jika ada dua user dengan email beda kapitalisasi (SQLite), id pertama yang dipakai, sama seperti `User::...->first()` lama. |
| Loop pembuatan baris/token/`fresh()` tidak diubah | Di luar "pengecekan peserta"; dipisah ke `AUDIT-P1-005-F1..F3`. |

## Dampak & risiko

- Query cek peserta konstan; tidak ada perubahan perilaku validasi.
- `LOWER(email)`/`LOWER(invited_email)` non-sargable seperti sebelumnya.
  `users` masih covering index scan pada dataset besar; functional index dicatat
  di `AUDIT-P1-005-F4` (`Measured` oleh review: ±1,66 s pada 1 juta baris).
- Unique index guest case-sensitive di SQLite; di MySQL mengikuti collation
  kolom. Sama seperti sebelum perubahan.

## Verifikasi

Lihat `docs/big-changes/patching-ticket/AUDIT-P1-005.md` §Verifikasi untuk
perintah, exit status, hasil red/green test, regression, Pint, E2E browser, dan
review independen.
