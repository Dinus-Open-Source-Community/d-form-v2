# Sapto Changes — 07 November 2026 (DFORM-AUDIT-P2-010)

## Ringkasan

`metadata.rules.max_size` pada field `fileUpload` sekarang benar-benar menjadi
batas validasi. Batas efektif adalah `min(max_size field, batas global
events.forms.upload_max_size_kb)`, sehingga nilai field yang lebih besar dari
global (termasuk data lama `10240` KB) di-clamp dan tidak memblokir form lama.
Pesan validasi menyebut batas efektif (`2 KB`/`5 MB`) dan tidak lagi hardcode
`5 MB`. Default builder `file_upload` diselaraskan ke `5120` KB.

| Issue | Commit |
| --- | --- |
| AUDIT-P2-010 — `max_size` per field tidak dipakai | `security: enforce per-field max_size on form file uploads` |

## Perubahan

- `app/Rules/SafeUploadFile.php` — argumen opsional `?int $maxSizeKb` pada
  constructor dan `rules()`; `effectiveMaxSizeKb()` meng-clamp ke batas global;
  `formatLimit()` membentuk pesan `MB`/`KB`. Pemeriksaan nama, MIME, PDF aktif,
  dan decompression bomb tidak berubah.
- `app/Services/Form/RulesBuilder.php` — file branch membaca
  `metadata.rules.max_size` (int/numeric string) dan meneruskannya ke
  `SafeUploadFile`. Berlaku untuk single form, team invitation, bundle slot, dan
  OpRec karena semuanya memakai builder yang sama.
- `resources/js/components/modules/builder/fieldMapping.ts` — default
  `file_upload` `max_size` `10240` → `5120` KB.
- `tests/Feature/Forms/FormUploadMaxSizeTest.php` — 6 feature test: di bawah
  limit, di atas limit + pesan `2 KB`, tepat di limit, legacy clamp 10240,
  tanpa `max_size`, clamp ke global yang diturunkan, dan clamp di batas global
  default (6 MB ditolak `5 MB`).
- `tests/e2e/form-upload-max-size.desktop.spec.ts` — 6 skenario browser: under/
  at/over limit, legacy clamp, MIME salah, dan upload 0 byte; login worker-scoped
  agar tidak kena throttle `auth-login` 5/menit.

## Keputusan desain

- Clamp `min(field, global)` dipilih agar data lama tetap valid tanpa migration;
  menolak `max_size > global` saat simpan form akan memblokir edit form legacy.
- Satu implementasi di `SafeUploadFile` menghindari rule `max:` ganda dan
  mempertahankan urutan cek ukuran sebelum pembacaan konten.
- Hint form-fill tidak diubah karena membutuhkan config global baru di props
  Inertia; server tetap otoritatif (dicatat sebagai batasan di report tiket).
- Nilai `max_size` tidak valid fallback ke global, bukan fail-closed, karena
  builder sudah menolak nilai < 1 dan nilai aneh hanya berasal dari data legacy.

## Verifikasi

- `FormUploadMaxSizeTest`: 6 passed, 25 assertions.
- `RulesBuilderTest`: 1 passed; `FormRegistrationTest`: 87 passed, 627
  assertions; `SafeUploadFileTest`: 8 passed.
- `tests/Feature/Forms` + `RulesBuilderTest`: 171 passed, 1032 assertions.
- Suite penuh: 914 passed, 7 skipped; satu flaky `Stress\DigestSnapshotCommandTest`
  (`total_ms` int vs float) yang lulus saat diulang dan tidak menyentuh file ticket.
- E2E browser `chromium-desktop`, `--retries=0`: 7 passed (1 setup + 6 skenario),
  0 failed, 0 flaky.
- `npm run lint`, `tsc -p tsconfig.e2e.json --noEmit`, dan `pint --test` (4 file)
  lulus.

Report lengkap: `docs/big-changes/patching-ticket/AUDIT-P2-010.md`.

## Catatan

- Tidak ada perubahan schema, route, migration, atau queue.
- Temuan Medium pre-existing di luar scope: `TeamInvitationController::relaxFileRulesWhenPathExists`
  memakai closure bertipe `string` pada array yang berisi `SafeUploadFile`
  (berpotensi `TypeError` pada undangan tim dengan field file `is_append`).
  Dicatat di report tiket, tidak diubah.
