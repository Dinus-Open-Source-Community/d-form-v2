# Product Requirements Document (PRD) — D-Form v2

**Versi dokumen:** 1.3  
**Sumber:** Project Brief D-Form v2 (PDF), diselaraskan dengan keadaan repositori, keputusan stack frontend, dan **skema basis data terbaru** (`database/migrations/`, termasuk perubahan Mei 2026). Revisi 1.3 menambahkan **pendaftaran bundle** (satu pendaftaran → banyak peserta) di §4.4.

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
- Mendesain **form pendaftaran dinamis** per event, termasuk (jika dipakai) **mode tim**, **`team_size` / `max_team_size`** di metadata form, **mode bundle** (`registration_mode`), penandaan field **`duplicatable`** di metadata field, dan penandaan field **`is_append`** untuk data yang boleh disunting anggota saat konfirmasi.
- (Fase lanjutan) Mengirim **siaran email** terkait event mendatang atau perubahan informasi.
- Melihat **daftar peserta** dan statistik kehadiran.
- Melakukan **pemindaian QR** untuk absensi (kamera perangkat melalui web app).

### 3.2 Member / peserta

- Mendaftar ke event yang dibuka.
- Mengisi **form dinamis** sesuai konfigurasi admin.
- Pada **mode pendaftaran tim** (§4.2): dapat diundang oleh **leader tim** (pemilik submission utama); menerima **notifikasi undangan**; membuka **halaman khusus** untuk meninjau, mengoreksi (pada field yang diizinkan), dan **memvalidasi / menyetujui** data `form_answer` yang awalnya diisi leader.
- Pada **mode bundle** (§4.3): leader mendaftarkan banyak peserta dalam satu pengajuan; setiap anggota memiliki `form_answer` sendiri dan menerima **undangan** untuk memverifikasi data serta **menerima atau menolak** sebelum alur review admin (untuk anggota: biasanya setelah status **accepted**).
- Menerima **email konfirmasi** (detail event + QR) sesuai alur produk (biasanya setelah submission diterima admin, untuk jalur individu atau leader tim).
- Menghadiri event dan menunjukkan QR untuk discan.

---

## 4. Lingkup fitur

### 4.1 MVP (wajib untuk rilis pertama)

| Area | Deskripsi |
|------|-----------|
| **Autentikasi & peran** | Multi-peran: Admin dan Member. Alur login, registrasi akun, lupa password; akun dapat menautkan **Google** dan/atau **GitHub** (`google_id`, `github_id` pada `users`). Akses dibatasi middleware berdasarkan peran / permission. |
| **Manajemen event** | Admin membuat event dengan: judul, deskripsi, lokasi, rentang tanggal event, jendela buka–tutup pendaftaran, kuota, **harga (`price`)**, **banner**, **status**, **kategori** dan **sesi/informasi sesi** (kolom teks panjang di skema), serta **`registered_count`** (counter denormalisasi jumlah pendaftar). Halaman daftar event, detail event, dan indikator status pendaftaran (buka/tutup/kuota). |
| **Form per event** | Satu event dapat memiliki **satu atau lebih** entri `forms`: judul/deskripsi form, **`closed_at`** (penutupan form), **`visible_for`** (siapa yang boleh melihat/mengisi — disimpan sebagai JSON dipetakan ke enum aplikasi), opsional **banner form** (`banner_url` berbasis teks panjang untuk URL/data URL, plus `banner_caption`), dan **`metadata`** JSON untuk **`registration_mode`** (`single` \| `bundle` dan varian mode tim), **`max_team_size`** / **`team_size`**, dan parameter terkait; soft delete. |
| **Form builder dinamis** | Per form: label, nama field internal, **`input_type`** di basis data: `input`, `selectInput`, `textarea`, `datePicker`, `fileUpload`, **`radio`**, **`checkbox`** (perluasan enum di migrasi MySQL/MariaDB; SQLite mengikuti definisi tabel), deskripsi field opsional, **`metadata` JSON** untuk opsi, validasi, **`duplicatable`** (bundle: field diulang per anggota), perilaku tambahan, **`is_append`** (opsional: field yang boleh disunting anggota pada alur konfirmasi tim), serta **`order`** urutan field. |
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

§4.2 menjelaskan mode tim secara umum. **§4.3 (pendaftaran bundle)** memperjelas mekanisme **satu pendaftaran = banyak peserta** dengan `group_token`, duplikasi field bertingkat (`duplicatable`), dan **konfirmasi undangan anggota** sebelum submission dianggap layak direview admin — tanpa mengubah prinsip **satu `form_answer` per peserta** untuk absensi dan QR.

### 4.3 Pendaftaran bundle (satu leader, banyak anggota; satu `form_answer` per orang)

#### 4.3.1 Latar belakang

Sistem form dinamis awalnya mengutamakan relasi **1 submission = 1 peserta**. Untuk jenis event yang membutuhkan **1 pendaftaran = banyak peserta** (grup/tim dipimpin satu ketua), produk menyediakan **pendaftaran bundle** dengan batasan:

