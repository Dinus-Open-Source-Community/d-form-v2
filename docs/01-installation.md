# Instalasi

Halaman ini bertujuan untuk memberikan dokumentasi tentang cara menginstal projek dan konfigurasi projek agar bisa menjalankannya. Untuk konfigurasi lebih lanjut bisa ke [Konfigurasi teks editor](configurations/text-editor.md)

### 1. Melakukan clone project ini / fork ke repo dform

### 2. Menginstal library php yang dibutuhkan dengan perintah di bawah (termasuk dev library)

```bash
composer install
```

### 3. Menginstal library js yang dibutuhkan dengan perintah di bawah menggunakan npm (termasuk dev library)

```bash
npm run dev
```

### 4. Konfigurasi dotenv

bisa men-copy .env.example dan menjadikannya .env atau menggunakan command berikut

```bash
cp .env.example .env && php artisan key:generate
```

Yang harus diisi di .env

- APP_KEY (Sudah otomatis dari key:generate)
- DB_PASSWORD
- REDIS_PASSWORD

### 5. Install Laravel Octane

Kita perlu file binary dari frankenphp sebagai web server, jadi kita harus menginstalnya terlebih dahulu. File binary frankenphp berukuran ~250 MB dan diletakkan di root projek ini.

```bash
php artisan octane:install --server=frankenphp
```

### 6. Melakukan build image docker

Jika ini adalah kali pertama anda menjalankan projek ini, maka anda perlu mem-build image docker dari projek ini terlebih dahulu

```bash
sudo docker compose up -d --build
```

### 7. Menjalankan projek dengan docker compose

Perintah ini boleh di-skip jika anda menggunakan perintah di atas.

```bash
sudo docker compose up -d
```
