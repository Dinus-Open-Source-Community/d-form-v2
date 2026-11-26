# Harga form dan diskon referral

Issue: DFORM-TBD · Commit: TBD · Tanggal: 26-11-2026

## Ringkasan (TL;DR)

Harga form kini disimpan di `form_fields.metadata.price` (integer rupiah) pada tipe
builder `Harga`. Halaman isi menampilkan harga dasar, lalu harga setelah diskon dan
nominal penghematan saat kode referral valid dipakai. Diskon hidup di `referral_codes.discount_type` +
`discount_value` (persen atau rupiah) dan boleh diubah kapan pun. Semua hitungan ada
di server (`FormPriceService`); client hanya menampilkan angka dari endpoint cek.
Harga final disnapshot ke `form_answers.pricing` saat submit, untuk leader dan semua
member satu submission.

Form tanpa `metadata.price` tampil identik seperti sebelumnya. Tidak ada backfill
dan tidak ada jawaban lama yang berubah.

## Perubahan

### Backend

- Migration `2026_11_26_000001_add_discount_to_referral_codes`: nullable
  `discount_type` (`percentage`/`fixed`) dan `discount_value`.
- Migration `2026_11_26_000002_add_pricing_to_form_answers`: nullable
  `form_answers.pricing` (JSON snapshot).
- `App\Enums\ReferralDiscountType` dan cast enum di `ReferralCode`.
- `FormPriceService` menjadi satu-satunya sumber hitungan:
  `basePrice()` membaca field bertipe `price` pertama sesuai `order` (metadata harga
  pada tipe lain diabaikan), `quote()` menghitung
  persen dengan `intdiv` (bulat ke bawah), fixed di-clamp ke harga, final minimal 0,
  `snapshot()` menulis JSON yang sama ke leader dan member.
- `ReferralRedemptionService::check()` mengembalikan kode yang sudah di-resolve
  (valid saja) tanpa mengubah perilaku read-only; `FormReferralCheckController`
  menambahkan `price` pada respons cek.
- `ReferralCodeRequest`: validasi pasangan jenis/nilai diskon (harus sama-sama diisi
  atau sama-sama kosong), persen 1..100, nominal minimal 1. Diskon tidak masuk daftar
  identitas yang terkunci sehingga tetap bisa diubah setelah kode dipakai.
- `ReferralCodeService`: menyimpan diskon saat create/update dan mengeksposnya di
  `payload()`.
- `FormFieldsRequestValidation`: `metadata.price` integer minimal 0, maksimal satu
  field bertipe `price` berharga per form, dan harga hanya untuk purpose
  `registration`/`d_kelas`.
  Purpose dibaca dari payload, lalu model, dengan fallback pendaftaran seperti
  `Form::purpose()`.
- `RulesBuilder::DISPLAY_ONLY_BUILDER_TYPES` menambah `price` sehingga field harga
  tidak pernah menjadi kunci jawaban.
- `FormSubmissionController`: snapshot harga di dalam transaksi submit pada jalur
  `single`, `team`, dan `bundle`, setelah redemption memakai form yang sudah dikunci.
  Jalur `submitOtherForm` dan `GuestFormSubmissionService` tidak disentuh.

### Frontend

- `lib/formatRupiahPrice.ts` dan `lib/formPrice.ts` menjadi helper bersama untuk
  format rupiah dan pembacaan `metadata.price`.
- `components/core/FormPriceBlock.vue` dipakai halaman isi, kanvas builder, dan
  pratinjau. Tanpa diskon: satu baris label kiri dan nominal kanan. Dengan diskon:
  rincian bergaya ringkasan pesanan (`harga`, `diskon`, garis pemisah, `Total`),
  total menjadi elemen paling kuat, semua angka `tabular-nums`, tanpa badge atau
  warna semantik hijau.
- `useReferralCodeCheck` menyimpan `price` dari respons dan meresetnya saat kode
  kosong, tidak valid, gagal, atau sedang dicek ulang. `adapters/http/referralCheck.ts`
  memvalidasi bentuk quote sebelum dipakai.
