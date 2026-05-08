# Milestone — D-Form v2

**Tujuan dokumen:** memberi tahapan kerja yang jelas agar tim bisa **paralel** dengan dependensi terbaca, dan agar **migrasi UI dari Livewire ke Vue (Inertia)** tidak ditunda ke satu big-bang di akhir.

**Acuan produk:** [prd.md](prd.md).  
**Acuan teknis:** [README](../README.md), [pedoman front-end](rules/front-end.md), [pedoman back-end](rules/back-end.md).

---

## Diagram dependensi antar fase

```mermaid
flowchart LR
  M0[M0_Baseline]
  M1[M1_AuthRoles]
  M2[M2_Events]
  M3[M3_FormBuilder]
  M4[M4_Registration]
  M4b[M4b_TeamInvite]
  M5[M5_EmailQR]
  M6[M6_Attendance]
  M7[M7_Dashboard]
  M8[M8_Hardening]
  M0 --> M1 --> M2
  M2 --> M3 --> M4 --> M5 --> M6
  M4 --> M4b
  M4b --> M5
  M4 --> M7
  M6 --> M7
  M7 --> M8
```

- **M4 → M7** dan **M6 → M7**: dasbor dapat mulai diisi data dummy/contract API paralel dengan penyelesaian M4/M6, asal kontrak response disepakati di M0.
- **M4b**: perluasan **pendaftaran tim** (metadata form, `is_append`, banyak `form_answer` per submit, konfirmasi anggota, notifikasi undangan); mengikat **M4** ke **M5** untuk pengiriman notifikasi/email terkait undangan.

---

## Migrasi Livewire → Vue (lintas fase)

Modul yang masih memakai Livewire di repositori harus mencapai **parity perilaku** di halaman Inertia/Vue sebelum fitur tersebut dianggap “selesai” untuk milestone terkait:

- Setiap fase di bawah mencantumkan **Cek migrasi** — tim menghapus atau mengisolasi rute/view Livewire yang sudah tergantikan agar tidak ada dua sumber kebenaran untuk UX yang sama.
- Prioritas: **Auth** → **Event & form admin** → **Publik pendaftaran** → **Dasbor**.

---

## M0 — Baseline & arsitektur

| | |
|---|---|
| **Tujuan** | Satu cara yang jelas untuk menambah halaman Inertia, layout, i18n, dan naming; keputusan Filament vs full Vue. |
| **Owner (saran)** | Fullstack / lead |
| **Deliverable** | Konvensi route (`routes/web/*.php`), pemetaan `resources/js/pages/*`, penggunaan layout di `resources/js/layouts`; keputusan tertulis: apakah Filament dipakai untuk subset admin atau tidak; strategi branch/PR singkat (mis. satu PR per milestone kecil). |
| **Kriteria selesai** | Dokumen atau ADR mini di repo/wiki tim; contoh satu halaman baru dari nol mengikuti pola; tidak ada kebingungan “halaman ini Blade atau Vue?”. |
| **Paralelisasi** | Dokumentasi bisa jalan bersamaan dengan spike teknis M1. |
| **Cek migrasi** | Inventaris komponen Livewire yang menabrak rute yang sama dengan Inertia — daftar prioritas penghapusan. |

---

## M1 — Autentikasi & peran

| | |
|---|---|
| **Tujuan** | Login, register, lupa password di **Vue + Inertia**; middleware peran; area admin vs member terpisah. |
| **Owner (saran)** | FE utama + BE untuk policy & route |
| **Deliverable** | Halaman auth Vue; integrasi Spatie permission (atau pola yang dipakai proyek); redirect sesuai peran setelah login. |
| **Kriteria selesai** | Semua jalur auth utama lolos uji manual; tidak ada akses panel admin untuk role member pada route yang dilindungi. |
| **Paralelisasi** | Setelah kontrak user/session di Inertia stabil, M2 bisa mulai untuk CRUD event di branch terpisah. |
| **Cek migrasi** | Form login/register Livewire digantikan; view Blade khusus auth legacy tidak lagi menjadi jalur utama. |

---

## M2 — Manajemen event

