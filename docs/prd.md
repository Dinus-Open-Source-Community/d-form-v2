# Product Requirements Document (PRD) — D-Form v2

**Versi dokumen:** 1.2  
**Sumber:** Project Brief D-Form v2 (PDF), diselaraskan dengan keadaan repositori, keputusan stack frontend, dan **skema basis data terbaru** (`database/migrations/`, termasuk perubahan Mei 2026).

Dokumen ini mendefinisikan *apa* yang dibangun dan kriteria utamanya. Detail implementasi mengikuti [pedoman front-end](rules/front-end.md), [pedoman back-end](rules/back-end.md), dan [struktur folder](02-directory-structure.md). Tahapan pengiriman diuraikan di [milestone.md](milestone.md).

---

## 1. Ringkasan produk

D-Form v2 adalah aplikasi web untuk **membuat, mengelola, dan memproses formulir pendaftaran event secara dinamis**. Admin mendefinisikan event dan form tanpa mengubah kode; peserta mendaftar melalui form tersebut, menerima **email konfirmasi berisi QR Code**, dan kehadiran dicatat dengan **pemindaian QR** saat event berlangsung.

---

## 2. Tujuan produk

1. Menyediakan platform manajemen event berbasis web yang **dinamis dan dapat dikembangkan** (scalable secara arsitektur).
2. Memudahkan admin membuat **form event dengan input yang sepenuhnya dapat dikustomisasi** (label, tipe field, validasi, urutan).
3. Mempercepat **pendaftaran oleh peserta** melalui alur yang jelas (pilih event → isi form → konfirmasi).
4. Mengotomatisasi **email konfirmasi** pendaftaran beserta **QR unik** untuk absensi.
5. Menyediakan **absensi berbasis QR** yang terukur (waktu, event, petugas scan).
6. Menegakkan **pemisahan peran Admin dan Member** untuk keamanan dan pembatasan akses.

---

## 3. Persona dan use case utama

### 3.1 Admin / Event organizer

- Membuat dan mengatur event (metadata dan jadwal pendaftaran).
- Mendesain **form pendaftaran dinamis** per event, termasuk (jika dipakai) **mode tim**, **`team_size`** di metadata form, dan penandaan field **`is_append`** untuk data yang boleh disunting anggota saat konfirmasi.
- (Fase lanjutan) Mengirim **siaran email** terkait event mendatang atau perubahan informasi.
- Melihat **daftar peserta** dan statistik kehadiran.
- Melakukan **pemindaian QR** untuk absensi (kamera perangkat melalui web app).

### 3.2 Member / peserta

- Mendaftar ke event yang dibuka.
- Mengisi **form dinamis** sesuai konfigurasi admin.
- Pada **mode pendaftaran tim**: dapat diundang oleh **leader tim** (pemilik submission utama); menerima **notifikasi undangan**; membuka **halaman khusus** untuk meninjau, mengoreksi (pada field yang diizinkan), dan **memvalidasi / menyetujui** data `form_answer` yang awalnya diisi leader.
- Menerima **email konfirmasi** (detail event + QR) sesuai alur produk (biasanya setelah submission diterima admin, untuk jalur individu atau leader tim).
- Menghadiri event dan menunjukkan QR untuk discan.

---

## 4. Lingkup fitur

### 4.1 MVP (wajib untuk rilis pertama)

