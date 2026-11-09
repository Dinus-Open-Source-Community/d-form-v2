# Sapto Changes — 9 November 2026 (AUDIT-P1-015)

## Ringkasan

E2E flow bisnis yang belum tertutup kini punya happy + failure path:
autosave/builder, QR scan manual, portal tracking OpRec, dan broadcast gate
default `FEATURE_BROADCAST=false`. Flow form submission peserta dan apply
memakai spec existing; keduanya dijalankan ulang. Dukungan test diperbaiki
untuk payload Inertia 3 dan storageState terpisah per run, dan CI mendapat
`e2e-types` (blocking) + `e2e-runtime` (report-only sampai run pertama hijau;
SQLite, seed referensi, subset fokus). Tidak ada perubahan kode produksi.

| Issue | Commit |
| --- | --- |
| AUDIT-P1-015 | _(diisi setelah commit)_ |

Laporan lengkap: `docs/big-changes/patching-ticket/AUDIT-P1-015/patching-ticket.md`.

## Perubahan

| File | Perubahan |
| --- | --- |
| `tests/e2e/builder-autosave.desktop.spec.ts` (baru) | 4 test autosave/builder: header, tambah field, failure 500, debounce. |
| `tests/e2e/qr-scan.desktop.spec.ts` (baru) | 3 test scan manual: happy, duplicate 409, invalid 422. |
| `tests/e2e/recruitment-tracking-portal.desktop.spec.ts` (baru) | 3 test tracking: login valid, token salah, tanpa sesi. |
| `tests/e2e/broadcast-gated.spec.ts` (baru) | 3 test gate-off di desktop + mobile. |
| `tests/e2e/support/network.ts` | `readInertiaPage` untuk payload `script[data-page]` Inertia 3. |
| `tests/e2e/support/authGuard.ts`, `playwright.config.ts` | `E2E_ADMIN_STATE` untuk isolasi storageState. |
| `tests/e2e/email-job-idempotency.desktop.spec.ts` | Stabilkan pilihan combobox ganda dan scope pesan error. |
| `tests/e2e/create-p0-save.desktop.spec.ts` | Timeout tunggu POST store 30 dtk; flaky saat digabung (F6), di luar subset CI. |
| `.github/workflows/quality.yml` | Job `e2e-types` + `e2e-runtime` (blocking). |

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `npm run test:e2e:types` | exit 0 |
| Spec baru + apply (isolated desktop) | 15 passed |
| CI-sim desktop subset | 15 passed |
| CI-sim mobile broadcast | 3 passed |
| Flow 1 subset | 2 passed |
| `create-p0-save` (di luar subset CI) | 3 passed sendiri; flaky saat digabung (`AUDIT-P1-015-F6`) |
| `npm run build` (container) | exit 0; entry 49.970 B / 15.327 B gzip |
| Cleanup DB | seluruh baris marker/flow = 0 |

## Dampak & risiko

- Test-only; tidak ada perubahan behavior, schema, route, atau dependency.
- CI runtime job belum dieksekusi di runner GitHub pada sesi ini; ekuivalen
  lokal (server + DB + seed + env yang sama) hijau. Status runner:
  `Needs verification`.
- Flake existing `form-submission-boundary` team happy path dicatat sebagai
  `AUDIT-P1-015-F1`; assertion usang, gap `e2e:fixture`, duplikasi helper,
  cakupan CI super-admin/gate-off, dan flake `create-p0-save` dicatat sebagai
  `AUDIT-P1-015-F2`–`F6` di `docs/big-changes/patching.md`.
- Rollback: revert file test/CI; tidak ada data atau migration.
