# Sapto Changes — 4 Mei 2026

Dokumentasi perubahan menyeluruh pada sesi **konsolidasi Milestone M1–M4**, **penyatuan jalur pendaftaran**, **publik event dari database**, **UI review submission (stub)**, dan **penghapusan Livewire + Filament** dari dependensi aplikasi. Tujuan dokumen: tim bisa melakukan `composer update`, QA, dan melanjutkan pekerjaan backend yang masih tertulis di [`docs/milestone-m1-m4-remaining.md`](../milestone-m1-m4-remaining.md).

---

## Ringkasan (TL;DR)

| Area | Perubahan utama |
|------|-----------------|
| **Auth** | Redirect member ke `/dashboard/user/events` setelah login/register/OAuth; toast OAuth lewat Inertia flash. |
| **Dashboard home** | Data ringkasan untuk non-admin memakai **hanya event published**. |
| **Publik `/events`** | Controller mengirim `events` nyata; `Event.vue`, `EventDetail.vue`, `EventList`, `EventHighlight` konsumsi props. |
| **Pendaftaran** | Rute register user → **redirect** ke halaman fill resmi; `FormFill` mengirim banner form; hapus halaman `EventRegister.vue`. |
| **Submissions admin** | Tombol Accept/Reject + toast “backend belum ada”. |
| **Stack** | Hapus `livewire/*`, `filament/*`, folder `app/Livewire`, view Livewire, layout Blade terkait, `FilamentServiceProvider`; enum tidak lagi bergantung interface Filament. |
| **Docs** | Baru: `docs/milestone-m1-m4-remaining.md`, `docs/milestone-m1-m4-post-implementation-analysis.md`. |

---

## Backend (PHP)

| File | Perubahan |
|------|-----------|
| [`composer.json`](../../composer.json) | Hapus paket Livewire, Filament, Volt; hapus `@php artisan filament:upgrade` dari `post-autoload-dump`. |
| [`bootstrap/providers.php`](../../bootstrap/providers.php) | Hapus registrasi `FilamentServiceProvider`. |
| [`app/Http/Controllers/Auth/LoginController.php`](../../app/Http/Controllers/Auth/LoginController.php) | Redirect intended default berdasarkan role. |
| [`app/Http/Controllers/Auth/RegisterController.php`](../../app/Http/Controllers/Auth/RegisterController.php) | Redirect sukses ke `dashboard.user.events`. |
| [`app/Http/Controllers/Auth/OAuthController.php`](../../app/Http/Controllers/Auth/OAuthController.php) | Ganti `Filament\Notifications\Notification` dengan `Inertia::flash`; assign role `member` untuk user Google baru; redirect sama seperti login. |
| [`app/Http/Controllers/Dashboard/HomeController.php`](../../app/Http/Controllers/Dashboard/HomeController.php) | Branch `events.list` untuk scope data. |
| [`app/Http/Controllers/EventsController.php`](../../app/Http/Controllers/EventsController.php) | Query `EventStatus::Published` + `eventToInertiaArray`. |
| [`app/Http/Controllers/Dashboard/Events/Forms/FormFillController.php`](../../app/Http/Controllers/Dashboard/Events/Forms/FormFillController.php) | Tambah `banner_url`, `banner_caption` pada payload `form`. |
| [`routes/web/admin/index.php`](../../routes/web/admin/index.php) | Register: redirect ke fill atau kembali ke detail + toast; perbaiki `registrationStatus` jadi `submitted`. |
| [`app/Enums/EventCategory.php`](../../app/Enums/EventCategory.php) (dan Session, Status, FormVisibility) | Lepas `implements HasLabel` Filament. |

### Dihapus

- Seluruh [`app/Livewire/`](../../app/Livewire/) (folder).
- [`app/Providers/FilamentServiceProvider.php`](../../app/Providers/FilamentServiceProvider.php).
- [`app/Http/Controllers/Auth/PageController.php`](../../app/Http/Controllers/Auth/PageController.php).
- Blade: `resources/views/livewire/**`, `resources/views/pages/dashboard/**`, `resources/views/pages/auth.blade.php`, `resources/views/pages/home.blade.php`, `resources/views/layouts/{app,auth,dashboard}.blade.php`.
- Konfig: `config/livewire.php`, `config/filament.php`.

---

## Frontend (Vue / TS)

| File | Perubahan |
|------|-----------|
| [`resources/js/pages/Event.vue`](../../resources/js/pages/Event.vue) | `defineProps<{ events: IEvent[] }>`; pass ke highlight + list. |
| [`resources/js/pages/EventDetail.vue`](../../resources/js/pages/EventDetail.vue) | Props `event: IEvent` dari server; label status registrasi; hapus cabang dummy “not found” (404 dari server). |
| [`resources/js/components/modules/landing/events/EventList.vue`](../../resources/js/components/modules/landing/events/EventList.vue) | Data dari props `events`. |
| [`resources/js/components/modules/landing/events/EventHighlight.vue`](../../resources/js/components/modules/landing/events/EventHighlight.vue) | Highlight dari event pertama di props. |
| [`resources/js/pages/Dashboard/Events/Forms/Submissions.vue`](../../resources/js/pages/Dashboard/Events/Forms/Submissions.vue) | Tombol Accept/Reject + `vue-sonner` toast stub. |
| [`resources/js/pages/Dashboard/User/EventDetail.vue`](../../resources/js/pages/Dashboard/User/EventDetail.vue) | Props `registrationStatus` termasuk `submitted`; salin teks badge. |
| [`resources/js/lib/dummyData.ts`](../../resources/js/lib/dummyData.ts) | `statusColorMap.submitted`. |
| [`resources/js/app.js`](../../resources/js/app.js) | Bersihkan komentar Livewire lama. |

### Dihapus

- [`resources/js/pages/Dashboard/User/EventRegister.vue`](../../resources/js/pages/Dashboard/User/EventRegister.vue) — diganti redirect + `Fill.vue`.

---

## Yang harus dijalankan developer setelah pull

1. **`composer update`** (wajib — lockfile akan berubah; penghapusan paket tidak lengkap tanpa ini).
2. **`php artisan optimize:clear`**
3. **`php artisan test`**
4. **`npm ci` atau `npm install`** lalu **`npm run build`**

> Catatan: di lingkungan agen CI lokal, perintah `composer` / `php` mungkin tidak tersedia; validasi suite tidak dijalankan dari sini.

---

## Perbandingan dengan [`saptoChanges19-04-2026.md`](saptoChanges19-04-2026.md)

| Aspek | 19 Apr 2026 | 4 Mei 2026 (sesi ini) |
|--------|-------------|------------------------|
| Fokus | Dashboard admin, seed, Docker | Konsolidasi M1–M4 + hapus Livewire/Filament |
| Data landing event | Banyak mock / placeholder | List & detail publik dari DB (`published`) |
| Jalur daftar member | `EventRegister.vue` terpisah | Redirect ke `Fill.vue` + guard backend |
| Stack | Livewire + Filament untuk modul lama | Hapus dari repo agar tidak bentrok dengan Inertia |

---

## Referensi cepat

- [`docs/milestone-m1-m4-remaining.md`](../milestone-m1-m4-remaining.md) — gap BE + diagram alur.
- [`docs/milestone-m1-m4-post-implementation-analysis.md`](../milestone-m1-m4-post-implementation-analysis.md) — checklist implementasi vs milestone.
- [`docs/milestone.md`](../milestone.md) — sumber acuan fase.

---

*Dokumen ini ditulis untuk kolaborasi tim; sumber kebenaran akhir tetap diff Git.*