- Tipe builder baru `price` (union, guard, palet kategori Content, factory, mapping,
  presentasi, kontrak fixture, dan daftar display-only).
- `FieldEditor` menampilkan input `Harga (Rp)` hanya untuk field `Harga`, dan hanya
  pada form `registration`/`d_kelas`. Kosong berarti field bukan blok harga.
- `FieldRenderer` dan `FormPreviewDialog` menampilkan blok harga yang sama tanpa diskon.
- `FormFillFieldsList` merender blok harga untuk field `Harga` berharga dan
  meneruskan quote referral.
- Drawer kode referral mendapat bagian `Diskon` (chip Persen/Rupiah + nilai) dan
  daftar kode menampilkan label diskon. Input diskon memakai `type=text` +
  `inputmode=numeric` tanpa spinner, menyaring karakter non-digit, menolak nilai
  negatif, dan membatasi persen di 100 saat mengetik lewat
  `sanitizeReferralDiscountInput`. Satuan tampil sebagai affix input (`Rp` di depan
  untuk rupiah, `%` di belakang untuk persen); teks bantuan tambahan dihapus dan
  label rupiah tidak lagi menulis `(Rp)`.
- Setiap baris kode referral punya tombol salin: ikon `Copy` bertukar ke `Check`
  selama satu detik dengan transisi 150 ms (menghormati `prefers-reduced-motion`)
  dan toast `Kode referral disalin.` dengan id tetap sehingga konfirmasi berulang
  menggantikan toast sebelumnya. `CopyFeedbackIcon` diekstrak ke `components/core/`
  dan dipakai juga oleh field salin di dialog Bagikan.

### Copy

- Label dan bantuan berbahasa Indonesia tanpa em dash.

### Spacing kartu dan hint upload

- Kartu field di halaman isi memakai `gap-0 py-0`; header `pt-4 pb-0` dan content
  `pt-3 pb-5` (bundle lebih rapat). Jarak label ke input kini konsisten 12 px dan
  tidak lagi mendapat gap 24 px bawaan `Card`. Halaman Apply recruitment memakai
  komponen yang sama dan ikut dirapikan.
- Hint upload: ekstensi ditulis uppercase tanpa label `Allowed`, ukuran memakai unit
  terbesar tanpa desimal bulat (`Maks. 5 MB`, bukan `Max size: 5120 KB`), dan baris
  memakai `leading-relaxed`.

### Banner halaman isi

- Gambar banner menutupi seluruh kartu (`h-56 sm:h-72`, `w-full object-cover`)
  sehingga tidak ada ruang kosong atau lapisan blur di kiri dan kanan.

### Header bagian halaman isi

- Strip `Bagian 1` di halaman isi hanya tampil saat form punya lebih dari satu
  bagian. Form satu bagian langsung menampilkan field tanpa header.
- Nama bagian di builder hanya tampil saat form punya lebih dari satu bagian, dan
  tampil sebagai input berlabel `Nama bagian` dengan border, bukan teks abu-abu yang
  terlihat statis. Judul kustom yang diisi di sana dipakai sebagai header halaman isi.

## Keputusan dan alternatif

- **Harga di `metadata.price`, bukan `events.price`.** Satu event bisa punya beberapa
  form dengan harga berbeda; `events.price` juga bukan kontrak halaman isi.
- **Hitungan diskon hanya di server.** Client tidak menghitung ulang, sehingga tidak
  ada dua implementasi yang bisa berbeda dan tidak ada harga yang bisa dimanipulasi.
- **Snapshot ke leader dan semua member.** Harga tetap milik submission, tetapi
  setiap baris menjadi self-describing sehingga laporan tidak melihat `pricing` null
  yang bukan jawaban lama.
- **Harga hanya untuk form pendaftaran.** Jalur submit non-registrasi dan guest
  berbeda dan tidak punya snapshot; validasi menolak harga di sana.
- **Diskon fixed tidak dibatasi di input, di-clamp saat dipakai.** Batas atas nominal
  tidak punya arti tanpa harga form, sedangkan clamp menjaga final tidak negatif.
