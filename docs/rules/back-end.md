---
author: 'DOSCOM'
project: 'DForm'
version: 2
---

# Aturan dalam Back-End

Dokumen ini berfungsi sebagai standarisasi pengembangan Back-End menggunakan **Laravel 12**, dan **Inertia.js (Server-side)**. Setiap pengembang wajib mengikuti aturan ini demi menjaga keamanan, skalabilitas, dan konsistensi arsitektur kode di dalam ekosistem **DForm**.

## 1. Basic Convention

1.  **PascalCase** untuk Nama File, Class, dan Enum. **camelCase** untuk variabel dan function.
2.  Gunakan identitas file di akhir nama file sebelum ekstensi (selain Model dan Enums). Contoh: `FormController`, `EventObserver`, `UserPolicy`.
3.  Selalu gunakan **Singular** (tunggal) untuk nama file PHP.
4.  Selalu gunakan prefix `create_` + nama table (plural/jamak) + suffix `_table` untuk migration manual. Contoh: `php artisan make:migration create_events_table`.

## 2. Struktur folder yang penting

| path                        | fungsi                                                                                                             |
| :-------------------------- | :----------------------------------------------------------------------------------------------------------------- |
| **app/Enums**               | Berisikan enum yang digunakan sebagai _Single Source of Truth_ untuk data statis di database.                      |
| **app/Http/Controllers**    | Berisikan controller. Gunakan `--resource` untuk CRUD standar dan `--invokable` untuk aksi tunggal yang kompleks.  |
| **app/Http/Middleware**     | Filter untuk HTTP request. Tempat mengecek autentikasi, izin akses, atau manipulasi header.                        |
| **app/Http/Requests**       | Tempat menangkap input browser dan melakukan validasi serta otorisasi sebelum masuk ke Controller.                 |
| **app/Models**              | Representasi tabel database. Tempat mendefinisikan _Relationship_, _Casting_, dan _Mass Assignment_ (`$fillable`). |
| **app/Observers**           | Mendengarkan (_listening_) event Eloquent (created, updated, deleted) untuk memicu logika tambahan otomatis.       |
| **app/Policies**            | Logic otorisasi tingkat lanjut. Menentukan apakah user boleh melakukan aksi tertentu pada model tertentu.          |
| **app/Providers**           | Tempat registrasi servis pihak ketiga, konfigurasi Gate, atau pengaturan komponen UI (seperti Filament).           |
| **bootstrap/app.php**       | _Entry point_ konfigurasi Laravel 12. Tempat mengatur Routing, Middleware global, dan Exception Handling.          |
| **bootstrap/providers.php** | Daftar otomatis Service Provider yang harus dimuat saat aplikasi pertama kali dijalankan.                          |
| **config**                  | Pusat pengaturan framework dan library (Database, Mail, Filesystem, dll).                                          |
| **database/factories**      | Blueprint untuk membuat ribuan data palsu secara otomatis menggunakan library Faker.                               |
| **database/migrations**     | _Version control_ untuk skema database. Mendefinisikan kolom, tipe data, dan index tabel.                          |
| **database/seeders**        | Mengisi database dengan data awal (seperti User Admin default) atau data dummy dari Factory.                       |
| **routes**                  | Definisi URL aplikasi. Gunakan `web.php` untuk browser dan `api.php` untuk integrasi luar.                         |
| **storage**                 | Penyimpanan file lokal. Folder `app/public` wajib di-link ke `public/storage` agar bisa diakses browser.           |

## 3. Cara penamaan file / lain-lain

| jenis      | penamaan            | contoh               |
| :--------- | :------------------ | :------------------- |
| php        | PascalCase          | `EventController`    |
| migration  | snake_case          | `create_users_table` |
| nama table | snake_case (plural) | `users`, `events`    |
| nama route | dot notation        | `events.index`       |

## 4. Routing

1.  Setiap Route wajib memiliki nama unik menggunakan `->name('...')`.
2.  Route wajib di-group berdasarkan fungsionalitas atau middleware yang sama.
3.  `Route::group` disarankan memiliki `prefix` untuk URL dan `as` untuk prefix nama route.
4.  Gunakan `Route::resource` untuk efisiensi CRUD. Batasi method dengan `->only([...])` atau `->except([...])`.
5.  Jika file `web.php` terlalu besar, pisah file ke folder `routes/web/*.php` dan import di file utama.

## 5. Controllers

1.  **Thin Controller:** Controller hanya boleh berisi logika alur (panggil Request, panggil Model/Service, Return View/Redirect).
2.  Jangan melakukan logika bisnis yang rumit (seperti perhitungan matematika atau kueri database yang sangat panjang) langsung di Controller.
3.  Gunakan `Type Hinting` pada method untuk mempermudah pembacaan kode.

## 6. Request dan Validation

1.  Gunakan penamaan: `Action` + `Model` + `Request`. Contoh: `StoreEventRequest`, `UpdateEventRequest`.
2.  **Strict Validation:** Semua data input (`$request->all()`) dilarang keras langsung dimasukkan ke database tanpa divalidasi di file Request.
3.  Gunakan method `authorize()` di dalam file Request untuk authorization yang terkait input dan resource request sebelum validasi berjalan.
4.  Validasi dilakukan di boundary request. Normalisasi dilakukan oleh value object atau input mapper. Jangan membuat satu sanitizer global yang menjadi god function.

