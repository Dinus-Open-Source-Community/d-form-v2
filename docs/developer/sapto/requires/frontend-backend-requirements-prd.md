# Frontend ↔ Backend Requirements PRD (gap analysis)

**Versi dokumen:** 1.0  
**Tanggal:** 19 April 2026  
**Status:** Proposal implementasi — kontrak data bersifat **rekomendasi** sampai disepakati tim backend.

---

## 1. Metadata & audiens

| Item | Nilai |
|------|--------|
| **Audiens** | Developer frontend (Vue/Inertia), backend (Laravel), QA |
| **Tujuan** | Menjabarkan secara eksplisit **celah** antara UI yang sudah ada dan lapisan controller/service/API yang belum memadai, agar backlog terarah |
| **Asumsi teknis** | Halaman utama aplikasi memakai **Inertia** (`Inertia::render`) dengan props; endpoint JSON tambahan boleh dipakai untuk polling, upload bertahap, atau mobile client masa depan |
| **Bukan tujuan dokumen ini** | Mengganti [docs/prd.md](../../../prd.md); dokumen ini adalah **pelengkap** berbasis audit kode |

**Referensi produk tingkat tinggi:** [docs/prd.md](../../../prd.md) (MVP: form dinamis, submission, email+QR, absensi).

---

## 2. Ringkasan eksekutif

Antarmuka Vue di `resources/js/pages` dan komponen terkait sudah mencakup **banyak** alur admin dan member (dashboard, list event, form builder UI, registrasi, scan QR, profil). Namun sebagian besar halaman masih **bergantung pada data statis** (`resources/js/lib/dummyData.ts`, array di komponen landing, atau simulasi klien), atau route backend hanya me-render halaman **tanpa props** yang cukup. Dokumen ini merinci **apa** yang harus dibangun di sisi Laravel (route, controller, service, validasi, model/migrasi jika belum ada) agar UI dapat diproduksi tanpa dummy, selaras MVP di PRD utama.

---

## 3. Matriks gap (ikhtisar)

Legenda: **UI** = tersedia / sebagian / placeholder | **BE** = belum / sebagian / ada | **P0** = blokir MVP produk | **P1** = penting setelah inti jalan | **P2** = peningkatan / nice-to-have

| Modul | Halaman / file utama | UI | BE | P | Catatan singkat |
|-------|----------------------|----|----|---|-----------------|
| Publik — list event | `pages/Event.vue`, `components/.../EventList.vue` | Ada | Belum | P1 | List hardcoded di komponen; perlu query event published |
| Publik — detail event | `pages/EventDetail.vue`, `EventsController@show` | Ada | Belum | P1 | Hanya `eventId` dari route; data dari `dummyEvents` |
| Dashboard admin | `pages/Dashboard/Index.vue`, `HomeController` | Sebagian | Sebagian | P1 | Stats + recent events OK; chart/KPI trend/kalender pakai dummy |
| Dashboard admin — events | `pages/Dashboard/Events/Index.vue`, `EventController@index` | Ada | Ada | — | Terhubung; cache perlu strategi busting saat submission |
| Form builder | `pages/Dashboard/Events/Forms/Index.vue`, `Forms/Create.vue` | Ada | Belum | P0 | Route Inertia tanpa props; `FormController` ada tapi Blade |
| Pendaftaran member | `pages/Dashboard/User/EventRegister.vue` | Ada | Sebagian | P0 | `forms` dari DB; **fields** masih dummy |
| Member — browse events | `pages/Dashboard/User/Events.vue` | Ada | Sebagian | P1 | List OK; tab **Joined** TODO — perlu data submission |
| Member — dashboard | `pages/Dashboard/Index.vue` (role user) | Ada | Belum | P1 | KPI & list upcoming banyak statis/dummy |
| Registrants admin | `pages/Dashboard/Events/Registrants.vue` | Ada | Belum | P0 | `dummyRegistrants` |
| QR scan | `pages/Dashboard/Events/Scan.vue` | Ada | Belum | P0 | Simulasi random di klien |
| Profil | `pages/Dashboard/Profile.vue` | Ada | Belum | P1 | Submit hanya toast; tidak ada `PUT`/`PATCH` user |
| Recruitment | `pages/Dashboard/Recruitment/Index.vue` | Placeholder | Belum | P2 | “Coming Soon” — scope produk TBD |
| Orphan page | `pages/Dashboard/User/Index.vue` | Ada | — | P2 | Tidak ter-route; kandidat hapus atau gabung ke `/dashboard` |

