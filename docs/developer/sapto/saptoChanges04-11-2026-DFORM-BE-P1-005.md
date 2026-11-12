# Sapto Changes — 4 November 2026 (DFORM-BE-P1-005)

## Ringkasan

Memperbaiki kontrak exception pada penyimpanan field form agar validation error tetap `422`, sedangkan kegagalan internal menjadi `500` dengan logging public-safe dan request ID.

| Issue | Commit |
| --- | --- |
| BE-P1-005 — exception internal tidak boleh menjadi validation `422` | `9ba584e` |

## Perubahan

- Memisahkan `ValidationException`/HTTP exception eksplisit dari unexpected `Throwable` pada `FieldOperationController`.
- Menambahkan structured log event `form_fields_save_failed` dengan request ID dan context domain aman.
- Menambahkan response JSON `500` dan response Inertia `500` dengan public-safe message.
- Menambahkan feature regression test untuk validation, database failure, rollback, logging, request ID, JSON, dan Inertia.
- Menambahkan browser E2E anomaly test desktop/mobile untuk payload invalid dan duplicate database ID.
- Membuat report tiket di `docs/big-changes/BE-P1-005/patching-ticket.md`; folder baru dibuat khusus untuk ownership report tiket ini.

## Keputusan dan alternatif

- Global correlation middleware tidak dimasukkan karena merupakan scope `AUDIT-P2-005`.
- Raw exception message tidak dikirim ke client.
- Tidak ada perubahan route, schema, frontend production code, dependency, atau migration.

## Verifikasi

- Full backend: `883 passed, 7 skipped, 5.667 assertions`.
- Feature target: `7 passed, 27 assertions`.
- Browser E2E BE-P1-005: `5 passed` termasuk setup, desktop, dan mobile.
- Targeted Pint: passed.
- `git diff --check`: passed.
- Prettier E2E: passed.
- E2E typecheck dan frontend typecheck: passed.

## Checklist

- [x] Validation payload tetap `422`.
- [x] Unexpected database failure menjadi JSON `500`.
- [x] Unexpected database failure menjadi Inertia `500`.
- [x] Public-safe message tidak memuat raw exception.
- [x] Request ID tercatat dan dikembalikan pada error response.
- [x] Browser anomaly test desktop/mobile lulus.
- [x] `boost.json` existing user change tidak disentuh.
