# Sapto Changes — 9 November 2026 (FE-AUDIT-006)

## Ringkasan

Empat jalur kegagalan frontend yang sebelumnya senyap sekarang memberi feedback yang
bisa ditindaklanjuti: polling feed scan (banner non-blocking + backoff eksponensial),
`pause`/`resume` kamera scanner, autosave draft OPRec yang ditolak browser, dan dynamic
import toast error global. Satu klaim audit (`ApplicantDetailContent.vue` "pesan generik
untuk network failure") diverifikasi ulang dan dinyatakan **stale** karena jalur itu
memakai `onError` Inertia yang memang tidak membawa status transport.

| Issue | Commit |
| ----- | ------ |
| FE-AUDIT-006 | `9a66e8e` — `refactor: move http and browser seams behind adapters` |

Laporan lengkap: `docs/big-changes/patching-ticket/FE-AUDIT-006/patching-ticket.md`.

## Akar masalah (bukti)

- `resources/js/hooks/useGlobalQrScanPage.ts` — `catch` kosong pada `pollFeed()`; `setInterval` tetap 2 dtk, tanpa backoff, tanpa state UI.
- `resources/js/hooks/useGlobalQrScanPage.ts` — `catch` kosong pada `resume()`/`pause()`; `isCameraReady` tidak direset saat kamera gagal lanjut.
- `resources/js/pages/Dashboard/Recruitment/Periods/Show.vue` — `catch { selectedApplication.value = null }`.
- `resources/js/pages/OpenRecruitment/Apply.vue` — `onError: () => {}`; `writeStorage()` mengembalikan `boolean` tetapi hasilnya dibuang, `save` no-op, sehingga UI tetap mengklaim "Draft tersimpan otomatis".
- `resources/js/hooks/useGlobalHttpStatusToast.ts` — `void showStatusToast(...)` tanpa rejection handler pada dynamic import.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/lib/qrScanUi.ts` | `FEED_POLL_MS` diekspor; fungsi murni `nextFeedDelayMs()` (2s/4s/8s/16s, cap 30s; negatif/NaN → 2000). |
| `resources/js/lib/requestErrorText.ts` (baru) | `describeRequestError({kind,status,retryAfterSeconds})` → pesan publik per `THttpErrorKind`; `aborted` → `null`; `rate_limited` hormati Retry-After; `invalid_response` tidak memakai `status ?? 500`. |
| `resources/js/hooks/useGlobalQrScanPage.ts` | `feedState`/`feedFailureCount`; polling `setTimeout` berantai + backoff + `clearTimeout` sebelum jadwal baru; `aborted` tidak dihitung gagal. `teardownScanner()` dipakai saat `resume`/`pause` gagal, `stop`, `start` (instance lama), dan error `start`; guard `processScan` untuk sumber kamera; `submitScanPayload` memakai `describeRequestError` (bukan "Kesalahan jaringan" untuk semua error). |
| `resources/js/hooks/useAutosaveSync.ts` | Snapshot dihitung di luar `try`; kegagalan storage → `onError` sekali per transisi sukses→gagal; `status='idle'`. |
| `resources/js/hooks/useGlobalHttpStatusToast.ts` | `.catch()` pada dynamic import + fallback `toast.error()` statis; kegagalan modul dicatat `console.error` dengan konteks statis. |
| `resources/js/components/modules/dashboard/QrScanSidebar.vue` | Prop `feedStale`/`feedFailureCount`; live region `role="status" aria-live="polite"` berisi teks statis, angka kegagalan dipindah ke luar live region; chip ikon + `border-warning/50 bg-warning/10`. |
| `resources/js/pages/Dashboard/Scan/Global.vue` | Wiring `:feed-stale` dan `:feed-failure-count`. |
| `resources/js/pages/OpenRecruitment/Apply.vue` | `probeDraftStorage()` saat mount menggerbang klaim "tersimpan otomatis"; adapter storage melempar saat `writeStorage()===false`; satu banner warning persisten; `AutosaveStatus` disembunyikan saat error; draft JSON rusak → `removeStorage` dicek hasilnya + notice jujur. |
| `resources/js/pages/Dashboard/Recruitment/Periods/Show.vue` | `detailError`/`detailFailedId`/`panelApplicantId`; `loadApplicantDetail()`; `detailErrorMessage()` memakai `describeRequestError` (kecuali `not_found` kontekstual); reset error saat ganti applicant; `retryApplicantDetail()`. |
| `resources/js/components/modules/dashboard/recruitment/ApplicantDetailPanel.vue` | Prop `errorMessage`, emit `retry`, sheet tetap terbuka saat error, judul menyatakan kegagalan, state error inline + tombol "Coba lagi". |
| `resources/js/lib/__tests__/qrScanFeedBackoff.test.ts` (baru) | Test `nextFeedDelayMs` (file terpisah agar tidak bentrok dengan lane `FE-AUDIT-005`). |
| `resources/js/lib/__tests__/error-message.test.ts` | Test `describeRequestError`. |
| `resources/js/hooks/__tests__/useAutosaveSync.test.ts` | Test storage gagal → `onError` sekali. |
| `tests/e2e/fe-audit-006-error-feedback.desktop.spec.ts` | 4 test: feed stale + recovery (termasuk live region statis), draft ditolak browser, detail 500 + retry, anomali scan 422 + `since` rusak. |
| `tests/e2e/support/authGuard.ts` | `E2E_ADMIN_STATE` opsional agar run paralel tidak saling menimpa storageState; default tidak berubah. |

Tidak ada perubahan backend, route, schema, dependency, atau lockfile.

## Revisi pasca-review

| Temuan | Perubahan |
| --- | --- |
| Kamera bocor setelah `resume()`/`pause()` gagal | Teardown nyata (`stop` + `clear`, `scanner=null` sebelum await), batas waktu 2000 ms, guard `processScan` sumber kamera, reset `pauseFailureNotified`. |
| Unmount saat `start()` pending | Instance lokal + cek `scanner.value !== instance`; instance yatim ditutup tanpa men-set ready/toast. |
| `start()` reject setelah getUserMedia | Mitigasi `releaseStrayCameraStream()` (stop track `video.srcObject`). |
| 429 scan membuang Retry-After | Pesan memakai `describeRequestError` + `retryAfterSeconds`. |
| Race detail applicant / panel terbuka sendiri | `detailRequestSeq` + invalidasi pada cache-hit/close. |
| Live region dibuat bersama konten | Satu live region `sr-only` persisten di luar `v-if`; banner visual tanpa `aria-live`. |
| 422 tampil bahasa Inggris | `parseApiErrorMessage` errors-first untuk 422; non-422 tidak berubah. |

Ditunda ke tiket follow-up: `FE-AUDIT-006-F4` (lifecycle kamera level library + unit test hook),
`-F5` (dedup/internal API hook), `-F6` (gap E2E).

## Verifikasi

| Perintah | Hasil |
| -------- | ----- |
| `npx vitest run qrScanFeedBackoff + error-message + useAutosaveSync` | exit 0 — 3 file / 50 test |
| `npm run test:unit` | exit 0 — 30 file / 300 test |
| `npx tsc -p tsconfig.vitest.json` | exit 0 |
| `npx tsc -p tsconfig.e2e.json` | exit 2 — hanya error di `tests/e2e/event-search.spec.ts` milik lane lain (mtime 19:51); spec FE-AUDIT-006 tidak menghasilkan error |
| `npm run typecheck` | exit 0 |
| `npm run lint` | exit 0 |
| `npm run build` (container `d_form_app`, setelah edit final) | exit 0 — entry 50,62 kB raw / 15,71 kB gzip; chunk terbesar 145,58 kB gzip |
| `npx playwright test tests/e2e/fe-audit-006-error-feedback.desktop.spec.ts` (`:8020`, `d_form_e2e`, `--retries=0`) | 5 passed / 0 failed |
| Regresi `qr-scan.desktop.spec.ts` + `recruitment-applicant-list.desktop.spec.ts` (setelah perubahan hook/panel) | 12 passed / 0 failed |

Anomali yang dibuktikan: `POST /admin/scan` body `{}` → 422 publik-safe tanpa
`SQLSTATE`/stack trace; `GET /admin/scan/feed?since=bukan-timestamp` → 200
`{rows:[],cursor:null}`.

## Risiko dan follow-up

- Feed yang gagal terus-menerus tetap polling dengan cap 30 dtk; observability hanya
  lewat state UI, belum ada metrik server-side. Ekstraksi seam polling dicatat sebagai
  `FE-AUDIT-006-F2`.
- Temuan di luar scope dicatat di `docs/big-changes/patching.md`: `FE-AUDIT-006-F1`
  (salin nomor registrasi OPRec gagal senyap) dan `FE-AUDIT-006-F3` (restore draft
  membuang nilai non-string).
- Working tree dipakai lane tiket lain (FE-AUDIT-002/003/004/005); angka verifikasi
  diambil dari working tree gabungan dan klaim dibatasi pada file di tabel Perubahan.
