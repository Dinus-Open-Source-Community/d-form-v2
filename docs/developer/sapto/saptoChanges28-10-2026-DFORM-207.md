# Sapto Changes — 28 Oktober 2026 (DFORM-207)

Laporan audit ESLint dan LSP diperbarui dari bukti TASK-206, dengan pengukuran lint terbaru dan cakupan LSP yang dinyatakan per path serta batasnya.

| Kolom | Isi |
| --- | --- |
| Issue | TASK-207 |
| Commit | `f84c796` (implementasi laporan awal; revisi ulasan pada tiket ini) |
| Keluaran | [Audit ESLint dan LSP 28-10-2026](../../big-changes/audit-eslint-lsp-28-10-2026.md) |

## Angka utama

- ESLint 10.13.0 / Node v24.22.1: 611 file (258 TypeScript, 353 Vue), 0 error, 0 warning, 0 fatal.
- LSP menghasilkan status diagnostik untuk 53/611 file ESLint (8,7%): 47 bersih dan 6 file memiliki 11 pesan error (snapshot 28-10-2026 06:46–06:48 UTC); 78 file dicoba (12,8% attempted); 25 Vue tetap `still starting` setelah retry. 533 file lainnya tidak dipanggil. Pada denominator 469 file TS/Vue tertrack di worktree, cakupan source-only adalah 11,3%; 142 file sisanya adalah output Wayfinder generated pada container.
- Cakupan tambahan 25 PHP dan 16 routes/config juga tidak tercakup karena language server tetap `still starting`.
- Diagnostic event Inertia adalah false positive dari LSP host yang memakai dependency stale 2.3.18; lockfile/container memakai 3.9.1. Diagnostic SFC/generated juga dibatasi perbedaan environment.
- Draf TASK-206 menyebut 469 file ESLint; pengukuran run ini menunjukkan 611 karena container turut memuat 142 file generated. Selisih ini adalah perbedaan cakupan, bukan pertambahan file source.

Audit ini menghasilkan dokumentasi saja; source, konfigurasi, dan test tidak diubah.
