# Analisis pasca-implementasi — Milestone M1–M4 (sesi konsolidasi)

Dokumen ini merangkum **apa yang sudah diimplementasikan** setelah audit gap M1–M4 dan permintaan konsolidasi Livewire → Vue. Ini **bukan** pengganti `docs/milestone.md`; gunakan untuk onboarding singkat setelah merge.

---

## Yang sudah diimplementasikan

### M1 — Autentikasi & peran

- **Redirect pasca-login / OAuth:** user dengan role **member** (tanpa admin/super-admin) diarahkan ke `dashboard.user.events` (lihat [`routes/web/admin/index.php`](../routes/web/admin/index.php)); admin tetap ke `/dashboard` (intended).
- **Redirect pasca-register:** langsung ke daftar event member (`dashboard.user.events`).
- **OAuth:** notifikasi Filament diganti dengan **`Inertia::flash('toast', …)`** agar konsisten dengan login Inertia.

### M2 — Event & publik

- **`EventsController`:** `index` / `show` memuat event **`published`** dari database dan mengirim array `IEvent` ke [`Event.vue`](../resources/js/pages/Event.vue) dan [`EventDetail.vue`](../resources/js/pages/EventDetail.vue) (menghapus ketergantungan dummy untuk data utama).
- **`HomeController`:** jika user **tidak** punya permission `events.list`, statistik dan “recent events” dihitung dari **event published** saja (mengurangi paparan data admin ke member).

### M3 — Form builder

- Tidak mengubah logika builder; tetap lewat rute dashboard yang sudah ada.

### M4 — Pendaftaran

- **Satu jalur fill resmi:** rute `GET /dashboard/user/events/{event}/register` sekarang **redirect** ke `dashboard.events.forms.fill` dengan form pertama (urut `title`). Jika tidak ada form → redirect ke detail event user + toast error.
- **`FormFillController`:** payload `form` untuk halaman fill kini menyertakan **`banner_url`** dan **`banner_caption`** selaras dengan properti form di builder.
- **Props `EventDetail` (user):** `registrationStatus` diset ke **`submitted`** ketika sudah ada baris `form_answers` (bukan lagi field model yang tidak ada).

### Admin — daftar submission

- **[`Submissions.vue`](../resources/js/pages/Dashboard/Events/Forms/Submissions.vue):** tombol **Accept** dan **Reject** pada tabel, kartu, dan panel detail (slide-over). Aksi memanggil **`toast.info`** menjelaskan bahwa **endpoint backend belum ada** (lihat [`docs/milestone-m1-m4-remaining.md`](milestone-m1-m4-remaining.md)).

### Penghapusan Livewire / Filament dari aplikasi

- **Dihapus:** `app/Livewire/`, `resources/views/livewire/`, halaman Blade dashboard/auth lama, layout Blade yang memuat `@livewire` / Filament, `config/livewire.php`, `config/filament.php`, provider Filament (file dihapus + unregister dari [`bootstrap/providers.php`](../bootstrap/providers.php)).
- **Enum:** [`EventCategory`](../app/Enums/EventCategory.php), [`EventSession`](../app/Enums/EventSession.php), [`EventStatus`](../app/Enums/EventStatus.php), [`EventFormVisibility`](../app/Enums/EventFormVisibility.php) tidak lagi `implements HasLabel` dari Filament; method `getLabel()` tetap ada.
- **`composer.json`:** dependensi `livewire/*` dan `filament/*` dihapus; script `filament:upgrade` dihapus dari `post-autoload-dump`.
- **`PageController` Blade auth:** dihapus (dead code).

### Landing — komponen event

- **[`EventList.vue`](../resources/js/components/modules/landing/events/EventList.vue)** dan **[`EventHighlight.vue`](../resources/js/components/modules/landing/events/EventHighlight.vue)** menerima data **`events`** dari server (bukan array statis semata).

---

## Yang belum / tidak diimplementasikan (alasan)

| Item | Alasan |
|------|--------|
| Lupa password Inertia | Tidak ada controller/rute reset di repo — lihat README sisa pekerjaan. |
| Accept/Reject persisten | Model/migrasi `form_answers` belum punya status review atau API. |
| `composer update` di lingkungan CI agen | Binary `composer` / `php` tidak tersedia di sandbox; **wajib dijalankan developer lokal**. |

---

## Risiko / tindak lanjut QA

1. Jalankan **`composer update`** lalu **`php artisan test`** dan smoke login (member vs admin), OAuth, daftar publik `/events`, redirect register → fill, submit form.
2. Pastikan **`npm run build`** lolos; periksa TypeScript untuk halaman yang diubah.
3. Aset **`public/js/filament/*`** mungkin masih ada dari publish lama — bisa dibersihkan manual jika mengganggu (opsional).

---

*Diselaraskan dengan [`docs/milestone.md`](milestone.md) dan catatan gap sebelumnya.*
