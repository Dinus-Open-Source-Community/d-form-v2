# Project Folder Structure

Dokumen ini menjelaskan struktur direktori utama D-Form beserta tanggung jawab masing-masing bagian. Tujuannya adalah membantu developer memahami lokasi kode, konfigurasi, aset, dokumentasi, dan pengujian sebelum mulai melakukan perubahan.

## 1. Root Directory

Berikut gambaran direktori utama repository:

```text
D-Form/
├── app/
├── bootstrap/
├── config/
├── database/
├── docker/
├── docs/
├── lang/
├── public/
├── resources/
├── routes/
├── scripts/
├── storage/
├── tests/
├── .github/
├── Makefile
├── docker.mk
├── docker-compose.yml
├── docker-compose.prod.yml
├── composer.json
├── package.json
├── phpunit.xml
├── playwright.config.ts
└── README.md
```

> Struktur di atas merupakan ringkasan direktori utama. Beberapa file dan direktori konfigurasi lainnya tidak ditampilkan agar lebih mudah dibaca.

## 2. Backend Structure (`app/`)

Direktori `app/` berisi kode utama aplikasi Laravel, termasuk controller, model, service, job, middleware, dan komponen backend lainnya.

```text
app/
├── Auth/
├── Console/
│   └── Commands/
├── Enums/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Mail/
├── Models/
├── Notifications/
├── Observers/
├── Policies/
├── Providers/
├── Rules/
├── Services/
└── Support/
```

### Directory Responsibilities

| Directory           | Responsibility                                                               |
| ------------------- | ---------------------------------------------------------------------------- |
| `Auth/`             | Implementasi komponen autentikasi khusus.                                    |
| `Console/Commands/` | Custom Artisan Commands untuk kebutuhan operasional dan pemrosesan aplikasi. |
| `Enums/`            | Enum untuk status, tipe, dan nilai tetap yang digunakan aplikasi.            |
| `Exceptions/`       | Custom exception dan penanganan kondisi error khusus.                        |
| `Http/Controllers/` | Controller untuk menangani HTTP request dan mengatur alur aplikasi.          |
| `Http/Middleware/`  | Middleware untuk autentikasi, otorisasi, keamanan, dan pemrosesan request.   |
| `Http/Requests/`    | Form Request untuk validasi dan otorisasi input.                             |
| `Http/Resources/`   | Resource untuk membentuk representasi data response.                         |
| `Jobs/`             | Background jobs yang diproses melalui queue.                                 |
| `Mail/`             | Kelas email yang digunakan aplikasi.                                         |
| `Models/`           | Eloquent models dan representasi entitas database.                           |
| `Notifications/`    | Implementasi notifikasi Laravel.                                             |
| `Observers/`        | Observer untuk menangani event pada model.                                   |
| `Policies/`         | Policy untuk mengatur otorisasi terhadap resource.                           |
| `Providers/`        | Service providers untuk registrasi dan konfigurasi layanan aplikasi.         |
| `Rules/`            | Custom validation rules.                                                     |
| `Services/`         | Business logic dan layanan aplikasi yang dikelompokkan berdasarkan domain.   |
| `Support/`          | Utility, helper, dan komponen pendukung lintas domain.                       |

### Domain-based Organization

Beberapa direktori backend menggunakan pengelompokan berdasarkan domain atau fitur.

Contohnya:

- `app/Services/Auth/` untuk layanan autentikasi.
- `app/Services/Events/` untuk layanan pengelolaan event.
- `app/Services/Form/` untuk layanan form dan pengelolaan jawaban.
- `app/Services/Recruitment/` untuk proses open recruitment.
- `app/Services/Registration/` untuk proses registrasi.
- `app/Services/Broadcasting/` untuk layanan email broadcasting.

Pengelompokan serupa juga digunakan pada `Controllers`, `Requests`, `Models`, `Jobs`, dan `Policies`.

## 3. Frontend Structure (`resources/`)

Direktori `resources/` menyimpan source asset frontend yang digunakan oleh aplikasi.

```text
resources/
├── css/
├── fonts/
└── js/
    ├── __tests__/
    ├── actions/
    └── ...
```

### `resources/css/`

Berisi stylesheet utama dan stylesheet khusus aplikasi.

File yang tersedia antara lain:

- `app.css`
- `dform-rich-text.css`

### `resources/fonts/`

Menyimpan font lokal yang digunakan aplikasi, termasuk font Poppins.

### `resources/js/`

Berisi source code frontend berbasis Vue 3, Inertia.js, dan TypeScript.

Direktori ini juga memiliki beberapa area khusus, seperti:

