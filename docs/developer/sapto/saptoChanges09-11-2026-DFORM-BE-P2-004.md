# Sapto Changes — 9 November 2026 (BE-P2-004)

## Ringkasan

Cascade soft-delete/restore event ke form dan field di `EventObserver` berubah dari
loop per model (O(form + field) query) menjadi dua bulk `UPDATE` di dalam
`DB::transaction($callback, 3)`. Query operasi turun dari **123 → 4** pada event
10 form × 10 field dan dari **553 → 4** pada 500 field; semantik cascade tidak berubah.

| Issue | Commit |
| ----- | ------ |
| BE-P2-004 | _(diisi setelah commit)_ |

Laporan lengkap: `docs/big-changes/patching-ticket/BE-P2-004.md`.
Plan: `docs/big-changes/patching-ticket/BE-P2-004-plan.md`.

## Akar masalah (bukti)

- `app/Observers/EventObserver.php::deleting()`/`::restored()` mengambil form, lalu
  field per form, lalu delete/restore per field dan per form.
- Diukur di MySQL 9 dev: event 10 form × 10 field → **123 query** untuk delete dan
  **123** untuk restore. Pada suite SQLite, event 25 form × 20 field → **553** query.
- Analisis lock: urutan field-dulu di dalam transaksi mengambil S-lock pada `forms`
  lalu upgrade S→X dan membentuk ABBA dengan `FormController::update`
  (forms→fields). Direproduksi: MySQL 9 4/4 deadlock, MariaDB 10.11 6/6 deadlock.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `app/Observers/EventObserver.php` | `deleting()`: `UPDATE forms` lalu `UPDATE form_fields ... WHERE form_id IN (subquery forms, withTrashed)`, dalam `DB::transaction($cb, 3)`. `restored()`: urutan sama, `onlyTrashed()`, `deleted_at = NULL`. Docblock: invariant listener, alasan urutan lock, retry 1213/1205, batas retry di transaksi luar. Cache/agregat kategori/created/updated/deleted tidak berubah. |
| `tests/Feature/Events/EventObserverCascadeQueryTest.php` (baru) | 5 test: query count konstan 1×1 vs 25×20 (500 field) dan ≤ 8; seluruh child soft-deleted lalu restored; blast radius event lain; event tanpa form; invariant tidak ada model listener; quirk child yang diarsipkan mandiri (referensi `BE-P2-017`). |
| `tests/e2e/event-cascade-archive.desktop.spec.ts` (baru) | E2E browser archive/restore lewat UI admin dengan 3 form × 4 field, verifikasi DB, dan anomali: restore event aktif, id tidak ada/malformed, guest, arsip ganda, event tanpa form. |

Tidak ada perubahan route, response contract, schema, enum, otorisasi, atau tampilan.
Tidak ada perubahan `AGENTS.md`/`SKILL.md`.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Bulk `UPDATE`, bukan eager load + `each()` | Eager load hanya menghapus query baca; tulis per baris tetap O(N+M). Bulk membuat query konstan. |
| Subquery `whereIn`, bukan `pluck()` id | Satu statement, tanpa materialisasi UUID. `withTrashed()` membuat subquery tetap cocok setelah `forms` di-update lebih dulu. |
| forms→fields + retry 3 | Menghapus deadlock S→X dan ABBA dengan `FormController::update`; retry menangani sisa kontensi. |
| Semantik lama dipertahankan | Delete me-restamp semua child termasuk yang sudah trashed; restore mengembalikan semua child trashed. Quirk arsip mandiri dipisah ke `BE-P2-017`. |

## Dampak & risiko

- Query cascade konstan 4 (termasuk baris event + agregat kategori); lock window jauh
  lebih pendek.
- Retry deadlock/lock-wait menambah latensi worst case pada kontensi ekstrem
  (didokumentasikan; tidak ada pemanggil yang membungkus operasi ini dalam transaksi
  luar).
- Quirk lama dipertahankan sengaja; perubahan perilaku menunggu `BE-P2-017`.
- Tidak ada migration atau perubahan data.

## Verifikasi

Lihat `docs/big-changes/patching-ticket/BE-P2-004.md` §Verifikasi untuk perintah, exit
status, angka query, reproduksi deadlock MySQL/MariaDB, dan hasil E2E browser.
