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

- `✨ feat:` Untuk penambahan fitur baru.
- `🔧 fix:` Untuk perbaikan bug.
- `🏗️ refactor:` Untuk memperbaiki struktur kode
- `📝 docs:` Untuk perubahan dokumentasi.
- `⬆️ chore:` Untuk tugas rutin/maintenance (update library, dll).

Contoh: `✨ feat: add oklch color support to tailwind config`

## 3. Pull Requests (PR)

1. PR wajib memiliki deskripsi yang jelas tentang apa yang diubah.
2. Setiap PR wajib di-review oleh minimal satu pengembang lain sebelum di-merge.
3. Pastikan tidak ada konflik (_resolve conflicts_) sebelum meminta review.

## 4. Environment & Security

1. **Dilarang keras** melakukan commit pada file `.env`. Pastikan `.env` terdaftar di `.gitignore`.
2. Gunakan `.env.example` sebagai referensi variabel apa saja yang dibutuhkan.
3. Jangan pernah membagikan kredensial (Password/Token) di dalam _source code_.

## 5. Daily Sync & Communication

- Update harian dilakukan melalui [Sebutkan Media, misal: Discord/WA Group].
- Jika ada kendala teknis (_blocker_) lebih dari 2 jam, wajib melapor agar bisa dibantu.