- `__tests__/` — pengujian unit dan pengujian batas struktur frontend.
- `actions/` — kumpulan action dan helper yang dihasilkan Wayfinder untuk integrasi route/controller backend dengan frontend.

File dan direktori frontend lainnya mengikuti struktur yang tersedia di repository. Untuk aturan penempatan komponen, import, dan batas antar-folder, lihat [Front-end Structure Rules](rules/front-end-structure.md).

> **Catatan:** Direktori `resources/js/routes`, `resources/js/actions`, dan `resources/js/wayfinder` merupakan artefak Wayfinder yang dihasilkan secara otomatis dan tidak disimpan sebagai source file biasa di Git. Ikuti dokumentasi [Frontend Quality Gates](05-frontend-quality-gates.md) untuk proses generasinya.

## 4. Routing (`routes/`)

Direktori `routes/` menyimpan definisi route aplikasi.

```text
routes/
├── ai.php
├── console.php
├── web.php
└── web/
    ├── admin/
    │   ├── broadcasting.php
    │   ├── du.php
    │   ├── event.php
    │   ├── index.php
    │   ├── recruitment.php
    │   └── users.php
    ├── auth.php
    ├── index.php
    ├── oprec.php
    └── secret.php
```

Route web dipecah ke beberapa file berdasarkan kelompok fitur agar definisinya lebih terorganisasi.

Beberapa kelompok route yang tersedia:

- `admin/` — route untuk dashboard dan fitur administrasi.
- `auth.php` — route autentikasi.
- `oprec.php` — route open recruitment.
- `index.php` — route halaman utama.
- `secret.php` — route untuk area khusus.

## 5. Database (`database/`)

Direktori `database/` menyimpan struktur dan data awal database.

```text
database/
├── factories/
├── migrations/
└── seeders/
```

| Directory     | Responsibility                                                                  |
| ------------- | ------------------------------------------------------------------------------- |
| `factories/`  | Factory untuk membuat data model, terutama untuk kebutuhan testing dan seeding. |
| `migrations/` | Definisi perubahan struktur database.                                           |
| `seeders/`    | Seeder untuk mengisi data awal atau data pendukung aplikasi.                    |

Factory dan seeder tertentu juga dikelompokkan berdasarkan domain, misalnya recruitment.

## 6. Configuration (`config/` dan `bootstrap/`)

### `config/`

Berisi konfigurasi aplikasi Laravel, termasuk:

- `app.php` — konfigurasi aplikasi.
- `auth.php` — konfigurasi autentikasi.
- `database.php` — konfigurasi database.
- `filesystems.php` — konfigurasi filesystem.
- `mail.php` — konfigurasi email.
- `octane.php` — konfigurasi Laravel Octane.
- `queue.php` — konfigurasi queue.
- `session.php` — konfigurasi session.
- `security.php` — konfigurasi keamanan aplikasi.

Terdapat pula konfigurasi khusus untuk fitur seperti events, recruitment, registration, scan, dan broadcasting.

### `bootstrap/`

Berisi bootstrap aplikasi Laravel, registrasi provider, cache bootstrap, serta aset yang berkaitan dengan SSR.

## 7. Public Assets (`public/`)

Direktori `public/` berisi file yang dapat diakses sebagai aset publik aplikasi.

```text
public/
├── build/
│   └── assets/
├── file-types/
├── illustrations/
├── images/
├── lotties/
├── index.php
└── storage/
```

| Directory / File | Responsibility                                                |
| ---------------- | ------------------------------------------------------------- |
| `build/`         | Hasil build frontend, termasuk JavaScript, CSS, dan manifest. |
| `file-types/`    | Ikon berdasarkan tipe file.                                   |
| `illustrations/` | Ilustrasi yang digunakan pada antarmuka aplikasi.             |
| `images/`        | Gambar dan logo aplikasi.                                     |
| `lotties/`       | Aset animasi Lottie.                                          |
| `index.php`      | Entry point aplikasi Laravel.                                 |
| `storage/`       | Symbolic link menuju `storage/app/public`.                    |

Source frontend berada di `resources/`, sedangkan hasil build yang disajikan kepada browser berada di `public/build/`.

## 8. Storage (`storage/`)

Direktori `storage/` digunakan untuk menyimpan file dan data runtime aplikasi.

```text
storage/
├── app/
│   ├── private/
│   └── public/
├── framework/
└── logs/
```

- `app/private/` — penyimpanan file yang tidak ditujukan untuk akses publik secara langsung.
- `app/public/` — penyimpanan file publik, seperti avatar, banner event, dan upload form.
- `framework/` — file runtime yang digunakan oleh framework Laravel.
- `logs/` — log aplikasi dan informasi runtime terkait.

