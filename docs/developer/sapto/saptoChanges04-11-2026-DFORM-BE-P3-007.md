# Sapto Changes — 4 November 2026 (DFORM-BE-P3-007)

## Ringkasan

Menambahkan structured error logging yang public-safe untuk kegagalan callback
OAuth Google dan GitHub tanpa mengubah pesan user atau flow redirect.

| Issue                                             | Commit          |
| ------------------------------------------------- | --------------- |
| BE-P3-007 — OAuth exception ditelan tanpa logging | `150d5b4` |

## Perubahan

- Menambahkan event log `oauth_callback_failed` pada level `error`.
- Mencatat provider, route, UUID request ID, dan exception class saja.
- Menolak request ID header yang bukan UUID dan membuat UUID baru.
- Menambahkan regression feature test untuk exception Google/GitHub dan
  redaction metadata.
- Membuat report ticket di
  `docs/big-changes/patching-ticket/BE-P3-007.md`.

## Keputusan dan alternatif

- Tidak memakai `report($exception)` agar exception message/provider payload
  tidak ikut masuk ke default logging stack.
- Tidak menambah global correlation middleware karena berada di ticket terpisah.
- Tidak memperluas catch menjadi `\Throwable` agar programming error tidak
  tertelan.
- Tidak mengubah route, schema, dependency, frontend production code, atau
  response contract.

## Verifikasi

- OAuth feature test: **18 passed, 99 assertions**.
- Full backend suite: **894 passed, 7 skipped, 1 unrelated failure** pada
  `DeleteDeclinedInvitationMemberSubmissionJob::$tries`.
- OAuth E2E desktop: **7 passed**.
- OAuth E2E mobile: **7 passed**.
- Targeted Pint, ESLint, frontend typecheck, E2E typecheck, dan `git diff
--check`: passed.
- Direct browser anomaly test: callback tanpa state dan state/code palsu tetap
  berakhir di `/auth/login` tanpa sesi.

## Checklist

- [x] Google OAuth exception tercatat.
- [x] GitHub OAuth exception tercatat.
- [x] Log tidak membawa token, secret, raw exception message, atau PII.
- [x] Response user tetap netral.
- [x] Feature regression test lulus.
- [x] Browser anomaly desktop/mobile lulus.
- [x] Perubahan existing `boost.json` tidak disentuh.