---

## 4. Lingkup per domain

### 4.1 Publik — listing event (`GET /events`)

**User story:** Sebagai pengunjung, saya ingin melihat daftar event yang benar-benar dibuka untuk publikasi agar saya bisa menjelajah sebelum login.

**Perilaku UI saat ini**

- `resources/js/pages/Event.vue` merender hero + `EventList`.
- `resources/js/components/modules/landing/events/EventList.vue` memakai **array statis** di script (bukan props, bukan fetch).

**Kebutuhan backend**

- **Controller:** extend `App\Http\Controllers\EventsController@index` (atau service terpisah) untuk memuat koleksi event dengan filter: `status = published`, `deleted_at` null, urutan disepakati (mis. `start_date` ascending).
- **Inertia props (proposal):** `events: EventResource[]` atau array bentuk sama dengan yang dipakai `EventService::eventToInertiaArray` tanpa data internal admin-only jika tidak diperlukan.
- **Query opsional (fase 2):** `?category=`, `?search=` — bisa ditambahkan setelah list dasar.

**Model / tabel:** `events` (sudah ada).

**Validasi & error:** Tidak wajib 422 untuk GET; 404 hanya jika route salah.

**Kriteria penerimaan**

- [ ] Landing `/events` menampilkan data dari database; menghapus array statis dari `EventList` atau mengisinya dari props.
- [ ] Event draft tidak muncul untuk pengunjung non-admin.

**Risiko / pertanyaan terbuka**

- Apakah deskripsi penuh HTML perlu dikirim ke list atau cukup excerpt? (dampak payload)

---

### 4.2 Publik — detail event (`GET /events/{id}`)

**User story:** Sebagai pengunjung, saya ingin detail event konsisten dengan data server (banner, kuota, status registrasi).

**Perilaku UI saat ini**

- `EventsController@show` me-render `EventDetail` dengan `['eventId' => $id]`.
- `resources/js/pages/EventDetail.vue` mencari event di **`dummyEvents`** berdasarkan `eventId`.

**Kebutuhan backend**

- Resolve `Event` by id (UUID); 404 jika tidak ada / tidak published (kebijakan produk: publik hanya published).
- **Inertia props:** `event` sebagai objek yang kompatibel dengan tipe `IEvent` front (minimal: field yang dipakai template + `banner_url`, `registration_status`, `registered_count`, `quota`).

**Model / tabel:** `events`.

**Kriteria penerimaan**

- [ ] Tidak ada import `dummyEvents` pada `EventDetail.vue` untuk jalur produksi.
- [ ] Deep link `/events/{uuid}` menampilkan event yang sama dengan yang di dashboard admin.

---

### 4.3 Dashboard admin — analytics & widget (halaman `/dashboard`)

**User story:** Sebagai admin, saya ingin grafik dan KPI mencerminkan aktivitas nyata, bukan angka contoh.

**Perilaku UI saat ini**

- `app/Http/Controllers/Dashboard/HomeController.php` mengirim `recentEvents` dan `stats` (total, active, registrants, completion rate).
- `resources/js/components/modules/dashboard/RegistrationChart.vue` dan `CategoryChart.vue` memakai **`dummyChartData`**.
- `MiniCalendar.vue`, `EventCalendar.vue` memakai **`dummyEvents`**.
- `KpiCard` di `Dashboard/Index.vue` memakai **angka trend statis** (props seperti `:trend="12"`).

