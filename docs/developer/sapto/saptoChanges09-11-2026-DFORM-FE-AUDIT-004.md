# Sapto Changes — 9 November 2026 (FE-AUDIT-004)

## Ringkasan

`resources/js/adapters/` dibuat sebagai satu-satunya seam HTTP dan browser.
Seluruh pemanggilan `axios`, `fetch`, `localStorage`/`sessionStorage`, dan akses
global `document`/`window` di `pages/`, `components/` (kecuali `components/ui/**`),
`layouts/`, `hooks/`, `utils/`, serta `lib/` dipindahkan ke adapter. Kontrak error
HTTP diseragamkan menjadi `HttpRequestError` (`kind`, `status`, `body`,
`validationErrors`, `retryAfterSeconds`).

| Issue | Commit |
| ----- | ------ |
| FE-AUDIT-004 | _(diisi setelah commit)_ |

Laporan lengkap: `docs/big-changes/patching-ticket/FE-AUDIT-004/patching-ticket.md`.
Plan: `docs/big-changes/patching-ticket/FE-AUDIT-004-plan.md`.

## Akar masalah (bukti)

- `axios` diimpor langsung oleh 8 file; `fetch` mentah dipakai untuk review
  submission, upload broadcast, cek email peserta, unduh lampiran, dan guard auth.
- `localStorage`/`sessionStorage` dipakai langsung oleh draft form, preferensi
  tampilan jawaban, dan identitas meja scan.
- 47 file memakai `document`/`window` langsung (141 akses).
- Tidak ada folder `adapters/`; kontrak response, header CSRF, retry, dan pesan
  error berbeda antar fitur.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/adapters/http/*` (baru) | `httpError`, `httpClient`, `auth`, `forms`, `recruitment`, `broadcast`, `scan`. |
| `resources/js/adapters/browser/*` (baru) | `environment`, `storage`, `navigation`, `documentState`, `domQuery`, `domEvents`, `mediaQuery`, `fileDownload`, `feedback`, `clipboard`. |
| `resources/js/types/http.ts`, `types/scan.ts`, `types/recruitment.ts` (baru) | Kontrak lintas file; `IApplicationDetail` dipindah dari `ApplicantDetailContent.vue`. |
| 46+ file `pages/`, `components/`, `layouts/`, `hooks/` | Hanya pemanggilan HTTP/DOM yang diganti adapter; copy, markup, dan behavior tidak berubah. |
| `resources/js/bootstrap.js` | Setup axios global dihapus; konfigurasi tinggal di `adapters/http/httpClient.ts`. |
| `resources/js/utils/theme.js` | `localStorage`/`document` diganti adapter. |
| `resources/js/lib/qrScanUi.ts` | `playScanBeep` dipindah ke `adapters/browser/feedback.ts`. |
| `resources/js/lib/recruitmentApplicantCapabilities.ts` | Tipe diimpor dari `types/recruitment`, bukan dari `.vue`. |
| `vitest.config.ts`, `tsconfig.vitest.json`, `package.json` | Harness unit adapter; FE-AUDIT-002 kemudian menambahkan coverage dan memperluas include. |
| `eslint.config.js` | Enforcement boundary: `no-restricted-imports` axios + `no-restricted-globals` DOM/storage/fetch, override adapters/ui/wayfinder. |
| `resources/js/adapters/**/__tests__/*` (baru) | 10 file, 70 test adapter. |
| `tests/e2e/adapter-boundaries.desktop.spec.ts` (baru) | Header/body adapter, 422 → field error, submit ganda, email kosong. |

Tidak ada perubahan route, payload backend, schema, atau copy UI.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Satu instance axios di adapter | Header JSON/XSRF/credentials konsisten; tidak bergantung `window.axios`. |
| `parse` wajib di `requestJson` | Response internal yang kontraknya diketahui tidak lolos sebagai `unknown`; parser `null` → `invalid_response`. |
| `requestJsonWithStatus` terpisah | OTP dan review butuh membaca 422/429/409 tanpa melempar. |
| Adapter browser per kapabilitas | Menghindari god-layer `document`/`window`; tiap modul punya satu alasan berubah dan mudah di-stub. |
| Tidak membuat adapter scanner/timers/session | Tidak ada seam nyata sekarang; scanner masuk `FE-AUDIT-008`, timer global standar, `probePageUrl` sudah di `httpClient`. |
| `components/ui/**` tidak diubah | Aturan folder melarang edit langsung; dicatat sebagai tiket baru. |

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `rg -l "from 'axios'" resources/js` | hanya `adapters/http/httpClient.ts` |
| `rg -l "fetch\\(" resources/js` | hanya `adapters/http/httpClient.ts` (probe guard auth) |
| `rg -l "localStorage\\|sessionStorage" resources/js` (non-adapter) | 0 |
| `rg -l "(?<!\\.)\\b(document\\|window)\\." resources/js` (non-adapter, non-ui) | 0 |
| `npx vitest run resources/js/adapters` | 12 file, 86 passed (+4 test `urlPolicy`) |
| `npx vitest run` (shared tree) | 30 file, 305 passed |
| `npx tsc -p tsconfig.vitest.json` | exit 0 |
| `npx vue-tsc --noEmit` | exit 0 |
| `npx eslint "resources/js/**/*.{ts,vue}"` | exit 0 |
| `npm run build` (container) | exit 0; entry 50.720 B raw / 15.636 B gzip; axios lazy chunk ±54,8/20,6 kB; `AxiosError` 0 di entry |
| E2E `adapter-boundaries` | 5 passed di `localhost:3000` dan `localhost:8020`; final bersama `auth-guard` 6 passed |
| E2E `create-p0-save` / `otp-verification` / `recruitment-applicant-list` / `recruitment-qr-status-tabs` | 4 / 11 / 9 / 6 passed |
| E2E `form-submission-boundary` | 17 passed + 1 lulus saat dijalankan terisolasi |

Kegagalan batch E2E lain disebabkan sesi admin bersama yang di-flush
(`SESSION_DRIVER=redis`) dan dua container e2e yang berbagi direktori session;
bukan regresi adapter. Detail dan tiket lanjutan ada di laporan.

## Review independen

Review arsitektur `oracle`: **APPROVE WITH CHANGES**. Dua temuan High diperbaiki
sebelum final: ACK autosave `{ok:true}` (tidak ada lagi sukses palsu) dan
`probePageUrl` kembali memakai `fetch` dengan `cache:no-store` di dalam adapter.
Medium yang diperbaiki: `requestJsonWithStatus` melempar `invalid_response` untuk
2xx rusak, parser submit scan bertipe, axios dipindah ke lazy chunk, `Retry-After`
satu sumber, test parser recruitment, enforcement ESLint, revoke object URL 1000
ms, `pushHistoryState` untuk tab auth, dan adapter clipboard. Review keamanan
`paladin`: **APPROVE** tanpa Critical/High/Medium; L1 redirect `//host`, L2
allowlist skema URL, L3 XSRF same-origin, L4 validasi URL upload, dan residual
tab/newline pada `isInternalPath` diperbaiki.

## Checklist

- [x] Behavior copy/markup/route tidak berubah.
- [x] Tidak ada `axios`/`fetch`/storage/global DOM di luar adapter.
- [x] Unit test adapter hijau; typecheck, lint, build hijau.
- [x] E2E flow utama yang memakai adapter hijau.
- [x] Temuan luar scope dicatat sebagai tiket baru di `patching.md`.
- [ ] Commit dan update status tiket ke In Review.
