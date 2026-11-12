---
author: 'DOSCOM'
project: 'DForm'
version: 2
---

# Aturan Umum (General Rules)

Dokumen ini berisi aturan kolaborasi yang wajib diikuti oleh seluruh pengembang (Front-End & Back-End).

## 1. Git Flow & Forking Strategy

Proyek ini menggunakan **Forking Workflow**. Setiap pengembang wajib mengikuti prosedur berikut:

- **Central Repo (Upstream):** - `main`: Kode stabil siap produksi. Dilarang keras _direct commit_ atau PR langsung ke sini kecuali dari branch `development`.
    - `development`: Tempat integrasi utama. Semua PR fitur baru diarahkan ke sini.
- **Forked Repo (Origin):**
    - `main`: Hanya digunakan untuk menyamakan (_sync_) kode dengan `upstream/main`. Jangan melakukan koding di sini.
    - `feat/nama-fitur`: Branch untuk pengerjaan fitur baru.
    - `fix/nama-bug`: Branch untuk perbaikan bug.

### Alur Kontribusi:

1. **Fork** repository pusat ke akun GitHub masing-masing.
2. **Clone** repo fork kamu ke lokal (laptop).
3. Tambahkan repo pusat sebagai `upstream`: `git remote add upstream [URL_REPO_PUSAT]`.
4. Buat branch baru dari `main` lokal: `git checkout -b feat/fitur-keren`.
5. Kerjakan kodingan, lalu **Push** ke repo fork kamu: `git push origin feat/fitur-keren`.
6. Buka GitHub, lakukan **Pull Request (PR)** dari `origin:feat/fitur-keren` ke `upstream:development`.

### Cara Sinkronisasi dengan Repo Pusat (Upstream)

Agar kode di laptopmu tidak tertinggal dengan fitur terbaru yang sudah di-merge ke pusat, lakukan langkah ini secara rutin (terutama sebelum membuat branch fitur baru):

1. Pastikan remote `upstream` sudah terdaftar:  
   `git remote -v` (Jika belum ada, jalankan: `git remote add upstream [URL_REPO_PUSAT]`)
2. Ambil update terbaru:  
   `git fetch upstream`
3. Gabungkan ke main lokal:  
   `git checkout main` lalu `git merge upstream/main`
4. Update repo fork (GitHub):  
   `git push origin main`

## 2. Conventional Commits

Setiap pesan commit wajib mengikuti format [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` untuk penambahan fitur baru.
- `fix:` untuk perbaikan bug.
- `refactor:` untuk perubahan struktur tanpa perubahan behavior.
- `docs:` untuk perubahan dokumentasi.
- `chore:` untuk maintenance.
- `test:` untuk penambahan atau perubahan test.
- `perf:` untuk optimasi performance.
- `security:` untuk perubahan security.

Commit wajib English-only, jelas, singkat, dan tanpa emoji.

Contoh: `feat: add oklch color support to Tailwind config`

## 3. Pull Requests (PR)

1. PR wajib memiliki deskripsi yang jelas tentang apa yang diubah.
2. Setiap PR wajib di-review oleh minimal satu pengembang lain sebelum di-merge.
3. Pastikan tidak ada konflik (_resolve conflicts_) sebelum meminta review.
4. PR wajib mencantumkan acceptance criteria, test yang dijalankan, risiko, migration impact, dan rollback/mitigasi bila relevan.
5. CI yang relevan harus selesai dan hijau sebelum merge. Kegagalan yang disetujui sebagai waiver wajib mencantumkan alasan, owner, dan follow-up.

## 4. Environment & Security

1. **Dilarang keras** melakukan commit pada file `.env`. Pastikan `.env` terdaftar di `.gitignore`.
2. Gunakan `.env.example` sebagai referensi variabel apa saja yang dibutuhkan.
3. Jangan pernah membagikan kredensial (Password/Token) di dalam _source code_.
4. Jangan mencatat password, token, file sensitif, atau PII berlebihan ke log.
5. Dependency baru membutuhkan alasan, owner, evaluasi license/vulnerability, dan evaluasi dampak performance.
6. Lockfile harus berubah konsisten dengan manifest dependency.
7. Source code tidak boleh dikirim ke layanan eksternal tanpa persetujuan dan pemeriksaan secret.

## 5. Daily Sync & Communication

- Update harian dilakukan melalui [Sebutkan Media, misal: Discord/WA Group].
- Jika ada kendala teknis (_blocker_) lebih dari 2 jam, wajib melapor agar bisa dibantu.