## 7. Migrations, Seeders, dan Factories

1.  Table yang sifatnya "detail" (weak entity) boleh digabung di satu file migration dengan parent-nya jika strukturnya sederhana.
2.  Gunakan trait `WithoutModelEvents` saat seeding data besar untuk menghindari konflik dengan Observers atau Spatie Roles.
3.  Selalu panggil semua Seeder melalui `DatabaseSeeder.php`.
4.  Gunakan pengecekan `app()->isProduction()` di seeder agar data dummy tidak sengaja masuk ke server produksi.
5.  Perubahan schema berisiko memakai pola expand → migrate → contract.
6.  Hindari operasi blocking pada tabel besar; pertimbangkan ukuran index, lock, dan execution plan.
7.  Migration tidak boleh bergantung pada data dummy.
8.  Destructive migration membutuhkan backup/rollback plan dan approval eksplisit.

## 8. Enums

1.  Gunakan **Backed Enums** (string atau int) untuk memudahkan penyimpanan ke database.
2.  Gunakan Enum di dalam Model Casting: `protected $casts = ['status' => EventStatus::class];`.

## 9. Models, Observers, dan Policies

1.  **Models:** Gunakan `$fillable` untuk keamanan mass assignment. Selalu definisikan hubungan (Relationship) dengan jelas.
2.  **Observers:** Gunakan untuk side effect domain yang sesuai lifecycle model. Side effect eksternal seperti email, webhook, file deletion, dan dispatch queue yang bergantung pada data committed harus memakai after-commit behavior.
3.  **Policies:** Policy menangani aturan resource/domain. Middleware menangani akses area besar, role, dan feature flag. Controller hanya memanggil authorization yang belum dijalankan; jangan menggandakan pemeriksaan tanpa alasan.

## 10. Providers

1.  Gunakan `AppServiceProvider` untuk konfigurasi global sederhana.
2.  Buat Provider baru jika ada integrasi library besar agar `AppServiceProvider` tidak menjadi "sampah" kode.

## 11. Storage

1.  Gunakan `Storage::disk('public')` untuk file yang bisa diakses umum.
2.  Gunakan `Storage::disk('local')` untuk file sensitif (seperti laporan keuangan).
3.  Wajib menjalankan `php artisan storage:link` di server agar file bisa ditampilkan di browser.
4.  Wajib menjalankan `storage:link` melalui runtime container yang aktif (Docker atau Podman) agar symbolic link merujuk pada filesystem container yang benar, bukan dijalankan melalui PHP host.

Perintah aktual mengikuti runtime environment, misalnya:

```bash
docker compose exec app php artisan storage:link
podman exec -i -w /app d_form_app php artisan storage:link
```

## 12. Exception handling dan HTTP status contract

Error internal tidak boleh dikirim mentah ke user. Response harus memakai status yang sesuai, memiliki public-safe message, dan mencatat context yang aman.

### 1xx — Informational

| Code | Nama | Fungsi |
| --- | --- | --- |
| 100 | Continue | Server menerima header awal dan client dapat melanjutkan request body. |
| 101 | Switching Protocols | Server menyetujui perubahan protokol. |
| 102 | Processing | Request sedang diproses dan belum ada response final. |
| 103 | Early Hints | Server mengirim header awal sebelum response final. |
| 104 | Upload Resumption Supported | Dukungan melanjutkan upload; temporary dan bukan default aplikasi. |
| 105–199 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |

### 2xx — Success

| Code | Nama | Fungsi |
| --- | --- | --- |
| 200 | OK | Request berhasil. |
| 201 | Created | Resource baru berhasil dibuat. |
| 202 | Accepted | Request diterima untuk diproses kemudian. |
| 203 | Non-Authoritative Information | Response berasal dari metadata yang dimodifikasi proxy. |
| 204 | No Content | Berhasil tanpa response body. |
| 205 | Reset Content | Client diminta mereset tampilan atau input. |
| 206 | Partial Content | Response hanya sebagian data. |
| 207 | Multi-Status | Beberapa hasil status dalam satu response WebDAV. |
| 208 | Already Reported | Resource sudah dilaporkan sebelumnya dalam response WebDAV. |
| 209–225 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |
| 226 | IM Used | Instance manipulation berhasil digunakan. |
| 227–299 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |

### 3xx — Redirection

| Code | Nama | Fungsi |
| --- | --- | --- |
| 300 | Multiple Choices | Tersedia beberapa representasi atau resource. |
| 301 | Moved Permanently | Resource berpindah permanen. |
| 302 | Found | Resource sementara tersedia di lokasi lain. |
| 303 | See Other | Client diarahkan mengambil resource pada URI lain. |
| 304 | Not Modified | Resource belum berubah dan cache dapat digunakan. |
| 305 | Use Proxy | Client harus menggunakan proxy; tidak digunakan dalam alur umum aplikasi. |
| 306 | Unused | Tidak digunakan. |
| 307 | Temporary Redirect | Redirect sementara dengan method tetap dipertahankan. |
| 308 | Permanent Redirect | Redirect permanen dengan method tetap dipertahankan. |
| 309–399 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |

