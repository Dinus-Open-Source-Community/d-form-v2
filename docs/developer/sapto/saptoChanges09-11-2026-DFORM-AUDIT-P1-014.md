# Sapto Changes — 9 November 2026 (AUDIT-P1-014)

## Ringkasan

Autosave form builder tidak punya state `error`, tidak punya retry eksplisit,
dan tidak punya single-flight: debounce, `onUnmounted`, serta manual save dapat
menjalankan `save()` bersamaan, sehingga snapshot lama bisa selesai terakhir dan
menimpa snapshot baru. Manual save di halaman Show juga berjalan berdampingan
dengan autosave.

Setelah patch:

- `AutosaveStatus` punya nilai `error`; `retry()` dan `markSaved()` diekspos,
  indikator menampilkan teks error + tombol retry yang bisa diakses keyboard.
- `flush()` menjadi **single-flight + coalescing**: maksimal satu `save()` aktif;
  permintaan saat request berjalan menandai satu putaran tambahan yang membaca
  snapshot terbaru (`last write wins`) tanpa abort controller.
- Kegagalan menghentikan loop dan menunggu retry eksplisit; edit berikutnya juga
  memicu percobaan baru.
- `suspend()` / `resume()` / `waitForIdle()` menahan autosave selama manual save:
  manual PUT menunggu request aktif selesai tanpa memicu save autosave tambahan.
- Toast kegagalan disatukan di `lib/autosaveFailureToast.ts` (humanisasi pesan +
  aksi retry), tidak lagi diduplikasi di Show dan Create.

Tidak ada perubahan route, response contract, enum, otorisasi, atau schema.

| Issue | Commit |
| ----- | ------ |
| AUDIT-P1-014 | belum di-commit (working tree dipakai bersama lane lain) |

Laporan lengkap: `docs/big-changes/patching-ticket/AUDIT-P1-014.md`.
Plan: `docs/big-changes/patching-ticket/AUDIT-P1-014-plan.md`.

## Akar masalah (bukti)

- `resources/js/hooks/useAutosaveSync.ts` (sebelum) — status hanya
  `idle/saving/saved/uploading`; `catch` mengembalikan status ke `idle` dan hanya
  memanggil `onError`. `flush()` selalu memulai `save()` baru; `saveSeq` hanya
  menjaga penulisan status.
- `resources/js/pages/Dashboard/Events/Forms/Show.vue` (sebelum) — `onSave()`
  memanggil `settingsForm.put()` tanpa berkoordinasi dengan autosave.
- Unit test awal mereproduksi 3 save paralel dan status yang tetap `idle` saat
  gagal; E2E mereproduksi overlap manual save vs autosave.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/hooks/useAutosaveSync.ts` | Status `error`; `retry()`, `markSaved()`, `waitForIdle()`, `suspend()`, `resume()`; loop `drain()` single-flight + coalescing; putaran yang diantre sebelum suspend ditahan untuk `resume()`; disabled setelah unblock kembali `idle`. |
| `resources/js/components/ui/autosave-status/AutosaveStatus.vue` | Cabang error + tombol retry (`aria-label`, `data-testid`), live region selalu ter-mount, tombol tetap ter-mount saat saving. |
| `resources/js/pages/Dashboard/Events/Forms/Show.vue` | `onSave()` async: `suspend()` → `waitForIdle()` → PUT state terbaru; `markSaved()` saat sukses; `resume()` di `onFinish`; guard `manualSaving` juga men-disable tombol dan processing workspace. |
| `resources/js/pages/Dashboard/Events/Create.vue` | Toast error memakai helper bersama; indikator menerima `@retry`. |
| `resources/js/lib/autosaveFailureToast.ts` (baru) | `notifyAutosaveFailure(message, retry)` — humanisasi pesan + aksi retry. |
| `resources/js/hooks/__tests__/useAutosaveSync.test.ts` (baru) | 17 unit test fake timer (debounce, single-flight, last-write-wins, error/retry, markSaved, suspend/resume, waitForIdle, blocked timeout, disabled-after-unblock, storage/snapshot failure). |
| `tests/e2e/autosave-retry.desktop.spec.ts` (baru) | 3 E2E desktop: retry setelah 500, anomali judul kosong → 422, manual save saat autosave berjalan tanpa overlap; assertion memakai kondisi terminal dan pasangan event per `id`. |

## Verifikasi

| Perintah | Hasil |
| -------- | ----- |
| `npm run test:unit -- resources/js/hooks/__tests__/useAutosaveSync.test.ts` | 17 passed |
| `npm run test:unit` | 30 file / 300 test passed |
| `npx vue-tsc --noEmit` | exit 0 |
| `npm run lint` | exit 0 (0 error, 0 warning) |
| `npx playwright test tests/e2e/autosave-retry.desktop.spec.ts --project=chromium-desktop --retries=0` | 4 passed (1 setup + 3 test) |
| Test overlap `--repeat-each=2` | 2 passed |

Review independen: **APPROVE WITH CHANGES**; seluruh temuan blocking ditutup
(lihat §Review independen pada laporan). Follow-up `AUDIT-P1-014-F1..F4` dicatat
di `docs/big-changes/patching.md`.