- **Tidak mengubah arsitektur utama** form dinamis, penyimpanan jawaban **JSON** pada `form_answers`, alur **approval** per submission, dan alur **absensi + QR** (tetap per baris `form_answer`).
- Setiap peserta (leader maupun anggota) **tetap memiliki sendiri-sendiri** `form_answer`, **QR absensi sendiri**, dan **approval sendiri**; yang menghubungkan mereka adalah **`group_token`**.

#### 4.3.2 Konsep kunci

| Konsep | Penjelasan |
|--------|------------|
| **Satu peserta = satu `form_answer`** | Meskipun mendaftar bersama dalam satu bundle, setiap orang mempunyai baris `form_answer` sendiri agar absensi, QR, approval, dan ekspor tetap **per individu**. |
| **`group_token`** | Kunci relasi logis antar submission dalam bundle yang sama (nilai unik, disarankan **8 karakter** alfanumerik acak — disepakati di implementasi). |
| **Absensi & QR** | Tetap mengacu pada **submission individu**; tidak ada perubahan konsep utama alur scan. |

#### 4.3.3 Konfigurasi form

1. **`registration_mode`** pada form (mis. disimpan di `forms.metadata` atau kolom eksplisit — disepakati migrasi): nilai **`single`** (individu) atau **`bundle`** (pendaftaran kelompok).
2. **`max_team_size`** pada form: **jumlah maksimum anggota** (termasuk atau tidak termasuk leader — **disepakati eksplisit di implementasi** dan didokumentasikan di UI admin) dalam satu bundle.
3. **Metadata field — `duplicatable`** pada **`form_fields.metadata`** (bukan konfigurasi global di tabel `forms` semata): contoh `{"duplicatable": true}`. Field yang `duplicatable: true` **diulang / ditambahkan di UI** untuk setiap anggota tambahan setelah leader mengisi data utama; field tanpa flag tersebut dianggap **data grup / induk** (hanya sekali).
   - Perilaku **`is_append`** (§4.2) tetap bisa dipakai bersamaan untuk batasan **suntingan anggota** saat konfirmasi, selama tidak konflik dengan desain teknis.

#### 4.3.4 Alur sistem (ringkas)

1. **Admin** membuat form, menyetel `registration_mode = bundle`, `max_team_size`, dan menandai field `duplicatable` lewat metadata field di builder.
2. **Leader** mengisi data utama, memakai aksi **“Tambah peserta”**; sistem menampilkan blok field yang **`duplicatable: true`** untuk setiap slot anggota.
3. **Leader** mengisi data per anggota (nama, email wajib akun sistem, dsb.). Validasi: **email harus terdaftar di sistem**, jumlah anggota **tidak melebihi `max_team_size`** (sesuai aturan yang disepakati apakah leader dihitung atau tidak).
4. **Saat submit:** dalam transaksi terkontrol, sistem **membuat satu `form_answer` untuk leader** dan **satu `form_answer` per anggota**, menetapkan **`group_token` yang sama**, peran **`registration_role`** (`leader` | `member`), status konfirmasi anggota, mengirim **email undangan**, dan field lain seperti pada §4.3.5.

#### 4.3.5 Kolom tambahan pada `form_answers` (target skema)

| Field | Tujuan |
|-------|--------|
| **`group_token`** | Menghubungkan submission dalam satu bundle (unik per grup; panjang/format disepakati). |
| **`registration_role`** | `leader` atau `member`. |
| **`member_confirmation_status`** | `pending`, `accepted`, `rejected`, `expired` — untuk anggota yang diundang; leader mengikuti aturan bisnis yang disepakati. |
| **`invited_email`** | Email undangan (terpisah dari blob JSON `answers` untuk audit dan konsistensi). |
| **`member_confirmed_at`** | Waktu anggota menyelesaikan konfirmasi (menerima). |
| **`invitation_expired_at`** | Batas waktu undangan aktif; **kedaluwarsa tidak memakai scheduler** — dicek **saat anggota membuka tautan konfirmasi**. |

Catatan: implementasi dapat menyesuaikan penamaan dengan kolom §4.2 yang sudah ada (mis. menyelaraskan `status_confirmation_member` dengan `member_confirmation_status`) melalui **satu sumber kebenaran** di migrasi — disepakati di **M4c** pada [milestone.md](milestone.md).

#### 4.3.6 Alur konfirmasi anggota (`/confirm/{token}` atau setara)

1. Sistem mengirim email undangan berisi tautan verifikasi/konfirmasi.
2. Saat anggota membuka tautan, sistem mengecek **`invitation_expired_at`**: jika lewat waktu, perbarui status ke **`expired`**, tolak akses (mis. HTTP 403) **tanpa cron**.
3. Anggota hanya dapat **melihat dan mengubah data miliknya** (sesuai izin field), lalu **menerima atau menolak** undangan.
4. **Menerima:** `member_confirmation_status = accepted`, `member_confirmed_at = now()` (set zona waktu aplikasi).
5. **Menolak:** `member_confirmation_status = rejected` (data dapat disembunyikan dari alur aktif sesuai kebijakan produk).

