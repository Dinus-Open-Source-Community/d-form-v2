# Sapto Changes — 30 Oktober 2026 (DFORM-FORM-SETTINGS-TAB)

## Ringkasan

Aturan global form pada halaman edit dipindahkan dari accordion palette ke tab tingkat atas `Pengaturan Form`, berdampingan dengan `Editor` dan `Jawaban`.

| Issue | Commit |
| ----- | ------ |
| Form global settings tab | `feat(forms): pindahkan pengaturan global ke tab form` |

## Perubahan

- Tab `Pengaturan Form` mendukung deep-link `?tab=settings` dan tetap tersinkron dengan URL.
- Ikon tab memakai `Settings` agar maknanya langsung terbaca sebagai pengaturan form dan konsisten dengan affordance pengaturan yang umum.
- Konten yang dipindahkan mencakup tanggal tutup, visibilitas, tujuan form, form prasyarat, mode registrasi, dan ukuran tim.
- Halaman edit tidak lagi menampilkan duplikasi pengaturan di palette desktop maupun tab mobile internal.
- Halaman Create tetap memakai pengaturan inline agar alur pembuatan form dan regression P0 yang sudah ada tidak berubah.
- Komponen `FormSettingsPanel` tetap menjadi satu sumber UI dan model binding untuk menghindari duplikasi.

## Verifikasi

- `npm run lint -- --no-warn-ignored`: PASS.
- `npm run typecheck`: PASS.
- `npm run test:e2e:types`: PASS.
- E2E browser belum dijalankan karena `E2E_BASE_URL=http://localhost:8000` tidak aktif pada environment ini.

## Keputusan desain

Tab tingkat atas dipilih karena aturan tersebut berlaku untuk seluruh form, bukan untuk satu field atau palette. Placement dibuat configurable: `tab` untuk halaman edit dan `inline` untuk halaman Create agar perubahan tidak menghilangkan jalur pengaturan saat form baru dibuat.
