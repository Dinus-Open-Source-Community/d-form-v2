# Referral: posisi blok di halaman isi + validasi live

Issue: DFORM-TBD · Commit: TBD · Tanggal: 26-11-2026

## Ringkasan (TL;DR)

Blok kode referral tidak lagi selalu di atas form. Posisinya disimpan di
`forms.metadata.referral_position` (`start`, `end`, atau id field = setelah field itu),
default `end`, dan bisa dipindah dari kanvas editor lewat blok semu `Kode referral`
(grip drag + tombol naik/turun). Halaman isi merender blok itu di posisi tersebut, dan
kode divalidasi langsung dengan debounce 500 ms: hijau saat valid, merah saat tidak,
netral saat kosong. Pengecekan memakai endpoint read-only baru sehingga tidak membuat
redemption dan tidak menambah `usage_count`.

## Perubahan

### Backend

- `Form::referralPosition()` membaca `metadata.referral_position` dengan fallback `end`.
- `FormPurposeRules::apply()` menormalkan nilai ke `start`/`end`/id field
  (`/^[A-Za-z0-9._-]{1,64}$/`), nilai lain menjadi `end`.
- `FormFieldsRequestValidation::formMetadataRules()` menambah rule
  `metadata.referral_position`. Tanpa rule ini `validated()` membuang key tersebut dan
  posisi selalu kembali ke `end` (bug yang ditemukan saat verifikasi browser).
- Endpoint baru `POST /joined/{event}/forms/{form}/referral-code/check`
  (`dashboard.forms.referral-check`, throttle `form-referral-check` 60/menit per user/IP)
  mengembalikan `{valid, message}`. Logika ada di
  `ReferralRedemptionService::check()`: read-only, tanpa lock, tanpa redemption.
  Pesan: tidak ditemukan, tidak aktif, belum aktif, kedaluwarsa, kuota habis.
- `FormFillController` dan `PublicFormFillPayload` mengirim `referralPosition` dan
  `referralCheckUrl` sehingga halaman member dan guest memakai kontrak yang sama.

### Frontend

- `lib/referralPlacement.ts` menjadi satu sumber perhitungan posisi (indeks sisip per
  bagian dan konversi indeks kembali ke anchor) untuk builder dan halaman isi.
- Builder: blok semu `Kode referral` tampil hanya saat referral aktif, bisa digeser
  (desktop) atau dipindah dengan tombol (mobile), dan tersimpan lewat kanal settings
  autosave. Blok tidak bisa dipilih, diduplikasi, atau dihapus. Kartunya mengikuti pola
  kartu field: chip ikon + label tipe, judul, deskripsi, rail kiri berisi grip dan tombol
  naik/turun bulat (desktop), serta bar aksi naik/turun di bawah kartu (mobile). Jarak
  antar-kartu sama dengan field (36 px desktop, 66 px mobile, diukur di browser). Satu
  komponen `FormBuilderReferralBlock.vue` dipakai untuk posisi awal/tengah dan akhir.
- Halaman isi: blok dirender di posisi anchor, bukan lagi selalu di atas. Mode bundle
  tidak memotong grup peserta (indeks digeser ke akhir grup).
- `useReferralCodeCheck` + `adapters/http/referralCheck.ts`: debounce 500 ms,
  `AbortController`, normalisasi uppercase+trim, respons basi diabaikan, kosong tetap
  netral. Field menampilkan border/ring hijau + `Kode referral valid.`, atau border merah
    - alasan dari server. Error submit tetap punya prioritas.
- Validasi submit di server tidak berubah.

### Copy dan kartu referral

- Istilah `Atribusi` diganti `Pemberi kode` agar jelas bahwa isian itu untuk siapa yang
  memberi kode: judul section, label `Nama pemberi kode`, label `Pengguna pemberi kode`,
  aria-label, pesan kunci identitas, dan deskripsi kartu. Kalimat bantu menyebut namanya
  muncul di laporan jawaban.
- Ringkasan `N kode · N pemakaian` dan tombol `Lihat jawaban` di header kartu referral
  dihapus; yang tersisa hanya switch aktif dan tombol `Tambah kode`.

### Perbaikan ikut

- Simpan kode referral gagal tanpa hit backend karena `usage_limit` dari input
  `type="number"` di-cast menjadi number lalu `.trim()` melempar TypeError. Tipe form
  menjadi `string | number` dan payload menormalkan dengan `String(...)`.
- Kolom Referral di tabel jawaban hanya menampilkan kode (fallback `Tanpa referral`) dan
  chip kode kini rata tengah (sebelumnya `line-clamp` membuat chip menempel kiri).
- E2E `referral-attribution` sekarang mengisi batas pemakaian sebelum Simpan.

## Bukti

| Pemeriksaan                                                                                                                                                     | Hasil                                                                                                                                                 |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php artisan test tests/Feature/Forms/ReferralCodeCheckTest.php` + FormDraft + FormPurposeRules + FormBuilderHttp + ReferralRedemption + ReferralCodeManagement | 113 passed (554 assertions)                                                                                                                           |
| `npm run test:unit`                                                                                                                                             | 1428 passed (139 file)                                                                                                                                |
| `npx vue-tsc --noEmit`                                                                                                                                          | exit 0                                                                                                                                                |
| ESLint 20 file tersentuh                                                                                                                                        | no issues                                                                                                                                             |
| Pint 8 file backend                                                                                                                                             | PSR-12 PASS                                                                                                                                           |
| E2E `referral-attribution`, `referral-placement`, `referral-fill-validation`, `du-field-mapping`, `du-drawer-issue`                                             | 7 passed                                                                                                                                              |
| Probe browser (form dev)                                                                                                                                        | blok di antara field anchor, kode valid hijau + `Kode referral valid.`, kode salah merah + `Kode referral tidak ditemukan.`, 1 request per jeda ketik |

## Batasan

- Memindahkan blok antar-bagian (section) belum didukung; blok tampil di bagian tempat
  anchor berada dan bisa digeser di dalam bagian itu. Drop di bagian lain akan
  menempatkan anchor ke field terakhir bagian tersebut.
- Drag blok hanya tersedia di desktop; mobile memakai tombol naik/turun.
- Validasi live tidak mengecek apakah pengguna sudah pernah submit form yang sama.