#### 4.3.7 Review admin

Admin **hanya** meninjau / menyetujui / menolak submission yang **`member_confirmation_status == accepted`** (untuk baris anggota; leader mengikuti aturan yang sama atau disederhanakan sesuai keputusan tim). Hal ini menjaga konsistensi dengan alur approval yang ada **tanpa mengganti pola utama** `review_status` per `form_answer`.

#### 4.3.8 Hasil akhir produk

Setelah fitur ini tersedia, sistem mendukung:

- **Pendaftaran individu** dan **bundle** (bergantung `registration_mode`).
- **Undangan & konfirmasi anggota** dengan kedaluwarsa berbasis cek saat akses.
- **Validasi anggota** (email terdaftar, batas ukuran tim).
- **Absensi & QR per individu** tanpa mengubah inti alur scan.
- **Duplikasi field dinamis** lewat metadata `duplicatable`.
- **Approval per peserta** tetap pada model yang ada.

---

### 4.4 Modul OpenRecruitment (OpRec)

Modul terpisah untuk **recruitment anggota DOSCOM** end-to-end: pendaftaran applicant tanpa login (`/open-recruitment`), screening, interview, attendance & antrean, penilaian interviewer, seleksi akhir, dan feedback. Dibangun di atas platform D-Form v2 dengan domain data sendiri (`recruitment_*`), reuse infrastruktur email, QR, dan file storage.

| Dokumen | Path |
|---------|------|
| PRD produk OpRec | [module/PRD — DOSCOM OpenRecruitment (OpRec).md](module/PRD%20—%20DOSCOM%20OpenRecruitment%20(OpRec).md) |
| Dokumentasi teknis & milestone | [module/oprec/README.md](module/oprec/README.md) |

### 4.5 Fase lanjutan (di luar MVP inti)

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
| `forms` | Form pendaftaran terikat **`event_id`**; judul, deskripsi, **`visible_for`** (JSON), **`closed_at`**, **`banner_url`** (longText, nullable), **`banner_caption`** (nullable), **`metadata`** JSON (nullable) untuk **`registration_mode`** (`single` \| `bundle`, dan varian legacy mode tim), **`max_team_size`** / **`team_size`**, dan konfigurasi terkait; soft delete. |
| `form_fields` | Definisi field per **`form_id`**: **`input_type`** (enum/string sesuai driver: `input`, `selectInput`, `textarea`, `datePicker`, `fileUpload`, `radio`, `checkbox`), label, nama internal, **`metadata`** JSON (**opsi, validasi, `duplicatable` untuk bundle**, dsb.), **`is_append`** (boolean: field boleh disunting anggota saat validasi undangan), **`order`**; soft delete. |
| `form_answers` | Satu baris **submission** per **peserta**; jawaban **`answers`** JSON; foreign key ke `users` dan `forms`; untuk tim/bundle: relasi antar baris lewat **`group_token`**, **`registration_role`**, **`member_confirmation_status`**, **`invited_email`**, **`member_confirmed_at`**, **`invitation_expired_at`** (dan/atau kolom §4.2 seperti `leader_form_answer_id` / `status_confirmation_member` yang diselaraskan di migrasi); constraint unik `(user_id, form_id)` dapat memerlukan peninjauan khusus untuk bundle — **disepakati di implementasi** agar satu akun tidak bentrok antar peran dalam satu form. Satu submit bundle menciptakan banyak baris dalam satu transaksi. |
| `email_logs` | Audit pengiriman email: `form_answer_id` (nullable, `nullOnDelete`), `event_id`, `user_id`, `recipient_email`, `status`, `error_message`, `sent_at`; indeks pada `form_answer_id` dan `(event_id, status)`. |

**Infra Laravel (bukan domain bisnis inti, tetapi ada di migrasi):** `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `sessions`, `password_reset_tokens`.

---

## 8. Kriteria sukses (definisi “selesai” tingkat produk)

- Alur **Admin**: buat event → bangun form → buka pendaftaran → lihat peserta → scan QR → lihat statistik kehadiran berjalan tanpa error kritikal pada jalur utama.
- Alur **Member**: daftar → isi form dinamis → terima email dengan QR → hadir dan tercatat absensi; **mode tim:** anggota yang diundang membuka halaman konfirmasi, menyesuaikan data (field `is_append`), lalu memvalidasi sebelum mengikuti alur berikutnya; **mode bundle (§4.3):** leader menambah anggota dengan field `duplicatable`, sistem membuat banyak `form_answer` dengan `group_token` sama; anggota menyelesaikan konfirmasi undangan sebelum submission memenuhi syarat review admin.
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
- [OpRec — dokumentasi modul & milestone](module/oprec/README.md)
- [README proyek](../README.md)
- Skema basis data: folder migrasi Laravel [`../database/migrations/`](../database/migrations/)
- Project Brief D-Form v2 (PDF)