| | |
|---|---|
| **Tujuan** | CRUD event lengkap sesuai PRD (judul, deskripsi, lokasi, tanggal event, buka–tutup pendaftaran, kuota); daftar, detail, status pendaftaran. |
| **Owner (saran)** | Fullstack |
| **Deliverable** | Controller + Form Request + halaman Inertia; model & migrasi selaras dengan `events` (dan relasi minimal). |
| **Kriteria selesai** | Admin dapat membuat event dan melihat daftar/detail; status pendaftaran konsisten dengan tanggal dan kuota. |
| **Paralelisasi** | Desain API/form field untuk M3 dapat dibahas setelah struktur `events` stabil. |
| **Cek migrasi** | Halaman daftar/detail/create event yang sebelumnya Livewire memiliki parity di Vue (`ListPage`, `EventDetail`, `CreateForm`, dll. sesuai kode). |

---

## M3 — Form builder dinamis

| | |
|---|---|
| **Tujuan** | Admin mengonfigurasi field per event: label, nama, tipe, urutan (sortable), aturan validasi. |
| **Owner (saran)** | FE (UI builder) + BE (skema simpan & validasi) |
| **Deliverable** | UI builder; persist ke **`form_fields`** (bukan nama tabel lama); validasi server untuk konfigurasi (mis. nama field unik per form); dukungan penyimpanan **`is_append`** per field dan (bersama halaman pengaturan form) pengisian **`metadata`** form untuk **mode pendaftaran** / **`team_size`** sesuai PRD. |
| **Kriteria selesai** | Form dapat disusun dan disimpan ulang tanpa kehilangan urutan; tipe field sesuai PRD. |
| **Paralelisasi** | Mock data field untuk M4 jika penyimpanan M3 belum merge (hindari blokir panjang). |
| **Cek migrasi** | Modul form admin Livewire (`EditForm`, `FormDetail`, `create-form`, dll.) tergantikan atau diarahkan ke rute Vue. |

---

## M4 — Pendaftaran member

| | |
|---|---|
| **Tujuan** | Member memilih event, mengisi form dinamis, submit; data jawaban disimpan (JSON); upload file dengan validasi. Mendukung **mode individu**; **mode tim** dijabarkan di **M4b**. |
| **Owner (saran)** | Fullstack |
| **Deliverable** | Render field dari definisi M3; **`form_answers`**; storage file; validasi dinamis server-side mencerminkan rules admin. Untuk tim: skema simpan diperluas di M4b (banyak baris per submit leader). |
| **Kriteria selesai** | Satu alur pendaftaran end-to-end untuk semua tipe field MVP; gagal validasi menampilkan error per field. |
| **Paralelisasi** | Kontrak payload submission untuk M5 (email) dan M6 (QR menempel pada submission). |
| **Cek migrasi** | Tidak ada duplikasi form pendaftaran di Blade/Livewire untuk event yang sama. |

---

## M4b — Pendaftaran tim: metadata form, `is_append`, undangan & konfirmasi anggota

| | |
|---|---|
| **Tujuan** | Memungkinkan **ketua tim (leader)** mengajukan satu kali dan sistem menyimpan **beberapa** `form_answer` (satu per pengguna); anggota yang diundang memiliki **`status_confirmation_member` false** sampai memvalidasi data di **halaman & route member** yang baru; mengirim **notifikasi undangan** (leader → anggota). |
| **Acuan produk** | [PRD D-Form v2 — §4.2 Pendaftaran tim](prd.md). |
| **Owner (saran)** | Fullstack (BE transaksi + policy + FE portal member + notifikasi). |
| **Deliverable** | **Migrasi / skema:** kolom **`metadata`** (JSON) pada `forms` untuk **`registration_mode`** dan **`team_size`**; kolom **`is_append`** pada `form_fields`; kolom tim pada `form_answers` (mis. **`status_confirmation_member`**, referensi ke submission leader). **Backend:** submit leader memakai **foreach** (atau setara) untuk menciptakan baris per anggota dengan flag konfirmasi sesuai PRD. **Notifikasi:** saluran baru untuk undangan anggota (mis. Laravel Notification + mail/queue). **Frontend:** route dan halaman Inertia member untuk menampilkan, menyunting field yang diizinkan (`is_append`), dan mengonfirmasi `form_answer`. **Builder admin:** mengatur `is_append` dan metadata mode tim pada form. |
| **Kriteria selesai** | Satu submit tim menciptakan baris untuk leader dan setiap anggota sesuai desain; anggota tidak terkonfirmasi di DB sampai aksi di halaman khusus; undangan terkirim; tidak ada regresi pada alur pendaftaran individu. |
| **Paralelisasi** | Dapat dibuka branch setelah kontrak M4 stabil; pengiriman undangan dapat memakai infrastruktur antrean **M5** (template/job terpisah). |
| **Cek migrasi** | Semua alur di jalur Inertia/Vue; tidak ada duplikasi dengan modul pendaftaran Livewire. |