Jangan menyimpan file sensitif pada direktori publik.

## 9. Documentation (`docs/`)

Direktori `docs/` menyimpan dokumentasi teknis dan dokumentasi pengembangan proyek.

```text
docs/
├── developer/
├── integrations/
└── rules/
```

| Directory       | Responsibility                                                                                    |
| --------------- | ------------------------------------------------------------------------------------------------- |
| `developer/`    | Catatan pengembangan, perubahan fitur, requirement, dan dokumentasi teknis berdasarkan developer. |
| `integrations/` | Dokumentasi integrasi dengan layanan eksternal.                                                   |
| `rules/`        | Aturan dan pedoman pengembangan frontend, backend, serta aturan umum.                             |

Dokumentasi operasional dan panduan developer yang berada langsung di `docs/` dapat digunakan sebagai referensi untuk menjalankan dan mengembangkan aplikasi.

## 10. Testing (`tests/`)

Direktori `tests/` berisi pengujian backend dan frontend end-to-end.

```text
tests/
├── Feature/
├── Fixtures/
├── Support/
├── Unit/
├── e2e/
└── TestCase.php
```

| Directory      | Responsibility                                                              |
| -------------- | --------------------------------------------------------------------------- |
| `Feature/`     | Feature tests untuk menguji perilaku aplikasi dan integrasi antar-komponen. |
| `Fixtures/`    | Data fixture yang digunakan dalam pengujian.                                |
| `Support/`     | Helper dan komponen pendukung test.                                         |
| `Unit/`        | Unit tests untuk menguji komponen secara terisolasi.                        |
| `e2e/`         | End-to-end tests menggunakan Playwright.                                    |
| `TestCase.php` | Base test case untuk pengujian Laravel.                                     |

Pengujian frontend unit juga tersedia di `resources/js/__tests__/`.

## 11. Scripts (`scripts/`)

Direktori `scripts/` berisi script pendukung proses development dan build.

```text
scripts/
├── build/
├── wayfinder/
├── check-period-phase.ts
├── frontendFolderBoundaries.js
└── libRuntimePackageRule.js
```

- `build/` — script pemeriksaan hasil build, termasuk bundle budget.
- `wayfinder/` — script untuk menghasilkan artefak frontend Wayfinder.
- `frontendFolderBoundaries.js` — pemeriksaan batas struktur folder frontend.
- `libRuntimePackageRule.js` — aturan pendukung pemeriksaan package runtime.

## 12. Docker & Deployment (`docker/`)

Direktori `docker/` berisi konfigurasi dan file pendukung container.

```text
docker/
├── caddy/
├── php/
├── Dockerfile.dev
├── Dockerfile.prod
├── entrypoint.sh
├── entrypoint.prod.sh
└── WINDOWS_SETUP.md
```

File terkait container lainnya berada di root repository:

- `docker-compose.yml`
- `docker-compose.prod.yml`
- `docker.mk`
- `Makefile`

Gunakan [Installation Guide](01-installations.md) untuk mengetahui cara menjalankan lingkungan development.

---

## 13. Quick Reference

Tabel berikut dapat digunakan sebagai referensi cepat ketika mencari lokasi file berdasarkan kebutuhan.

| Jika ingin mengubah...  | Lokasi                    |
| ----------------------- | ------------------------- |
| HTTP Controller         | `app/Http/Controllers/`   |
| Validasi request        | `app/Http/Requests/`      |
| Database Model          | `app/Models/`             |
| Business Logic          | `app/Services/`           |
| Background Job          | `app/Jobs/`               |
| Email                   | `app/Mail/`               |
| Middleware              | `app/Http/Middleware/`    |
| Authorization Policy    | `app/Policies/`           |
| Artisan Command         | `app/Console/Commands/`   |
| Database Migration      | `database/migrations/`    |
| Database Seeder         | `database/seeders/`       |
| Frontend Source         | `resources/js/`           |
| Stylesheet              | `resources/css/`          |
| Route Definition        | `routes/`                 |
| Public Images & Assets  | `public/`                 |
| Private Uploaded Files  | `storage/app/private/`    |
| Public Uploaded Files   | `storage/app/public/`     |
| Backend Feature Test    | `tests/Feature/`          |
| Backend Unit Test       | `tests/Unit/`             |
| End-to-End Test         | `tests/e2e/`              |
| Frontend Unit Test      | `resources/js/__tests__/` |
| Build & Utility Scripts | `scripts/`                |
| Project Documentation   | `docs/`                   |
| Docker Configuration    | `docker/`                 |
