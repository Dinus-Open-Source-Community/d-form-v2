# Form Builder: Kartu Header dan Optimasi Simpan Field

| Field       | Isi                                     |
| ----------- | --------------------------------------- |
| Issue       | DFORM-HEADER (nomor tiket menyusul)     |
| Commit      | `1f80fec3` (UI), `59cadfba` (backend)   |
| Tanggal     | 20 November 2026                        |
| Klasifikasi | refactor (UI) dan performance (backend) |

## 1. Ringkasan

Panel header form builder dipisah dari kanvas field menjadi kartu pengaturan sendiri, dan jalur simpan snapshot field diubah dari satu query per baris menjadi chunk. Dua bug nyata ikut diperbaiki: tombol "pindah ke bawah" tidak mengubah urutan, serta field yang pernah dihapus membuat simpan berikutnya gagal 500.

## 2. Perubahan

| File                                                                                | Perubahan                                                                                                             |
| ----------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `components/modules/builder/FormBuilderHeaderSettings.vue`                          | Baru. Kartu berisi sampul, judul, deskripsi, counter karakter, dan status autosave.                                   |
| `components/modules/builder/FormBuilderBannerBlock.vue`                             | Dipersempit: satu jalur ganti dan satu jalur hapus, nama file hanya tooltip, hint dibangun dari `BANNER_MAX_SIZE_KB`. |
| `components/modules/builder/FormBuilderCanvasBuildView.vue`                         | Header keluar dari kartu kanvas, kontainer memakai `max-w-[720px]`, kanvas tetap `max-w-[480px]/[520px]`.             |
| `components/modules/builder/FormBuilderWorkspace.vue`                               | Meneruskan `autosaveStatus` dan event `autosaveRetry`.                                                                |
| `hooks/useFormBuilderWorkspace.ts`                                                  | `moveField` mengirim posisi sisip sebelum elemen dilepas; arah turun tidak lagi kembali ke posisi asal.               |
| `constants/formBuilderLimits.ts`, `lib/ariaIds.ts`, `lib/__tests__/ariaIds.test.ts` | Batas karakter bersama dan penggabung id `aria-describedby` beserta unit test.                                        |
| `pages/Dashboard/Events/{Create,Forms/Create,Forms/Show}.vue`                       | Mengirim status autosave yang sudah dimiliki halaman.                                                                 |
| `app/Services/Form/FormFieldSnapshotSync.php`                                       | Baru. Sinkronisasi snapshot: satu query baca, insert per 200 baris, upsert per 200 baris, hapus per 500 id.           |
| `FieldOperationController.php`                                                      | Memakai service, menghapus loop `create()/update()` per field.                                                        |
| `FieldModifyRequest.php`                                                            | Batas `fields` 500 dengan pesan Indonesia.                                                                            |
| `FormFieldsRequestValidation.php`                                                   | `MAX_FIELDS = 500`; konflik nama field divalidasi dari satu query, bukan satu `exists()` per field.                   |
| `LegacyFormImage.php`                                                               | Tidak menyalin baris yang tidak memuat `data:`.                                                                       |

Copy yang berubah: `Title` menjadi `Judul form`, `Subtitle` menjadi `Deskripsi singkat`, hint banner tanpa tanda pisah panjang, nama file mentah `banner-form` dihapus dari tampilan.

## 3. Keputusan dan alternatif yang ditolak

- **Header dipisah dari kanvas.** Ditolak: satu kolom dengan pratinjau di bawah (scroll bolak-balik di desktop) dan modal pengaturan (pekerjaan utama tersembunyi).
- **Pratinjau peserta di kolom kanan dihapus** atas permintaan PM karena tombol Pratinjau sudah ada. Konsekuensi: header memakai lebar `max-w-[720px]`, bukan dua kolom.
- **Judul dan paragraf pengantar kartu dihapus** karena mengulang label field.
- **Status autosave dirender di kartu header dan tetap ada di bar tab.** Ditolak: memindahkan satu-satunya indikator ke header karena tab Jawaban dan Pengaturan juga membutuhkannya.
- **Batas memori tetap 256M** sesuai keputusan PM (produksi 256M). Ditolak: menurunkan ke 128M, karena OOM berasal dari Debugbar dan Inertia DevTools, bukan dari aplikasi.
- **Upsert/insert chunked** dipilih daripada mempertahankan Eloquent per baris. Insert dipakai untuk id baru agar id milik form lain tidak pernah tertimpa; upsert hanya untuk id yang memang milik form.