**Kebutuhan backend**

1. **Time series registrasi (proposal):** endpoint atau props tambahan, mis. `registrationSeries: { labels: string[], counts: number[] }` untuk rentang waktu (hari/bulan) dengan agregasi query ke tabel **submission** (setelah ada migrasi — lihat 4.6).
2. **Breakdown kategori:** `categoryBreakdown: { category: string, count: number }[]` (join/count by `events.category`).
3. **Kalender:** props `calendarEvents: { id, title, start_date, end_date, category }[]` untuk bulan yang ditampilkan — atau endpoint `GET /dashboard/calendar-events?month=&year=` dengan batasi jumlah.
4. **KPI trend (opsional P2):** membutuhkan snapshot historis atau perbandingan periode sebelumnya; jika belum ada data historis, FE bisa menyembunyikan trend sampai BE siap.

**Model / tabel:** `events`; nanti `form_submissions` atau setara untuk agregasi registrasi.

**Non-fungsional:** Query agregasi boleh di-cache singkat (mis. 5 menit) dengan key yang jelas.

**Kriteria penerimaan**

- [ ] Minimal satu chart menggambar data dari server; dummy chart bisa dihapus atau dipakai hanya di storybook.
- [ ] Kalender admin menampilkan event dari DB, bukan `dummyEvents`.

---

### 4.4 Dashboard member — ringkasan partisipasi (`/dashboard` untuk non-admin)

**User story:** Sebagai member, saya ingin angka “Events Joined”, “Upcoming”, dll. sesuai data pendaftaran saya.

**Perilaku UI saat ini**

- Di `resources/js/pages/Dashboard/Index.vue`, cabang non-admin memakai KPI **hardcoded** dan `upcomingEvents` turunan dari dummy/filter lokal.

**Kebutuhan backend**

- Props tambahan untuk user biasa, mis. `memberStats: { joinedCount, upcomingCount, pendingCount }` dan `memberUpcomingEvents: IEvent[]` (event yang user sudah daftar + tanggal mendatang — definisi “pending” disepakati).

**Model / tabel:** Tergantung entitas submission (4.6).

**Kriteria penerimaan**

- [ ] KPI member tidak angka magic di template; sumber dari server atau nol eksplisit.

---

### 4.5 Member — tab “Joined” (`/dashboard/user/events`)

**User story:** Sebagai member, saya ingin filter event yang sudah saya daftari.

**Perilaku UI saat ini**

- `resources/js/pages/Dashboard/User/Events.vue`: tab `joined` memfilter **array kosong** dengan komentar `TODO: filter by joined events when backend provides user registration data`.

**Kebutuhan backend**

- Pada load halaman: sertakan flag per event `user_has_submitted: boolean` atau kirim dua koleksi `availableEvents` / `joinedEvents` — pilih satu pola agar FE tidak menebak.

**Kriteria penerimaan**

- [ ] Tab Joined menampilkan hanya event dengan submission untuk `auth()->id()`.

---

### 4.6 Form builder — CRUD form & field (admin)

**User story:** Sebagai admin, saya ingin mengelola form dan field per event dari UI Inertia yang sama tanpa mengisi database manual.

**Perilaku UI saat ini**

- Route `GET /dashboard/events/{event}/forms` dan `/forms/create` di `routes/web/admin/index.php` memanggil `inertia(...)` **tanpa props**.
- `resources/js/pages/Dashboard/Events/Forms/Index.vue` & `Create.vue` memakai **`dummyEvents` / `dummyForms`**.
- Ada `App\Http\Controllers\Dashboard\Events\Forms\FormController` yang mengembalikan **Blade** — **tidak** terhubung ke rute Inertia saat ini.

**Kebutuhan backend**