| Area | Deskripsi |
|------|-----------|
| **Autentikasi & peran** | Multi-peran: Admin dan Member. Alur login, registrasi akun, lupa password; akun dapat menautkan
| **Autentikasi & peran** | Multi-peran: Admin dan Member. Alur login, registrasi akun, lupa password; akun dapat menautkan **Google** dan/atau **GitHub** (`google_id`, `github_id` pada `users`). Akses dibatasi middleware berdasarkan peran / permission. |
| **Manajemen event** | Admin membuat event dengan: judul, deskripsi, lokasi, rentang tanggal event, jendela buka–tutup pendaftaran, kuota, **harga (`price`)**, **banner**, **status**, **kategori** dan **sesi/informasi sesi** (kolom teks panjang di skema), serta **`registered_count`** (counter denormalisasi jumlah pendaftar). Halaman daftar event, detail event, dan indikator status pendaftaran (buka/tutup/kuota). |
| **Form per event** | Satu event dapat memiliki **satu atau lebih** entri `forms`: judul/deskripsi form, **`closed_at`** (penutupan form), **`visible_for`** (siapa yang boleh melihat/mengisi — disimpan sebagai JSON dipetakan ke enum aplikasi), opsional **banner form** (`banner_url` berbasis teks panjang untuk URL/data URL, plus `banner_caption`), dan **`metadata`** JSON untuk **`registration_mode`**, **`team_size`**, dan parameter mode tim lain bila diperlukan. |
| **Form builder dinamis** | Per form: label, nama field internal, **`input_type`** di basis data: `input`, `selectInput`, `textarea`, `datePicker`, `fileUpload`, **`radio`**, **`checkbox`** (perluasan enum di migrasi MySQL/MariaDB; SQLite mengikuti definisi tabel), deskripsi field opsional, **`metadata` JSON** untuk opsi, validasi, dan perilaku tambahan, **`is_append`** (opsional: field yang boleh disunting anggota pada alur konfirmasi tim), serta **`order`** urutan field. |
| **Pendaftaran (Member)** | Pemilihan event dan form yang relevan, render form otomatis, penyimpanan jawaban dalam **kolom JSON `answers`** pada **`form_answers`**, upload file jika ada field file, validasi sesuai aturan admin. **Constraint unik `(user_id, form_id)`**: satu pengguna hanya boleh **satu submission** per form (pendaftaran ganda dicegah di basis data). **Mode tim:** satu pengajuan leader dapat membuat **beberapa** `form_answer` (loop / batch), dengan anggota memiliki **`status_confirmation_member` false** sampai memvalidasi di halaman khusus; notifikasi **undangan leader → anggota** dikirim sesuai desain teknis. |
| **Notifikasi email** | Email sukses pendaftaran berisi detail event, ringkasan data peserta, dan **QR unik** terikat pada submission (`form_answers`); serta (pada mode tim) **notifikasi undangan** dari leader ke anggota sesuai PRD §4.2. Pengiriman melalui **SMTP**. Pengiriman non-blocking memakai **antrean (queue)**. **Audit pengiriman** pada tabel **`email_logs`** (untuk email yang dilog-kan di sistem): tautan ke `form_answer_id` (nullable jika submission dihapus), `event_id`, `user_id`, alamat penerima, **status**, pesan error opsional, **`sent_at`**. |
| **Absensi QR** | Satu QR unik per **submission** (`form_answers`); pemindaian untuk kehadiran merupakan sasaran produk (waktu, event, **admin yang memproses scan**). **Catatan skema:** belum ada tabel dedikasi `event_attendances` (atau setara) di migrasi saat ini — implementasi pencatatan per scan dapat mengikuti milestone teknis terpisah. |
| **Dasbor & pelaporan** | Admin melihat jumlah pendaftar per event, statistik kehadiran, data peserta, dan log/rekaman absensi (minimal tampilan tabular; ekspor dapat berupa CSV pada MVP). |

### 4.2 Pendaftaran tim (leader & anggota)

Fitur ini memperluas **Pendaftaran (Member)** ketika form dikonfigurasi untuk mode tim.

| Aspek | Requirement |
|--------|-------------|
| **Metadata form (`forms.metadata`)** | Kolom **JSON** di tabel `forms` menyimpan konfigurasi mode pendaftaran, minimal: **`registration_mode`** (mis. individu vs tim) dan **`team_size`** (ukuran tim / jumlah peserta per satu pengajuan tim, sesuai keputusan implementasi). |
| **Field `is_append` (`form_fields`)** | Flag **`is_append`** menyatakan apakah suatu field **boleh diubah/ditambahkan** oleh anggota saat memvalidasi data yang awalnya diisi leader — dipakai untuk validasi server-side dan batasan UI di halaman konfirmasi anggota. |
| **Penyimpanan banyak `form_answer` sekaligus** | Satu aksi simpan dari **leader** menghasilkan **beberapa baris** `form_answers` (satu per pengguna terlibat, dalam transaksi / loop yang terkontrol). **Leader** memiliki baris utama; setiap **anggota yang diundang** mendapat baris sendiri dengan jawaban yang disalin atau diselaraskan dengan submission leader. |
| **`status_confirmation_member`** | Pada setiap `form_answer` anggota (bukan leader), nilai **`status_confirmation_member`** awalnya **false / 0** sampai anggota **menerima atau memvalidasi** datanya di halaman khusus member. Leader (submission utama) memiliki status konfirmasi yang sesuai aturan bisnis (biasanya **true** sejak tersimpan). |
| **Notifikasi undangan** | Sistem mengirim **notifikasi baru** (email dan/atau saluran lain yang disepakati teknis) kepada anggota ketika leader menambahkan mereka ke tim — isi pesan menjelaskan bahwa data pendaftaran perlu ditinjau dan disetujui. |
| **Rute & halaman member** | **Route** dan **halaman Inertia/Vue** baru di area portal member untuk: melihat data `form_answer` terkait undangan, mengedit bagian yang diizinkan (`is_append`), dan menyelesaikan **validasi / konfirmasi** sehingga `status_confirmation_member` menjadi **true**. |

