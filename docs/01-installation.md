# Installation & Local Development Setup

## Overview

D-Form menggunakan Makefile untuk mengotomatisasi proses instalasi, konfigurasi, dan menjalankan aplikasi pada lingkungan development.

Terdapat dua implementasi Makefile berdasarkan container runtime:

| File        | Container Runtime |
| ----------- | ----------------- |
| `Makefile`  | Podman            |
| `docker.mk` | Docker            |

Keduanya menyediakan alur development yang serupa. Perbedaan utamanya terletak pada penggunaan perintah container runtime.

Developer disarankan menggunakan Makefile sebagai entry point utama daripada menjalankan perintah container secara manual.

## 1. Prerequisites

Pastikan tools berikut sudah tersedia:

| Tools          | Requirement | Keterangan                 |
| -------------- | ----------- | -------------------------- |
| Git            | Latest      | Version control            |
| Podman         | -           | Container runtime          |
| Podman Compose | -           | Container orchestration    |
| GNU Make       | -           | Automation commands        |
| Node.js        | 24+         | JavaScript runtime         |
| npm            | 11+         | JavaScript package manager |

Jika menggunakan Docker, gunakan Docker dan Docker Compose sebagai pengganti Podman.

## 2. Clone Repository

Clone repository D-Form:

```bash
git clone <repository-url>
cd d-form
```

## 3. Environment Configuration

Buat file environment berdasarkan template yang tersedia:

```bash
cp .env.example .env
```

Sesuaikan konfigurasi environment dengan kebutuhan development, terutama:

- Application configuration
- Database connection
- Redis connection
- Mail configuration

Pastikan konfigurasi `.env` sesuai dengan service yang didefinisikan dalam container configuration.

## 4. Install Frontend Dependencies

Instal dependency frontend menggunakan npm:

```bash
npm install
```

Frontend dependencies diperlukan untuk menjalankan development server dan proses build aset frontend.

## 5. Running the Application

### 5.1 Podman

Secara default, `Makefile` menggunakan Podman sebagai container runtime.

Jalankan:

```bash
make
```

Perintah tersebut secara otomatis menjalankan target `run`.

Alternatif:

```bash
make run
```

Target `run` menjalankan beberapa proses secara berurutan:

1. **Build & Start Containers**

    ```bash
    podman compose up --build -d
    ```

    Membangun image terbaru dan menjalankan container dalam background.

2. **Wait for Application Readiness**

    Makefile memeriksa log container `d_form_app` hingga menemukan pesan `Starting Octane`.

    Proses menunggu dilakukan maksimal 60 kali dengan interval 5 detik.

3. **Database Migration**

    ```bash
    php artisan migrate:fresh --force
    ```

    Menghapus seluruh tabel dan menjalankan ulang migration.

4. **Database Seeding**

    ```bash
    php artisan db:seed --force
    ```

    Mengisi database menggunakan seeder yang tersedia.

5. **Start Background Services**

    Menjalankan Laravel queue worker:

    ```bash
    php artisan queue:work --tries=3
    ```

    Menjalankan Laravel scheduler:

    ```bash
    php artisan schedule:work
    ```

6. **Follow Container Logs**

    ```bash
    podman compose logs -f
    ```

    Menampilkan log container secara real-time.

### 5.2 Docker

Jika menggunakan Docker, gunakan `docker.mk` sebagai pengganti Makefile default.

```bash
make -f docker.mk
```

Atau secara eksplisit:

```bash
make -f docker.mk run
```

Implementasi Docker menyediakan alur development yang serupa dengan implementasi Podman.

## 6. Database Reset Warning

**Penting:** Target `run` menjalankan perintah:

```bash
php artisan migrate:fresh --force
```

Perintah tersebut akan menghapus seluruh tabel sebelum menjalankan ulang migration.

Konsekuensinya:

- Seluruh data dalam database akan hilang.
- Data akan dibuat ulang melalui database seeder.
- Jangan menjalankan target `run` pada database production atau database development yang berisi data penting.

Pastikan database yang digunakan memang ditujukan untuk lingkungan development.

## 7. Frontend Development Server

Untuk menjalankan frontend development server dan mengaktifkan Hot Module Replacement (HMR):

```bash
npm run dev
```

Pastikan container aplikasi sudah berjalan sebelum menjalankan development server.

## 8. Development Workflow

Alur pengembangan lokal:

1. Clone repository.
2. Buat dan konfigurasi file `.env`.
3. Instal frontend dependencies menggunakan `npm install`.
4. Jalankan aplikasi menggunakan Makefile.
5. Tunggu hingga Laravel Octane siap.
6. Pastikan migration dan seeding berhasil.
7. Jalankan frontend development server jika diperlukan.
8. Mulai pengembangan fitur.

## 9. Troubleshooting

### Application container tidak siap

Periksa log container:

```bash
podman logs d_form_app
```

Pastikan container berhasil menjalankan Laravel Octane.

### Database migration gagal

Periksa:

- Database container sudah berjalan.
- Konfigurasi database pada `.env` benar.
- Database dapat diakses oleh aplikasi.

### Queue tidak berjalan

Periksa proses queue worker dan log aplikasi.

Perintah yang digunakan oleh Makefile:

```bash
php artisan queue:work --tries=3
```

### Scheduler tidak berjalan

Pastikan scheduler telah dijalankan melalui:

```bash
php artisan schedule:work
```

### Menggunakan Docker tetapi perintah menjalankan Podman

Pastikan menggunakan:

```bash
make -f docker.mk
```

Jangan menggunakan Makefile default apabila ingin menjalankan implementasi Docker.

## Related Documentation

- [Project README](../README.md)
- [Directory Structure](02-directory-structure.md)
- [Development Guidelines](03-development-guidelines.md)
- [Frontend Quality Gates](05-frontend-quality-gates.md)
- [E2E Testing](06-e2e-testing.md)
