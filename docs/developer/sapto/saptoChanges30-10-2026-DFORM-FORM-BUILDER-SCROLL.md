# Sapto Changes — 30 Oktober 2026 (DFORM-FORM-BUILDER-SCROLL)

## Ringkasan

Shell editor form tidak lagi ikut memanjang dan terscroll bersama halaman. Area field pada kartu form menjadi scroll container utama, sementara toolbar, banner, judul, dan deskripsi tetap berada pada frame editor.

| Issue | Commit |
| ----- | ------ |
| Form builder scroll containment | `fix(forms): constrain form builder scroll to fields` |

## Perubahan

- Halaman Create/Edit form full-width memakai shell dengan tinggi viewport terkontrol dan `overflow-hidden`.
- Tab `Editor`, `Pengaturan Form`, dan `Jawaban` tetap dapat menggulir konten masing-masing tanpa mengaktifkan scroll parent.
- Section field pada kartu preview memakai flex sizing dan `overflow-y-auto`, sehingga daftar field menjadi area scroll yang terisolasi.
- Tinggi daftar field dibatasi dengan `min(36rem, 60dvh)` agar form pendek tidak meninggalkan ruang kosong besar, sementara form panjang tetap dapat digulir.
- Teks helper `Lebar pratinjau mengikuti...` dihapus sesuai kebutuhan UI.
- Regression E2E ditambahkan untuk memverifikasi shell tidak memiliki overflow vertikal dan area field tetap menjadi scroll container.

## Keputusan desain

Scroll containment dipusatkan pada container yang benar-benar memiliki daftar field. Ini menjaga banner dan metadata form tetap terbaca, menghindari nested page scroll, dan mempertahankan akses scroll untuk form panjang pada viewport kecil maupun besar.

## Verifikasi

- `npm run lint -- --no-warn-ignored`: PASS.
- `npm run typecheck`: PASS.
- `npm run test:e2e:types`: PASS.
- `git diff --check`: PASS.
- Browser E2E belum dijalankan karena `E2E_BASE_URL=http://localhost:8000` tidak aktif pada environment ini.