### 4xx — Client Error

| Code | Nama | Fungsi |
| --- | --- | --- |
| 400 | Bad Request | Request malformed atau tidak valid. |
| 401 | Unauthorized | Authentication tidak ada atau tidak valid. |
| 402 | Payment Required | Dicadangkan untuk kebutuhan pembayaran. |
| 403 | Forbidden | User teridentifikasi tetapi tidak memiliki izin. |
| 404 | Not Found | Resource tidak ditemukan. |
| 405 | Method Not Allowed | HTTP method tidak didukung endpoint. |
| 406 | Not Acceptable | Format yang diminta client tidak dapat dipenuhi. |
| 407 | Proxy Authentication Required | Proxy membutuhkan authentication. |
| 408 | Request Timeout | Server menunggu request terlalu lama. |
| 409 | Conflict | Request bertentangan dengan state resource saat ini. |
| 410 | Gone | Resource dihapus permanen. |
| 411 | Length Required | Content-Length diperlukan. |
| 412 | Precondition Failed | Precondition tidak terpenuhi. |
| 413 | Content Too Large | Payload terlalu besar. |
| 414 | URI Too Long | URI terlalu panjang. |
| 415 | Unsupported Media Type | Format body tidak didukung. |
| 416 | Range Not Satisfiable | Range request tidak dapat dipenuhi. |
| 417 | Expectation Failed | Expect header tidak dapat dipenuhi. |
| 418 | Unused | Tidak digunakan. |
| 419–420 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |
| 421 | Misdirected Request | Request diarahkan ke server yang tidak sesuai. |
| 422 | Unprocessable Content | Sintaks benar tetapi isi tidak dapat diproses. |
| 423 | Locked | Resource sedang dikunci. |
| 424 | Failed Dependency | Request gagal karena dependency sebelumnya gagal. |
| 425 | Too Early | Server menolak request yang mungkin diulang. |
| 426 | Upgrade Required | Client harus memakai protokol berbeda. |
| 427 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |
| 428 | Precondition Required | Request wajib memiliki precondition. |
| 429 | Too Many Requests | Client terkena rate limit. |
| 430 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |
| 431 | Request Header Fields Too Large | Header request terlalu besar. |
| 432–450 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |
| 451 | Unavailable For Legal Reasons | Resource tidak tersedia karena alasan hukum. |
| 452–499 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |

### 5xx — Server Error

| Code | Nama | Fungsi |
| --- | --- | --- |
| 500 | Internal Server Error | Kegagalan internal tanpa mapping lebih spesifik. |
| 501 | Not Implemented | Server belum mendukung kemampuan yang diperlukan. |
| 502 | Bad Gateway | Gateway menerima response tidak valid dari upstream. |
| 503 | Service Unavailable | Service sementara tidak tersedia atau overload. |
| 504 | Gateway Timeout | Gateway tidak mendapat response tepat waktu dari upstream. |
| 505 | HTTP Version Not Supported | Versi HTTP tidak didukung. |
| 506 | Variant Also Negotiates | Content negotiation mengalami loop. |
| 507 | Insufficient Storage | Server tidak memiliki storage yang cukup. |
| 508 | Loop Detected | Server mendeteksi infinite loop. |
| 509 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |
| 510 | Not Extended | Obsolete; tidak boleh digunakan. |
| 511 | Network Authentication Required | Client harus authentication pada network. |
| 512–599 | Unassigned | Tidak boleh digunakan tanpa standar resmi. |

Nama dan status di atas mengikuti [IANA HTTP Status Code Registry](https://www.iana.org/assignments/http-status-codes). `419` Laravel Page Expired bukan status IANA dan hanya boleh digunakan sebagai framework-specific exception yang terdokumentasi. Kode seperti `499`, `598`, dan `599` tidak boleh digunakan sebagai contract aplikasi tanpa standar resmi.

Untuk setiap endpoint, dokumentasikan status code yang mungkin, bentuk error, field validation, dan apakah response berupa Inertia redirect atau JSON.

## 13. Queue dan job reliability

1.  Job wajib idempotent.
2.  Retry, backoff, timeout, dan failed-job behavior wajib didefinisikan.
3.  Job email tidak boleh mengirim duplikasi ketika retry.
4.  Gunakan unique job atau idempotency key bila diperlukan.
5.  Job ID dan attempt harus dapat diobservasi tanpa mencatat secret.

## 14. Observability

Observability recommended secara umum, tetapi wajib pada sesi development, audit, debugging, dan incident reproduction:

1.  Gunakan request/correlation ID dan structured logging.
2.  Jangan mencatat token, password, file sensitif, atau PII berlebihan.
3.  Error internal memakai public-safe message.
4.  Exception tidak boleh ditelan tanpa logging.
5.  Metric latency, error rate, queue lag, dan failed jobs wajib bila tooling tersedia atau endpoint berisiko tinggi.