**Catatan integrasi:** Alur review admin (`review_status`), email konfirmasi registrasi, dan QR harus konsisten dengan aturan “anggota hanya dianggap ‘siap’ untuk langkah berikutnya setelah konfirmasi” bila produk mewajibkan hal itu (detail disepakati di milestone terkait).

### 4.3 Fase lanjutan (di luar MVP inti)

- **Email broadcast:** pengingat event mendatang, pemberitahuan perubahan jadwal/lokasi/deskripsi.
- Pelaporan lanjutan (filter lanjutan, agregasi, unduhan format tambahan).
- Optimasi skala dan observabilitas (metrik, rate limit, dsb.) sesuai kebutuhan operasional.

---

## 5. Kebutuhan non-fungsional

| Aspek | Requirement |
|-------|-------------|
| **Keamanan** | Autentikasi sesi/Laravel; pembatasan route per peran; validasi server-side untuk semua input form dinamis; upload file dibatasi tipe dan ukuran. |
| **Performa** | Pengiriman email tidak menghalangi response HTTP utama (queue). |
| **Audit email** | Riwayat pengiriman disimpan di **`email_logs`** (status, error, `sent_at`, korelasi ke submission/event/user). |
| **Audit absensi** | Rekaman scan (admin, waktu) menjadi sasaran akuntabilitas; **persistensi** mengikuti desain migrasi ketika tabel absensi ditambahkan. |
| **Kompatibilitas** | Flow scan QR dapat berjalan pada browser umum di desktop/mobile dengan akses kamera (dengan izin pengguna). |
| **Kualitas kode** | Formatter PHP (Laravel Pint, PSR-12) dan Prettier untuk front-end sesuai konfigurasi proyek. |

---

## 6. Stack teknologi (keputusan disepakati)

Deviasi dari Project Brief PDF bagian Blade/Livewire: **UI aplikasi utama dibangun dengan Vue + Inertia**, bukan Livewire/Volt sebagai lapisan UI utama.

### 6.1 Backend

- Laravel 12, PHP 8.2+.
- Endpoint/halaman melalui **Inertia** (response `Inertia::render`) dan validasi Form Request / controller sesuai pola proyek.
- Basis data: **MySQL / MariaDB**; **Redis** untuk cache/antrean sesuai konfigurasi.
- **Laravel Queue** untuk pengiriman email.
- Pustaka **QR Code**: **`endroid/qr-code`** (sesuai `composer.json`) untuk generate gambar / payload QR.
- Web server: sesuai deployment tim (mis. Frankenphp/Octane seperti yang tercatat di README proyek).

### 6.2 Frontend

- **Vue 3** (Composition API, `<script setup>`) dan **Inertia.js** untuk navigasi dan state halaman.
- **Tailwind CSS** dan komponen UI mengikuti konvensi repositori (mis. pola Shadcn-Vue di `resources/js/components/ui` — lihat [front-end rules](rules/front-end.md)).
- **Bukan** Livewire/Volt untuk fitur-fitur di atas; migrasi dari modul Livewire yang masih ada adalah bagian dari pekerjaan berkelanjutan (lihat [milestone.md](milestone.md)).

### 6.3 Opsional / keputusan tim

- **Filament** (paket ada di `composer.json`) dapat dipakai untuk panel admin terpisah atau dikurangi seiring konsolidasi UI di Vue — keputusan eksplisit disarankan di milestone M0 agar tidak ada duplikasi UX.