- **Diskon boleh diubah setelah kode dipakai.** Redemption lama sudah punya snapshot
  sendiri; identitas (prefix/body/atribusi) tetap terkunci.
- **Harga hanya di tipe field `Harga`.** Input harga pada `Judul` dihapus setelah
  review UX: heading tidak menyimpan data peserta dan dua jalur pengaturan harga
  membuat perilaku tidak konsisten. `metadata.price` pada tipe lain diabaikan server
  dan dibuang saat field disimpan ulang.
- **Blok harga memakai pola ringkasan pesanan, bukan badge diskon.** Label kiri dan
  nominal kanan, diskon sebagai baris minus, total dipisah garis. Mengacu pada pola
  order summary checkout dan rekomendasi NN/g agar penghematan tampil dekat total
  dengan bobot yang tepat, bukan sebagai elemen yang terlihat seperti iklan.
- **`FormPriceBlock` di `components/core/`.** Dipakai dua modul (halaman isi dan
  builder), jadi tidak boleh tinggal di `modules/`. Blok menerima `showLabel` agar
  kartu builder tidak mengulang label field yang sudah tampil di header kartu.

## Kompatibilitas

- Kode referral lama: `discount_type`/`discount_value` null berarti tanpa diskon.
- Jawaban lama: `pricing` null; tidak ada query yang memaksa nilainya.
- Field `Judul` yang terlanjur membawa `metadata.price` tidak lagi dianggap harga;
  nilainya dibuang saat form disimpan ulang.
- Tipe `price` hanya muncul jika admin menambahkannya dari palet.

## Bukti verifikasi

- `FormPriceSchemaTest`: 2 passed.
- `FormPriceServiceTest`: 13 passed.
- `ReferralCodeCheckTest`: 13 passed.
- `ReferralCodeManagementTest`: 24 passed.
- `FormPriceFieldValidationTest`: 6 passed.
- `FormPriceSnapshotTest`: 5 passed.
- Regresi suite `Referral*`: 83 passed.
- Full PHP suite di container: 2288 passed, 16 skipped, 0 failed (182.05 s).
- Vitest penuh: 1448 passed, 142 file.
- `vue-tsc --noEmit`: exit 0. ESLint: exit 0.
- Build container (`podman exec -i -w /app d_form_app npm run build`): sukses 18.35 s.
  Entry `app` 16.27 kB gzip; chunk halaman terbesar `Show` 41.30 kB gzip; chunk
  terbesar keseluruhan `index` 145.66 kB gzip. Tidak ada chunk halaman di atas
  200 kB gzip.
- E2E `tests/e2e/form-price.desktop.spec.ts`: 4 skenario passed: (1) harga dasar,
  diskon, kode tidak valid, dan kode dikosongkan di halaman isi; (2) input diskon
  hanya angka dan persen maksimal 100; (3) tombol salin kode dengan toast dan
  transisi; (4) builder menyimpan harga.
- Regresi E2E referral (`referral-placement`, `referral-attribution`,
  `referral-fill-validation`): 5 passed.
- E2E `form-fill-section-header`: 1 skenario passed (header disembunyikan untuk satu
  bagian, nama bagian kustom tampil saat dua bagian).
- E2E `builder-section-rename`: 1 skenario passed (rename bagian tidak berubah).
- Probe browser form target: `Bagian 1` tidak lagi dirender, `Rp 95.000` tetap tampil,
  dan hint upload terbaca `PDF, DOCX, XLSX, PPTX, CSV, TXT · Maks. 5 MB`.

## Batasan yang diketahui

- Snapshot `pricing` belum ditampilkan di tabel atau drawer jawaban (sesuai spec;
  data sudah tersedia untuk follow-up).
- Palet builder belum sadar purpose, jadi tipe `Harga` masih bisa ditambahkan ke form
  non-registrasi; field itu tersimpan dengan harga 0 dan server menolak harga > 0.
- Tidak ada backfill harga untuk form lama; admin mengisi sekali lewat builder.
- Perubahan tidak menambah lock baru; snapshot berjalan di transaksi submit yang sudah
  ada sehingga risiko concurrency tidak berubah.
