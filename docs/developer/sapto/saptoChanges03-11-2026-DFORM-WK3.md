# Sapto Changes — 3 November 2026 (DFORM-WK3)

## Ringkasan

Entri beads/Dolt di `.gitignore` dipertahankan (keputusan user). Entri ditambahkan oleh `bd init` dan sebelumnya belum di-commit; tanpa entri ini artefak tooling lokal bisa ikut ter-stage, termasuk berkas kredensial.

| Issue       | Commit                                           |
| ----------- | ------------------------------------------------ |
| `dform-wk3` | `chore(gitignore): ignore beads local artifacts` |

## Isi entri yang dipertahankan

```gitignore
# Beads / Dolt files (added by bd init)
.dolt/
*.db
.beads-credential-key
.beads/proxieddb/
*.gate.lock*
```

## Alasan

- `.beads-credential-key` adalah kredensial dan **tidak** tercakup `.git/info/exclude` (yang hanya berisi `.beads/`), sehingga satu `git add -A` bisa membocorkannya.
- `.dolt/` dan `*.db` adalah database Dolt/SQLite lokal beads; bentuknya biner dan berubah setiap operasi.
- `*.gate.lock*` mencegah file lock (mis. `.beads.gate.lock`) masuk commit.

## Alternatif yang ditolak

- **Revert seluruh entri**: repo bebas dari konfigurasi tooling, tetapi proteksi kredensial bergantung pada exclude lokal per mesin.
- **Versi sempit** (hanya kredensial + `.beads/proxieddb/`): kredensial tetap aman, tetapi file lock dan database beads muncul sebagai untracked di mesin lain.

## Verifikasi

- `git diff .gitignore` sebelum commit hanya berisi blok beads di atas (tidak ada perubahan lain).
- `git diff --cached --name-only` setelah staging: hanya `.gitignore`, changelog ini, dan `docs/developer/sapto/README.md`. `boost.json` dan perubahan sesi lain tidak ikut.
- `git check-ignore -v .beads .beads/config.yaml .beads.gate.lock .beads-credential-key` menunjukkan entri yang berlaku.
- `npx prettier --check` pada file dokumentasi: PASS.

## Catatan

`.beads/` sudah ter-exclude lewat `.git/info/exclude` di mesin ini; entri `.gitignore` memastikan mesin lain yang menjalankan `bd` mendapat proteksi yang sama tanpa bergantung pada state lokal.
