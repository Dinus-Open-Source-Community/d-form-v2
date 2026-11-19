# Sapto Changes — 19 November 2026 (DFORM-TBD-TOAST)

## Ringkasan

Setiap aksi sukses kini menghasilkan tepat satu toast. Sebelumnya aksi berbasis Inertia memunculkan dua toast: satu dari `Inertia::flash('toast')` yang dikonsumsi `usePageFlashToast`, dan satu lagi dari `showSuccessToast()` di handler `onSuccess`.

| Issue | Commit |
| ----- | ------ |
| Toast sukses ganda pada aksi Inertia | `fix(ui): dedupe success toasts by relying on server flash for Inertia actions` |

## Aturan yang dipakai

- **Flash server adalah satu-satunya sumber** untuk aksi Inertia yang diakhiri redirect atau yang memang mem-flash (`ProfileController`, `EventController`, `FormController`). Pesannya sudah diterjemahkan di `lib/error-message.ts` (`humanizeErrorMessage`), jadi copy Indonesia tetap sama.
- **Toast klien tetap dipakai** untuk endpoint JSON/XHR (autosave, review jawaban, resend email, scan, forgot-password via adapter), aksi clipboard/unduhan, dan respons Inertia 200 tanpa flash (publish draft form via `updateFormSettings`, create event mode wizard).

## Perubahan

- Hapus `showSuccessToast` ganda di `useProfileAvatar` (unggah + hapus avatar), `useProfileIdentityForm`, dan `useProfilePasswordForm`; pembersihan state (`clearPending`, `committed`, `form.reset`) tetap.
- Hapus toast ganda pada edit event (`EventDashboardForm`), arsip/pulihkan event (`useDashboardEventShowPage`), hapus form (`EventShowAsideRail`), simpan + ubah status form (`Forms/Show.vue`), dan simpan form (`Forms/Create.vue`).
- Buang import/destructure yang menjadi tidak terpakai.
- Selaraskan assertion E2E dengan teks flash yang sekarang tampil: `Profil berhasil diperbarui.`, `Event berhasil diperbarui.`, dan `Kata sandi berhasil diperbarui.` (assertion negatif).

## Verifikasi

- `npx eslint` pada 8 file yang berubah: PASS.
- `npm run typecheck`: PASS.
- `npm run test:e2e:types`: PASS.
- `npx vitest run`: 108 file / 1126 test PASS.
- Bukti browser (Playwright MCP, `http://localhost:3000`):
  - simpan identitas → tepat satu toast `Profil berhasil diperbarui.`
  - unggah avatar → tepat satu toast `Foto profil berhasil diperbarui.`
  - hapus avatar → tepat satu toast `Foto profil dihapus.`
  - simpan edit event → tepat satu toast `Event berhasil diperbarui.`
- `tests/e2e/profile.desktop.spec.ts`: 7 passed (termasuk assertion toast baru).
- `tests/e2e/event-edit-save.desktop.spec.ts`: body test lulus dengan `E2E_ADMIN_EMAIL=superadmin@gmail.com` (assertion toast baru tercapai); suite tetap ditandai gagal oleh `e2e:cleanup` yang menabrak FK `events_created_by_foreign` saat menghapus user fixture — masalah pra-ada di `FixtureScope`, di luar perubahan ini.

## Temuan di luar scope

- `tests/e2e/support` cleanup menghapus `users` sebelum `events` sehingga `php artisan e2e:cleanup` gagal dan meninggalkan baris fixture.
- `.env.example` tidak menyertakan `E2E_*`; e2e harus dijalankan dengan variabel environment eksplisit dan kredensial super-admin untuk aksi lintas-owner.
