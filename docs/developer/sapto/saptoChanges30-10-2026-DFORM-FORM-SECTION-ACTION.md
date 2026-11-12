# Sapto Changes — 30 Oktober 2026 (DFORM-FORM-SECTION-ACTION)

## Ringkasan

Aksi penambahan section dipindahkan dari header ke batas akhir section aktif. User dapat menambah section dari posisi scroll terakhir tanpa sticky header atau floating action yang mengganggu tampilan field.

| Issue | Commit |
| ----- | ------ |
| Section boundary action | `fix(forms): place section action at content boundary` |

## Perubahan

- Menghapus tombol `Tambah bagian` dari header section.
- Menambahkan tombol `Tambah bagian setelah ini` setelah field terakhir atau empty state.
- Menggunakan divider dashed ringan untuk menandai batas section tanpa menambah card baru.
- Menjaga tombol full-width di mobile dan inline di layar yang lebih lebar.
- Section baru otomatis menjadi section aktif dan canvas kembali ke posisi awal section tersebut.
- Navigasi section tetap berada setelah CTA dan tetap tersembunyi saat hanya ada satu section.

## Keputusan desain

Section boundary dipilih karena aksi ini secara semantik terjadi setelah field terakhir. Pola ini menjaga tombol tetap berada dalam alur normal dokumen, tidak menutup field saat scroll, dan tidak bersaing dengan tombol tambah field pada mobile.

## Verifikasi

- `npm run lint -- --no-warn-ignored`: PASS.
- `npm run typecheck`: PASS.
- `npm run test:e2e:types`: PASS.
- `git diff --check`: PASS.
- `timeout 120 podman exec -i -w /app d_form_app npm run build`: PASS; entry `app` 26.81 kB gzip dan chunk editor `FormBuilderWorkspace` 20.66 kB gzip.
- Browser E2E belum dijalankan karena `E2E_BASE_URL=http://localhost:8000` tidak aktif pada environment ini.
