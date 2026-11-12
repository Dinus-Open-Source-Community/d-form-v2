# Sapto Changes — 30 Oktober 2026 (DFORM-FORM-SECTIONS)

## Ringkasan

Editor form sekarang memakai bagian (section) yang dapat ditambah tanpa batas lima field. Setiap bagian memiliki daftar field independen, sementara halaman pengisian publik berpindah antarbagian dan memvalidasi field wajib sebelum peserta melanjutkan.

| Issue | Commit |
| ----- | ------ |
| Form builder sections | `feat(forms): add configurable form sections` |

## Perubahan

- Metadata form menyimpan `sections` dengan minimal satu bagian; field menyimpan `metadata.sectionId`.
- Field lama tanpa `sectionId` otomatis masuk ke `Bagian 1`, sehingga form existing tetap dapat dibuka dan disimpan.
- Tombol `Tambah bagian` ditempatkan di header area field, bukan di footer, agar mudah dijangkau pada form panjang.
- Navigasi `Sebelumnya`/`Berikutnya` hanya muncul saat form memiliki lebih dari satu bagian dan menampilkan indeks bagian aktif dari total bagian.
- Setiap bagian dapat berisi jumlah field berapa pun, termasuk bagian kosong; drag, tambah, hapus, duplikasi, dan urutan field bekerja pada bagian aktif.
- Halaman isi form menampilkan bagian aktif, hanya meminta field wajib pada bagian tersebut sebelum berpindah, lalu menampilkan tombol submit pada bagian terakhir.
- Validasi backend menerima struktur metadata section dan `sectionId` agar data tersimpan melalui endpoint form maupun batch field.

## Keputusan desain

Model section dipilih daripada mempertahankan paginasi lima field karena paginasi sebelumnya mencampur batas visual dengan struktur data. `sectionId` menjadi relasi ringan di metadata tanpa migrasi tabel, kompatibel dengan data lama, dan tetap memungkinkan setiap section memiliki jumlah field bebas.

## Verifikasi

- `npm run lint -- --no-warn-ignored`: PASS.
- `npm run typecheck`: PASS.
- `npm run test:e2e:types`: PASS.
- `podman exec -i -w /app d_form_app php artisan test tests/Feature/Forms/FormBuilderHttpTest.php tests/Feature/Forms/FieldOperationTest.php tests/Feature/Forms/FormControllerM3ComplianceTest.php`: PASS (25 tests, 51 assertions).
- `podman exec -i -w /app d_form_app vendor/bin/pint --test app/Http/Controllers/Dashboard/Events/Forms/FormFillController.php app/Support/FormFieldsRequestValidation.php`: PASS.
- `timeout 120 podman exec -i -w /app d_form_app npm run build`: PASS; entry `app` 26.80 kB gzip, chunk editor terbesar `FormBuilderWorkspace` 20.51 kB gzip, chunk page terbesar `index-BRVR_4u8` 145.58 kB gzip.
- Browser E2E belum dijalankan karena `E2E_BASE_URL=http://localhost:8000` tidak aktif pada environment ini.
