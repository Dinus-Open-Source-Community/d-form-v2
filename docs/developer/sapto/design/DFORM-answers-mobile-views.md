# Tampilan Jawaban Mobile

Spesifikasi ini mencatat tampilan yang diterapkan pada tab **Jawaban** di halaman detail formulir. Peralihan tampilan berlaku di bawah breakpoint `md` (768 px); mulai 768 px tabel desktop tetap digunakan.

## Bingkai 375 px

- Header “Daftar jawaban” dan jumlah jawaban tetap tampil.
- Kontrol dua pilihan berada di bawah header, rata kanan: **Kartu** dan **Daftar**. Pilihan pertama kali adalah Kartu; pilihan terakhir disimpan pada `localStorage` dengan kunci `d-form:form-answers:mobile-view`.
- Kartu menampilkan avatar, nama, email, badge status, waktu kirim, maksimal empat jawaban non-berkas, serta setiap jawaban berkas dengan ikon dan nama file. Footer menyediakan **Terima** dan **Tolak** ketika status masih menunggu review, serta **Detail**.
- Daftar menampilkan avatar, nama yang dipotong jika panjang, waktu kirim, badge status, dan chevron. Menekan seluruh baris membuka detail, termasuk aksi review yang tersedia di sheet detail.
- Tombol kontrol memiliki tinggi minimal 40 px; baris daftar minimal 72 px. Tombol aksi kartu memiliki tinggi 40 px.
- Saat belum ada kiriman, kedua mode digantikan empty state yang sama: “Belum ada jawaban” dan penjelasan bahwa jawaban akan muncul setelah formulir dikirim. Kontrol mode tidak ditampilkan.

## Bingkai 768 px

- Kontrol Kartu/Daftar dan tampilan mobile tidak dirender.
- Tabel desktop yang sudah ada tetap tampil dengan kolom Pengirim, Status review, jawaban non-berkas, Dikirim, dan Aksi.
- Empty state tetap sama dengan tampilan 375 px.

## Status review

- **Menunggu review** memakai badge status menunggu dan aksi review aktif.
- **Diterima** memakai badge “Diterima”; aksi Terima/Tolak tidak ditampilkan.
- **Ditolak** memakai badge “Ditolak”; aksi Terima/Tolak tidak ditampilkan.
- Aksi review yang sedang diproses dinonaktifkan sampai respons diterima; seluruh aksi memakai jalur review yang sama dengan tabel dan sheet detail.