---

## M5 — Email & QR

| | |
|---|---|
| **Tujuan** | Email konfirmasi berisi detail event, data peserta, QR unik; antrean; log di `email_logs`. |
| **Owner (saran)** | BE + FE (template/preview opsional) |
| **Deliverable** | Job queue; Mailable; generate QR terikat submission ID; konfigurasi SMTP; entri log sukses/gagal; **(perluasan M4b)** notifikasi/email **undangan anggota tim** mengikuti pola terkirim yang disepakati (queue, log bila relevan). |
| **Kriteria selesai** | Pendaftaran sukses memicu email (via queue); QR dapat ditampilkan/diuji di email test. |
| **Paralelisasi** | Template email bisa dipoles paralel dengan M6 setelah payload QR final. |
| **Cek migrasi** | N/A khusus Livewire; pastikan trigger email dari controller/Inertia bukan dari komponen LW. |

---

## M6 — Absensi QR

| | |
|---|---|
| **Tujuan** | Admin memindai QR (kamera web); sistem mencatat waktu, event, submission, admin yang memindai. |
| **Owner (saran)** | FE (scanner UX) + BE (endpoint idempotent / aturan double-scan) |
| **Deliverable** | Halaman scan; decode QR → lookup submission; tulis `event_attendances`; aturan duplikasi (mis. tolak scan kedua atau izinkan dengan flag — **disepakati di PRD revisi kecil jika perlu**). |
| **Kriteria selesai** | Scan valid menambah baris kehadiran; scan tidak valid memberi pesan jelas. |
| **Paralelisasi** | Uji perangkat dapat dilakukan oleh QA paralel setelah API scan ada. |
| **Cek migrasi** | Fitur serupa di Livewire (jika ada) digantikan halaman Vue. |

---

## M7 — Dasbor & pelaporan

| | |
|---|---|
| **Tujuan** | Statistik pendaftar per event, kehadiran, daftar peserta, data absensi; ekspor MVP (mis. CSV). |
| **Owner (saran)** | FE (tabel/chart) + BE (query teroptimasi) |
| **Deliverable** | Halaman dasbor admin; filter per event; unduhan atau tampilan yang dapat di-screenshot untuk laporan sederhana. |
| **Kriteria selesai** | Angka yang ditampilkan konsisten dengan data di DB; ekspor dapat dibuka di spreadsheet. |
| **Paralelisasi** | Query report dapat ditulis setelah M4/M6 data tersedia; UI bisa memakai skeleton. |
| **Cek migrasi** | Widget dasbor Livewire digantikan Inertia/Vue. |

---

## M8 — Stabilisasi

| | |
|---|---|
| **Tujuan** | Kepercayaan rilis: tes, aksesibilitas dasar, dokumentasi operasional. |
| **Owner (saran)** | Seluruh tim |
| **Deliverable** | Tes fitur untuk jalur kritis (auth, daftar, email, scan); perbaikan bug P1; README/deployment tetap akurat. |
| **Kriteria selesai** | Daftar smoke test lolos; tidak ada regresi besar pada migrasi LW→Vue yang tersisa. |
| **Paralelisasi** | Bugfix diprioritaskan berdasarkan dampak user. |
| **Cek migrasi** | Dependensi Livewire/Volt dapat dievaluasi untuk dihapus dari `composer.json` jika tidak terpakai (keputusan terpisah setelah verifikasi). |

---

## Ringkasan paralelisasi yang aman

| Bekerja bersamaan | Syarat |
|-------------------|--------|
| M1 + spike M2 | Kontrak user & layout dasar jelas. |
| M3 UI + M2 API | Kontrak bentuk `event` dan `event_form_fields` disepakati. |
| M7 UI + M4/M6 | Mock JSON atau seed data; kontrak response dasbor. |
| M3 + M4b UI | Metadata form & `is_append` tersimpan dari builder; tidak konflik dengan penyimpanan field eksisting. |
| M4b + M5 | Template/payload email undangan disepakati; worker queue sama untuk email registrasi dan undangan (jika keduanya email). |
| Dokumentasi + fitur | Perubahan README hanya menyentuh bagian yang relevan agar tidak konflik merge. |

---

## Referensi

- [PRD — D-Form v2](prd.md)
- [README](../README.md)
