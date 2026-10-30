# Sapto Changes — 30 Oktober 2026 (DFORM-FORM-BUILDER-SCROLL)

## Ringkasan

Shell editor form tidak lagi ikut memanjang dan terscroll bersama halaman. Parent kanvas menjadi scroll container utama, sementara tab atas, panel komponen, banner, judul, dan deskripsi tetap berada pada frame editor.

| Issue | Commit |
| ----- | ------ |
| Form builder scroll containment | `fix(forms): constrain form builder scroll to fields` |

## Perubahan

- Halaman Create/Edit form full-width memakai shell dengan tinggi viewport terkontrol dan `overflow-hidden`.
- Tab `Editor`, `Pengaturan Form`, dan `Jawaban` tetap dapat menggulir konten masing-masing tanpa mengaktifkan scroll parent.
- Parent kanvas memakai `overflow-y-auto`, sehingga daftar field bergerak bersama kartu form tanpa nested scroll pada section field.
- Panel komponen kiri tetap menjadi pane tersendiri; tab tingkat atas berada di luar area scroll editor.
- Teks helper `Lebar pratinjau mengikuti...` dihapus sesuai kebutuhan UI.
- Regression E2E ditambahkan untuk memverifikasi shell tidak memiliki overflow vertikal dan area field tetap menjadi scroll container.

## Keputusan desain

Scroll containment dipusatkan pada parent kanvas. Ini menghindari nested scroll di dalam kartu field, membuat perpindahan antar-field lebih natural, dan menjaga tab tingkat atas serta panel komponen tetap stabil saat form panjang digulir.

## Verifikasi

- `npm run lint -- --no-warn-ignored`: PASS.
- `npm run typecheck`: PASS.
- `npm run test:e2e:types`: PASS.
- `git diff --check`: PASS.
- Browser E2E belum dijalankan karena `E2E_BASE_URL=http://localhost:8000` tidak aktif pada environment ini.
