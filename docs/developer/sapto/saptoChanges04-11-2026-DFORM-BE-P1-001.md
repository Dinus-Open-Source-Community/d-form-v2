# Sapto Changes — 04 November 2026 (BE-P1-001)

## Ringkasan

Memperbarui dependency transitif `symfony/mime` dari `v7.4.19` ke `v7.4.22`
untuk menutup `CVE-2026-45070` email header injection. Tidak ada production
code, route, schema, atau flow bisnis yang diubah.

| Issue     | Commit                                        |
| --------- | --------------------------------------------- |
| BE-P1-001 | Current commit; see `git log` |

## Perubahan

- `composer.lock`: `symfony/mime` menjadi `v7.4.22`.
- `composer.lock`: dua dependency transitif ikut diperbarui oleh Composer.
- `tests/Unit/Mail/SymfonyMimeHeaderSecurityTest.php`: regression test untuk
  parameter name MIME non-token dan valid.
- `docs/big-changes/patching-ticket/BE-P1-001.md`: report lengkap dan evidence.

## Verifikasi

- `composer audit --no-dev --format=json`: lulus tanpa advisory.
- Full backend test: 850 passed, 7 skipped, 5.396 assertions.
- Regression mail/MIME: lulus.
- `composer validate`, Pint test baru, `git diff --check`, typecheck, dan E2E
  typecheck: lulus.
- Playwright desktop/mobile: belum dapat dimulai karena credential E2E tidak
  tersedia; browser manual dan anomaly validation telah dijalankan.

Detail command dan batasan ada di report
[`BE-P1-001`](../../big-changes/patching-ticket/BE-P1-001.md).