- Satukan sumber kebenaran: **Inertia** sesuai arsitektur proyek ([docs/prd.md](../../../prd.md) §6.2).
- Implementasi per route (nama disesuaikan konvensi `dashboard.events.forms.*`):
  - `GET` index: props `event` (ringkas), `forms` (list form untuk event tersebut).
  - `GET` create: props `event`, mungkin `defaultClosedAt`.
  - `POST` store: validasi `title`, `description`, `visible_for`, `closed_at`, `event_id`.
  - `GET` edit (jika ada): props form + fields.
  - `PUT/PATCH` update form.
  - **Field:** CRUD atau reorder endpoint — `form_fields` punya `metadata` JSON; pastikan validasi tipe `input_type` sesuai enum migrasi: `textInput`, `selectInput`, `textarea`, `datePicker`, `fileUpload`.

**Model / tabel:** `forms`, `form_fields` — migrasi `2026_03_07_201912_create_forms_table.php`.

**Validasi & error:** 422 dengan struktur key per field; FE sudah punya pola toast untuk event — pola serupa untuk form builder.

**Kriteria penerimaan**

- [ ] Tidak ada ketergantungan pada `dummyForms` untuk operasi normal setelah seed.
- [ ] Policy: hanya admin yang boleh mengubah form untuk event yang mereka kelola (sesuai `EventPolicy`).

---

### 4.7 Pendaftaran — submit jawaban (`/dashboard/user/events/{event}/register`)

**User story:** Sebagai member, saya ingin mengirim pendaftaran yang tersimpan di server dan memicu email konfirmasi sesuai MVP.

**Perilaku UI saat ini**

- Route closure mengirim `event` + `forms` (koleksi `Form` model).
- `EventRegister.vue` memakai **`dummyFormFields`** jika props `fields` tidak ada.
- Submit: `toast.success` tanpa `router.post` ke backend.

**Kebutuhan backend**

- **GET register:** Untuk form yang dipilih (atau form default per event), kirim `fields: FormFieldResource[]` yang sudah diurutkan (kolom order jika ditambahkan migrasi; jika belum, urutkan by `created_at` / id).
- **POST submission:** endpoint `POST .../register` atau `POST /dashboard/user/events/{event}/submissions` dengan:
  - `form_id`
  - `answers` sebagai JSON map `field_id -> value` (string untuk teks; file upload via `multipart` terpisah atau `FormData`).
  - Validasi dinamis berdasarkan `metadata` (required, min, max, options).
- **Migrasi:** PRD menyebut `event_form_submissions`; di repo saat ini **belum** ada tabel `submissions` pada migrasi terlacak — tim backend harus menambah migrasi (mis. `form_submissions`) dengan: `id`, `user_id`, `form_id`, `event_id` (denormalisasi opsional), `payload` JSON, `timestamps`, soft deletes jika perlu, unique constraint `(user_id, form_id)` jika satu user satu kali per form.

**Model / tabel:** `forms`, `form_fields`; **baru:** tabel submission + storage file untuk upload.

**Kriteria penerimaan**

- [ ] Submit menyimpan record; reload halaman menampilkan status terdaftar / tidak bisa submit duplikat sesuai aturan.
- [ ] Job queue mengirim email (lihat PRD utama); QR unik terikat submission.

**Risiko**

- Ukuran upload file dan virus scan policy — definisi di luar dokumen ini, tapi wajib di server.

---

### 4.8 Daftar peserta — admin (`/dashboard/events/{event}/registrants`)

**User story:** Sebagai admin, saya ingin melihat dan mencari peserta yang mendaftar.

**Perilaku UI saat ini**

- `resources/js/pages/Dashboard/Events/Registrants.vue` memakai **`dummyRegistrants`**.

**Kebutuhan backend**

- `GET` Inertia dengan `registrants: Paginator` atau list + meta.
- Query `search`, `sort`, pagination.
- **Export CSV (MVP PRD):** route `GET` download atau `POST` export job — opsi P1 setelah list API.

