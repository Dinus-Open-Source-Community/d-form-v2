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
| **bootstrap/app.php**       | _Entry point_ konfigurasi Laravel 11. Tempat mengatur Routing, Middleware global, dan Exception Handling.          |
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
3.  Gunakan method `authorize()` di dalam file Request untuk mengecek hak akses user sebelum validasi berjalan.

## 7. Migrations, Seeders, dan Factories

1.  Table yang sifatnya "detail" (weak entity) boleh digabung di satu file migration dengan parent-nya jika strukturnya sederhana.
2.  Gunakan trait `WithoutModelEvents` saat seeding data besar untuk menghindari konflik dengan Observers atau Spatie Roles.
3.  Selalu panggil semua Seeder melalui `DatabaseSeeder.php`.
4.  Gunakan pengecekan `app()->isProduction()` di seeder agar data dummy tidak sengaja masuk ke server produksi.

## 8. Enums

1.  Gunakan **Backed Enums** (string atau int) untuk memudahkan penyimpanan ke database.
2.  Gunakan Enum di dalam Model Casting: `protected $casts = ['status' => EventStatus::class];`.

## 9. Models, Observers, dan Policies

1.  **Models:** Gunakan `$fillable` untuk keamanan mass assignment. Selalu definisikan hubungan (Relationship) dengan jelas.
2.  **Observers:** Gunakan untuk aksi "side-effect" seperti mengirim email setelah transaksi berhasil atau menghapus file fisik setelah data di DB dihapus.
3.  **Policies:** Gunakan `Gate::authorize()` di Controller untuk menghubungkan aksi ke file Policy.

## 10. Providers

1.  Gunakan `AppServiceProvider` untuk konfigurasi global sederhana.
2.  Buat Provider baru jika ada integrasi library besar agar `AppServiceProvider` tidak menjadi "sampah" kode.

## 11. Storage

1.  Gunakan `Storage::disk('public')` untuk file yang bisa diakses umum.
2.  Gunakan `Storage::disk('local')` untuk file sensitif (seperti laporan keuangan).
3.  Wajib menjalankan `php artisan storage:link` di server agar file bisa ditampilkan di browser.
4.  Wajib menjalankan storage:link melalui docker compose exec agar symbolic link yang tercipta merujuk pada struktur filesystem di dalam kontainer, bukan di host.

```bash
docker compose exec app php artisan storage:list
```