### 6.4 Tooling

- Node.js & npm untuk build front-end (Vite).
- Docker untuk lingkungan tim (folder `docker/`).
- Dokumentasi kode: PHPDoc dan Markdown sesuai kebiasaan proyek.

---

## 7. Model data (ringkas)

Entitas utama mengikuti **migrasi aktual** di `database/migrations/` (Laravel). Nama tabel di bawah ini menggantikan ringkasan brief yang sebelumnya memakai penamaan `event_form_*` / jadwal terpisah — **skema repositori memakai `forms` / `form_fields` / `form_answers`**, tanpa tabel `event_schedules` atau `event_attendances` pada migrasi saat ini (fitur absensi QR tetap pada rencana produk; payload QR mengacu **ID submission** / `form_answers`).

| Entitas / tabel | Peran |
|-----------------|--------|
| `users` | Akun pengguna (UUID); `google_id`, **`github_id`** untuk OAuth; peran Admin/Member lewat **Spatie Laravel Permission** (`permissions`, `roles`, pivot terkait). |
| `events` | Metadata event: tanggal, lokasi, kuota, **`registered_count`**, banner, harga, status, kategori & sesi (tipe teks panjang setelah migrasi perluasan kolom), jendela pendaftaran; soft delete. |
| `forms` | Form pendaftaran terikat **`event_id`**; judul, deskripsi, **`visible_for`** (JSON), **`closed_at`**, **`banner_url`** (longText, nullable), **`banner_caption`** (nullable), **`metadata`** JSON (nullable) untuk **`registration_mode`**, **`team_size`**, dan konfigurasi terkait mode tim; soft delete. |
| `form_fields` | Definisi field per **`form_id`**: **`input_type`** (enum/string sesuai driver: `input`, `selectInput`, `textarea`, `datePicker`, `fileUpload`, `radio`, `checkbox`), label, nama internal, **`metadata`** JSON, **`is_append`** (boolean: field boleh disunting anggota saat validasi undangan), **`order`**; soft delete. |
| `form_answers` | Satu baris **submission** per kombinasi **user + form** (unik di basis data); kolom **`answers`** JSON; foreign key ke `users` dan `forms`; untuk tim: relasi ke submission leader (mis. **`leader_form_answer_id`**) dan **`status_confirmation_member`** (anggota belum konfirmasi = false sampai memvalidasi di halaman member). Satu submit leader dapat menciptakan banyak baris melalui penyimpanan berulang per pengguna. |
| `email_logs` | Audit pengiriman email: `form_answer_id` (nullable, `nullOnDelete`), `event_id`, `user_id`, `recipient_email`, `status`, `error_message`, `sent_at`; indeks pada `form_answer_id` dan `(event_id, status)`. |

**Infra Laravel (bukan domain bisnis inti, tetapi ada di migrasi):** `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `sessions`, `password_reset_tokens`.

---

## 8. Kriteria sukses (definisi “selesai” tingkat produk)

- Alur **Admin**: buat event → bangun form → buka pendaftaran → lihat peserta → scan QR → lihat statistik kehadiran berjalan tanpa error kritikal pada jalur utama.
- Alur **Member**: daftar → isi form dinamis → terima email dengan QR → hadir dan tercatat absensi; **mode tim:** anggota yang diundang membuka halaman konfirmasi, menyesuaikan data (field `is_append`), lalu memvalidasi sebelum mengikuti alur berikutnya.
- Kebijakan akses: Member tidak mengakses panel admin; Admin tidak mengubah data orang lain tanpa alur yang disengaja (sesuai desain permission).
- Dokumentasi operasional minimal: cara set SMTP, queue worker, dan menjalankan build front-end (mengacu [README](../README.md) dan [instalasi](01-installation.md)).

---

## 9. Luar lingkup (eksplisit)

- Aplikasi mobile native (hanya web yang responsif).
- Pembayaran / gateway (kecuali ditambahkan di PRD revisi).
- Integrasi kalender pihak ketiga (Google Calendar, dsb.) kecuali ditambahkan kemudian.

---

## 10. Referensi

- [Milestone & fase pengiriman](milestone.md)
- [README proyek](../README.md)
- Skema basis data: folder migrasi Laravel [`../database/migrations/`](../database/migrations/)
- Project Brief D-Form v2 (PDF)