## 4. Bukti

- `pint --test`: PASS 865 file. `npm run lint`: exit 0. `npm run typecheck`: exit 0.
- `tests/Feature/Forms`: 372 passed.
- Test baru `FormBuilderHttpTest`: 250 field tersimpan dengan query di bawah 60 (sebelumnya 261), batas 500 field ditolak 422, dan field terhapus dipulihkan.
- E2E: `form-builder-header` desktop 4 dan mobile 1 lulus, `upload-ui-media` 2, `form-builder-drag-state` 8, `builder-autosave` 2, `form-create-autosave` 3.
- Build: entry `51,13 kB raw / 16,13 kB gzip`, chunk terbesar `451,8 kB / 142,1 kB gzip`, `FormBuilder` `25,16 kB / 8,79 kB gzip`. Semua di bawah budget `AGENTS.md`.
- Lighthouse 3 run pada aset produksi tanpa Vite dan tanpa Debugbar: performance 88/78/86, accessibility 100/100/100, best practices 100/100/100, SEO 58/58/58. LCP 604 ms. SEO tetap di bawah 100 karena halaman admin sengaja `noindex`; keputusan PM: pertahankan.
- Benchmark memori jalur request (decode, konversi legacy, validasi): 200 field 26 MB peak, 400 field 36 MB peak; dengan batas 256M.
- Sampah dev dibersihkan: `storage/debugbar` 315 MB, `laravel.log` 281 MB, `browser.log` 35 MB, total sekitar 631 MB. `LOG_CHANNEL=daily` di `.env` lokal.

## 5. Temuan di luar scope

- Kegagalan E2E yang sudah ada sebelum perubahan ini, dibuktikan dengan menjalankan ulang tanpa patch: `builder-autosave` anomali debounce, dua test `form-builder-autosave-channels`, `form-create-autosave` draf kosong, `autosave-retry` 422, `fe-audit-003`, `form-builder-topbar`, `multi-upload-fields`.
- OOM 256M berasal dari Debugbar (`QueryCollector`, `TimeDataCollector`, `JavascriptRenderer`) dan Inertia DevTools. Rekomendasi menonaktifkan Debugbar untuk route builder belum dikerjakan.
- `CONTEXT.md` §14 no. 13 tidak akurat: `docker/php/php.ini` justru dimuat sebagai `conf.d/zz-app.ini` di dev dan `99-custom.ini` di prod.
- Setelah `podman compose up --force-recreate`, entrypoint mengembalikan kepemilikan `storage` ke `www-data`, sehingga harness E2E di host kehilangan izin tulis ke `storage/app/e2e`. Perbaikan sementara: `podman unshare chown -R 0:0 storage/app/e2e`.
- Working tree saat pengerjaan juga memuat perubahan sesi lain (`app/Models/Form.php`, `tests/Feature/Forms/FormDraftTest.php`, `SecurityHeaders`, `EventShowAsideRail`, `.impeccable/config.json`). Perubahan itu tidak ikut di-commit.

## 6. Risiko dan rollback

- Perubahan urutan simpan field menyentuh data: rollback kode cukup dengan revert dua commit, tidak ada migrasi.
- Batas 500 field adalah guardrail baru; form dengan lebih dari 500 field akan ditolak 422. Tidak ada form seperti itu di data saat ini.
- Lighthouse performance belum 100 karena total blocking time hidrasi builder. Pekerjaan lanjutan yang disarankan: memecah inisialisasi autosave dari chunk halaman dan menunda hidrasi komponen builder yang tidak terlihat saat first paint.