**Model / tabel:** Submission + join `users`.

**Kriteria penerimaan**

- [ ] Data konsisten dengan submission di 4.7.

---

### 4.9 Absensi QR — scan (`/dashboard/events/{event}/scan`)

**User story:** Sebagai admin, saya ingin memindai QR peserta dan mencatat kehadiran dengan status yang jelas (sukses / sudah pernah / tidak valid).

**Perilaku UI saat ini**

- `Scan.vue` memanggil `simulateScan()` — random di klien; history statis.

**Kebutuhan backend**

- **POST** `/dashboard/events/{event}/scan` atau `/api/...` dengan body: `token` atau `payload` hasil decode QR (format ditetapkan saat generate QR di email).
- Logika: validasi token → submission → cek duplikasi absensi → tulis `event_attendances` (atau nama tabel yang disepakati) dengan `scanned_by_user_id`, `scanned_at`.
- Response JSON: `{ status: 'success' | 'already' | 'invalid', participant?: { name, email } }` agar FE bisa memetakan ke UI yang ada.

**Model / tabel:** Perlu migrasi absensi jika belum ada (PRD §7 menyebut `event_attendances`).

**Non-fungsional:** Rate limiting untuk mencegah brute force token.

**Kriteria penerimaan**

- [ ] Scan tidak mengubah state hanya di klien; history bisa di-refresh dari server atau ditambahkan ke response.

---

### 4.10 Profil & kata sandi (`GET/PUT /dashboard/profile`)

**User story:** Sebagai pengguna login, saya ingin memperbarui nama/email dan password dengan aman.

**Perilaku UI saat ini**

- `resources/js/pages/Dashboard/Profile.vue`: `updateProfile` dan `updatePassword` menampilkan **toast** tanpa request ke Laravel.

**Kebutuhan backend**

- `PUT` atau `PATCH` `profile` untuk `name`, `email` (dengan konfirmasi email ulang jika email berubah — opsional).
- `PUT` `password` dengan `current_password`, `password`, `password_confirmation` — gunakan `Hash::check`, aturan Laravel default.
- Gunakan **Form Request** untuk validasi.

**Model / tabel:** `users`.

**Kriteria penerimaan**

- [ ] Setelah simpan, `auth.user` di Inertia shared props konsisten (bisa redirect atau reload partial).

---

### 4.11 Endpoint pendukung yang sudah ada (referensi)

- `GET /dashboard/events/{event}/registration-status` — JSON untuk polling status registrasi; sudah di `EventController` (lihat dokumentasi Nafan). **Tetap** relevan saat submission mengubah `registered_count` secara atomi di backend (jangan hanya increment di FE).

---

### 4.12 Modul Recruitment (`/dashboard/recruitment`)

**User story:** TBD — UI menampilkan “Coming Soon”.

**Kebutuhan backend:** **Belum didefinisikan.** Dokumen ini menandai **P2 / out-of-scope** hingga keputusan produk. Saat scope jelas, tambahkan entitas dan route baru.

---

### 4.13 Halaman orphan — `Dashboard/User/Index.vue`

**Temuan:** Tidak ada `Inertia::render` yang merujuk ke komponen ini pada rute yang tercatat.

**Rekomendasi:** Hapus file jika tidak dipakai, atau gabungkan dengan `/dashboard` dan pass role untuk menghindari duplikasi layout “My Dashboard”.

---

## 5. Lampiran A — Kontrak data (proposal JSON)

Contoh tidak mengikat; field disesuaikan dengan `EventResource` dan kebutuhan FE.

### A.1 Publik — list event (Inertia `Event`)

```json
{
  "events": [
    {
      "id": "uuid",
      "title": "string",
      "start_date": "2026-04-25",
      "end_date": "2026-04-25",
      "location": "string",
      "banner_url": "https://...",
      "category": "rkt",
      "registration_status": "open",
      "registered_count": 120,
      "quota": 500
    }
  ]
}
```

