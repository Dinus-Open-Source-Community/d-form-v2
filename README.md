# D-Form

D-Form adalah proyek open-source dari **Dinus Open Source Community (DOSCOM)** berupa platform web untuk mengelola berbagai kegiatan dan kebutuhan organisasi.

Platform ini dikembangkan untuk menangani:

- **Event Management:** Pengelolaan event yang diselenggarakan oleh DOSCOM.
- **Open Recruitment:** Pengelolaan proses penerimaan anggota baru DOSCOM.
- **Attendance System:** Sistem presensi peserta menggunakan QR Code.

## Tech Stack

| Teknologi    | Versi |
| ------------ | ----- |
| Laravel      | 12.x  |
| Vue.js       | 3.x   |
| Inertia.js   | 2.x   |
| Tailwind CSS | 4.x   |
| shadcn-vue   | -     |
| MySQL        | 9.0   |
| Redis        | 8.0   |
| Node.js      | 24+   |

## Development Tools

| Tools          | Versi   | Keterangan                                    |
| -------------- | ------- | --------------------------------------------- |
| npm            | 11+     | JavaScript package manager                    |
| Docker         | -       | Containerization                              |
| FrankenPHP     | PHP 8.3 | Web server                                    |
| Laravel Pint   | ^1.25   | PHP code formatter                            |
| Prettier       | ^3.6.2  | Formatter Blade, Vue, TypeScript, dan lainnya |
| Laravel Octane | ^2.13   | Application server bootstrapper               |

## Development Guidelines

Sebelum melakukan perubahan kode, pastikan untuk membaca pedoman pengembangan berikut:

- [Frontend Development Guidelines](docs/rules/front-end.md)
- [Backend Development Guidelines](docs/rules/back-end.md)

## Documentation

Dokumentasi proyek tersedia dalam beberapa bagian:

| Dokumentasi                                                 | Deskripsi                                                 |
| ----------------------------------------------------------- | --------------------------------------------------------- |
| [Instalasi Proyek](docs/01-installation.md)                 | Panduan instalasi dan konfigurasi lingkungan pengembangan |
| [Struktur Direktori](docs/02-directory-structure.md)        | Penjelasan struktur folder dan organisasi kode            |
| [Development Guidelines](docs/03-development-guidelines.md) | Pedoman pengembangan frontend dan backend                 |
| [Email Queue](docs/04-email-queue.md)                       | Konfigurasi antrean email konfirmasi pendaftaran          |
| [Frontend Quality Gates](docs/05-frontend-quality-gates.md) | Type checking dan unit testing frontend                   |
| [E2E Testing](docs/06-e2e-testing.md)                       | Panduan pengujian end-to-end menggunakan Playwright       |