### A.2 Dashboard — stats + series (proposal)

```json
{
  "stats": {
    "totalEvents": 12,
    "activeEvents": 5,
    "totalRegistrants": 3400,
    "completionRate": 42
  },
  "registrationSeries": {
    "labels": ["2026-01", "2026-02", "2026-03"],
    "counts": [10, 24, 18]
  },
  "categoryBreakdown": [
    { "category": "rkt", "label": "RKT", "count": 4 },
    { "category": "non-rkt", "label": "NON RKT", "count": 3 }
  ]
}
```

### A.3 Member — joined events (proposal)

```json
{
  "events": [ { "id": "uuid", "title": "...", "user_has_submitted": true } ]
}
```

atau

```json
{
  "allEvents": [ ],
  "joinedEventIds": ["uuid1", "uuid2"]
}
```

### A.4 Scan response (proposal)

```json
{
  "status": "success",
  "participant": { "name": "Ahmad", "email": "ahmad@student.dinus.ac.id" },
  "scannedAt": "2026-04-19T09:15:00+07:00"
}
```

```json
{ "status": "already", "participant": { "name": "Ahmad", "email": "..." } }
```

```json
{ "status": "invalid", "message": "Unknown token" }
```

---

## 6. Prioritisasi rilis (rekomendasi backlog)

| Urutan | Fokus | Alasan |
|--------|--------|--------|
| **1** | Migrasi + model **submission**, API **submit** + **fields** di GET register | Menghidupkan jalur inti MVP tanpa dummy |
| **2** | **Form builder** Inertia + CRUD field | Admin harus bisa mendefinisikan form sebelum submission |
| **3** | **Registrants** list + **registered_count** terupdate konsisten | Dashboard admin dan peserta selaras |
| **4** | Publik **list/detail** event dari DB | Konsistensi marketing dan deep link |
| **5** | **QR scan** + tabel absensi | Penutupan loop MVP PRD |
| **6** | **Profile** update | Kualitas akun |
| **7** | Dashboard **analytics** + kalender dari DB | Menghapus dummy chart |
| **8** | **Recruitment** | Setelah scope produk |

---

## 7. Kebutuhan non-fungsional

| Aspek | Requirement |
|-------|-------------|
| **Cache** | `EventService::paginateForAdminIndex` memakai cache; setelah submission/CRUD form/event, **invalidate** tag `events` atau naikkan `events:list:cache:buster` agar list admin tidak basi |
| **Authorization** | Setiap route baru memakai `authorize()` / policy yang konsisten dengan `EventPolicy` |
| **Upload** | Batasi mime & size; simpan di `storage` public/private sesuai kebutuhan URL |
| **Scan** | Rate limit; jangan log token penuh di plaintext |
| **Konsistensi error** | Objek error Laravel validasi agar kompatibel dengan helper toast di FE (field key per field) |

---

## 8. Definisi selesai untuk dokumen ini

Dokumen ini dianggap **terimplementasi** untuk suatu modul ketika: matriks §3 diperbarui (UI + BE = “ada”), `dummyData` untuk modul tersebut dihapus atau dibatasi ke pengembangan lokal, dan kriteria penerimaan per domain tercentang di QA.

---

## 9. Referensi file kode (audit)

| Area | File |
|------|------|
| Controller publik | `app/Http/Controllers/EventsController.php` |
| Dashboard home | `app/Http/Controllers/Dashboard/HomeController.php` |
| Event admin | `app/Http/Controllers/Dashboard/Events/EventController.php` |
| Form (Blade legacy) | `app/Http/Controllers/Dashboard/Events/Forms/FormController.php` |
| Rute dashboard | `routes/web/admin/index.php`, `routes/web/admin/event.php`, `routes/web/index.php` |
| Dummy | `resources/js/lib/dummyData.ts` |
| Toast validasi | `resources/js/lib/eventValidationToast.ts` |

---

*Akhir dokumen.*
