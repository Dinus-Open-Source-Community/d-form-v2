# PRD: Integrasi Pendaftaran D-Form dengan DU

| Metadata    | Nilai                                              |
| ----------- | -------------------------------------------------- |
| Status      | Draft untuk review tim                             |
| Tanggal     | 13 November 2026                                   |
| Produk      | D-Form v2 dan DU (`du.zappz.my.id`)                |
| Dokumen API | [`external-redeem-api.md`](external-redeem-api.md) |

Dokumen ini mendefinisikan kebutuhan produk integrasi. Dokumen belum menjadi persetujuan implementasi. Perilaku yang sudah dikonfirmasi pengguna ditulis sebagai keputusan, sedangkan detail teknis yang masih perlu disepakati tim ditandai sebagai usulan atau pertanyaan terbuka.

## 1. Ringkasan

D-Form mengelola event dan form pendaftaran. Untuk event DU, peserta memilih kelas DU di form. Setelah admin menyetujui pendaftaran, D-Form meminta DU membuat redeem code yang dibatasi untuk email peserta. Peserta menerima redeem code dan QR absensi, dapat melihat redeem code di detail pendaftarannya, dan admin dapat melihat status serta kode pada tabel peserta.

Integrasi hanya berlaku untuk jenis form DU. Form biasa tetap menggunakan perilaku pendaftaran dan email yang sudah ada. Pilihan kelas berlaku per peserta, termasuk anggota bundle yang dapat memilih kelas berbeda.

## 2. Masalah

Peserta DU saat ini perlu mendaftar melalui form D-Form dan memperoleh dua hal setelah disetujui:

1. QR code D-Form untuk absensi event.
2. Redeem code DU untuk kelas yang dipilih peserta.

Tanpa integrasi, admin harus membuat dan mengirim redeem code secara terpisah. Pencocokan pilihan kelas, email, approval, dan redeem code berisiko memerlukan pekerjaan manual serta menyulitkan tindak lanjut ketika peserta bertanya.

## 3. Tujuan dan metrik keberhasilan

### Tujuan

- Peserta dapat memilih kelas DU aktif saat mendaftar melalui form DU.
- Pilihan kelas tersimpan untuk setiap peserta, termasuk tiap anggota bundle.
- Admin dapat menyetujui satu peserta dan memicu pembuatan redeem code DU dari server D-Form.
- Redeem code dan status penerbitannya tersimpan per peserta, terpisah dari QR registration code.
- Peserta dapat menerima QR dan redeem code melalui email serta melihat kode di detail pendaftaran.
- Admin dapat melihat hasil, menangani kegagalan yang pasti, dan membagikan kode yang sudah terbit secara manual.
- Kegagalan DU tidak mengubah keputusan review yang sudah diterima D-Form.

### Ukuran keberhasilan

- Seluruh form yang ditandai sebagai form DU menampilkan kelas aktif dari API DU, bukan opsi kelas statis.
- D-Form menyimpan paling banyak satu redeem code hasil penerbitan yang terkonfirmasi per peserta; status `unknown` tidak diproses ulang tanpa rekonsiliasi.
- Pilihan kelas untuk tiap anggota bundle dipertahankan secara terpisah.
- Status review, status redeem, dan status email dapat dibedakan oleh admin.
- API key tidak pernah dikirim ke browser atau tercatat dalam log.
- Tidak ada perubahan perilaku pada form non-DU.

Angka target uptime, waktu respons, atau jumlah peserta tidak ditetapkan di PRD ini karena belum ada baseline produk yang disepakati.

## 4. Batas lingkup

### Termasuk

- Jenis form DU baru yang terpisah dari form registration/other pada umumnya.
- Field sistem wajib untuk memilih kelas DU.
- Pengambilan seluruh halaman daftar kelas aktif DU.
- Single dan bundle, dengan pilihan kelas per peserta.
- Penerbitan redeem code saat admin menyetujui peserta, satu peserta per tindakan.
- Status/kode DU pada tabel admin dan detail pendaftaran peserta.
- Email konfirmasi berisi QR dan redeem code setelah kode berhasil disimpan.
- Penanganan error, notifikasi admin, retry manual terbatas, dan penerbitan manual per baris untuk submission lama yang sudah accepted.
- Perubahan konfigurasi environment server, persistence, otorisasi, dan pengujian yang diperlukan.

### Tidak termasuk

- Mengubah alur atau payload QR absensi.
- Melakukan enrollment atau redeem atas nama peserta di DU.
- Mengubah perilaku form non-DU.
- Bulk approval atau bulk penerbitan kode DU.
- Backfill massal untuk seluruh submission accepted lama.
- Retry otomatis untuk request POST yang hasilnya ambigu.
- Kerangka integrasi generik untuk banyak provider.
- Mengubah kelas yang dipilih pada jawaban historis secara diam-diam.

## 5. Pengguna dan user stories

1. Sebagai admin event DU, saya ingin menandai form sebagai jenis DU agar alur khusus redeem code hanya berlaku pada form yang memang terhubung ke DU.
2. Sebagai admin, saya ingin field pilihan kelas selalu tersedia pada form DU agar peserta tidak dapat mendaftar tanpa menentukan kelas.
3. Sebagai admin, saya ingin pilihan field kelas berasal dari API DU agar daftar kelas tidak perlu disalin dan diperbarui manual di D-Form.
4. Sebagai peserta single, saya ingin memilih kelas DU aktif saat mengisi form agar redeem code yang diterima sesuai pilihan saya.
5. Sebagai peserta bundle, saya ingin setiap anggota memiliki pilihan kelas sendiri agar anggota satu bundle dapat memilih kelas yang berbeda.
6. Sebagai peserta bundle, saya ingin pilihan kelas setiap anggota tersimpan bersama submission anggota tersebut agar kode tidak tertukar antaranggota.
7. Sebagai peserta, saya ingin menerima QR absensi dan redeem code dalam email setelah pendaftaran saya disetujui dan kode berhasil diterbitkan.
8. Sebagai peserta, saya ingin melihat redeem code di detail pendaftaran agar saya tetap dapat mengaksesnya jika email tidak ditemukan atau terhapus.
9. Sebagai admin, saya ingin menyetujui peserta satu per satu dan melihat hasil penerbitan DU agar keberhasilan tiap peserta tidak tertutup oleh hasil peserta lain.
10. Sebagai admin, saya ingin melihat status redeem code pada tabel peserta agar peserta yang belum memiliki kode mudah ditindaklanjuti.
11. Sebagai admin, saya ingin melihat kode yang sudah terbit agar saya dapat memberikannya secara manual ketika peserta melapor tidak menerima email.
12. Sebagai admin, saya ingin memperoleh notifikasi yang jelas ketika DU menolak request atau tidak dapat dihubungi agar saya mengetahui bahwa approval berhasil tetapi kode belum tersedia.
13. Sebagai admin, saya ingin mencoba ulang penerbitan untuk kegagalan yang diketahui tidak membuat kode agar masalah konfigurasi atau penolakan yang sudah diperbaiki dapat dipulihkan.
14. Sebagai admin, saya tidak ingin sistem mengulang request dengan hasil timeout yang belum diketahui karena DU mungkin sudah membuat kode dan retry dapat menimbulkan duplikasi.
15. Sebagai admin, saya ingin dapat menerbitkan kode untuk peserta lama yang sudah accepted satu per satu agar mereka tetap dapat menerima manfaat integrasi tanpa proses massal yang tidak terkontrol.
16. Sebagai admin, saya ingin memilih kelas DU pengganti untuk submission lama yang nilai dropdown-nya tidak dapat dipetakan dengan pasti agar kode dapat diterbitkan tanpa mengubah jawaban asli peserta.
17. Sebagai admin event non-DU, saya ingin form dan approval yang sudah ada tetap berjalan seperti sebelumnya tanpa request ke DU.
18. Sebagai operator aplikasi, saya ingin kredensial DU disimpan di environment server agar rahasia tidak terekspos ke browser atau source control.

## 6. Alur produk

### 6.1 Pengaturan form DU

1. Admin memilih jenis form DU pada form yang terkait event DU.
2. D-Form menyediakan field sistem kelas secara otomatis.
3. Field tersebut wajib, tidak dapat dihapus, dan opsinya tidak dapat diedit menjadi daftar statis.
4. D-Form mengambil daftar kelas aktif dari backend DU dan menyajikan nama kelas untuk dipilih peserta.
5. Pada single, pilihan disimpan pada jawaban peserta. Pada bundle, field diulang per anggota dan pengisi bundle menentukan pilihan setiap anggota.

### 6.2 Pendaftaran dan approval baru

1. Peserta mengisi form DU dan memilih kelas aktif.
2. D-Form menyimpan submission dan pilihan UID kelas per peserta.
3. Admin menyetujui satu peserta melalui tindakan approval yang sudah ada.
4. D-Form menyimpan keputusan accepted dan QR registration code terlebih dahulu.
5. Server D-Form mengirim request pembuatan kode ke DU dan menunggu hasilnya.
6. Jika berhasil, D-Form menyimpan redeem code dan status `issued`, kemudian membuat email berisi QR dan redeem code.
7. Jika DU menolak request, status review tetap accepted; status penerbitan menjadi `failed`, email belum dikirim, dan admin memperoleh notifikasi.
8. Jika hasil request tidak diketahui, status penerbitan menjadi `unknown`; D-Form tidak mengulang request tanpa rekonsiliasi dari DU.

### 6.3 Submission accepted yang sudah ada

- Submission lama pada empat form DU termasuk lingkup.
- Submission pending mengikuti proses approval normal.
- Submission accepted memperoleh tindakan issue/retry per peserta dari tabel admin, bukan backfill massal.
- Jika kelas historis dapat dipetakan secara pasti, gunakan UID yang terverifikasi.
- Jika pilihan lama hilang atau tidak dapat dipetakan secara pasti, admin memilih kelas DU aktif pengganti pada tindakan per peserta. Jawaban asli tetap dipertahankan.
- Submission rejected tidak menerima redeem code.

## 7. Kebutuhan fungsional

### FR-1: Jenis form DU

- Sistem menyediakan discriminator jenis DU yang terpisah dari tujuan form registration/other.
- Empat form pada event DU yang disebut dalam diskusi menjadi lingkup jenis baru.
- Form biasa tidak memanggil API DU dan tidak berubah behavior.
- Jenis DU berlaku pada form registration dengan mode single atau bundle yang digunakan saat ini.

### FR-2: Katalog kelas

- D-Form mengambil kelas dari endpoint `GET /courses/`.
- Endpoint hanya mengembalikan kelas aktif dan tidak memerlukan API key.
- D-Form mengikuti pagination `data.meta.total_pages`; setiap request tidak meminta lebih dari 100 item.
- Nilai pilihan yang dikirim ke server adalah full course UID, bukan UID prefix atau judul.
- Server memvalidasi response API sebelum menyajikan data ke halaman.
- Usulan MVP: jika katalog tidak dapat dimuat, peserta melihat error yang dapat dipahami dan tidak dapat mengirim pendaftaran DU tanpa pilihan kelas.

### FR-3: Field sistem dan jawaban peserta

- Field kelas otomatis tersedia pada semua form DU dan wajib dipilih.
- Admin tidak dapat menghapus field atau mengganti daftar opsinya menjadi opsi statis.
- Single menyimpan satu UID untuk peserta.
- Bundle menyimpan UID berbeda untuk setiap peserta/anggota.
- Nilai dari submission lama tidak boleh dicocokkan menggunakan fuzzy matching.
- Override historis dari admin disimpan terpisah dari jawaban asli atau dalam slot sistem DU yang tidak menimpa jawaban asli.

### FR-4: Pembuatan redeem code

- Pembuatan kode dilakukan server-to-server ke `POST /external/redeem-codes`.
- Header `X-API-Key` hanya tersedia pada server D-Form. Nilai D-Form harus sama dengan `REDEEM_CODE_API_KEY` pada server DU.
- Usulan MVP: request memuat `course_uid`, `target_email`, dan `max_uses: 1`.
- Target email mengikuti email peserta yang dipakai alur notifikasi D-Form: email user terhubung jika ada, jika tidak gunakan email undangan peserta.
- Usulan MVP: `expires_at` tidak dikirim sehingga DU memakai default tanpa kedaluwarsa.
- D-Form tidak melakukan enrollment langsung.
- Response sukses harus HTTP 201, `success: true`, dan memiliki string kode tidak kosong di `data.code`.
- Kode dan status penerbitan tersimpan per peserta, terpisah dari `registration_code` QR.

### FR-5: Approval dan status

- Admin menyetujui satu submission per tindakan untuk form DU; bulk review tidak ditampilkan/diizinkan pada form DU.
- Review accepted disimpan sebelum request eksternal. Kegagalan DU tidak membatalkan approval atau QR.
- Status review dan status redeem adalah dua status terpisah.
- Status redeem yang diusulkan: `pending`, `issued`, `failed`, `unknown`.
- Error yang jelas ditolak oleh DU menghasilkan `failed`. Hasil request yang tidak dapat dipastikan, termasuk timeout, error server, atau response sukses yang malformed, menghasilkan `unknown`.
- Error internal tidak dikirim mentah ke user. Pesan admin harus aman dan cukup untuk tindak lanjut.
- Admin mendapat notifikasi saat proses DU gagal; tabel tetap menjadi sumber status yang dapat diperiksa setelah notifikasi hilang.

### FR-6: Retry dan penerbitan historis

- Tindakan issue/retry tersedia per peserta accepted dengan status `pending` atau `failed`.
- Tindakan tidak tersedia untuk status `unknown` sampai DU menyediakan idempotency atau lookup/reconciliation yang disepakati.
- Tindakan pada peserta lama tidak dilakukan secara massal.
- Jika kelas historis tidak dapat dipetakan, admin memilih kelas aktif dari katalog DU sebelum request.
- Permintaan issue/retry yang bersamaan untuk peserta yang sama harus dicegah.
- Setelah sukses, status menjadi `issued`, kode tersimpan, dan email DU-issued dibuat.

### FR-7: Tabel admin dan detail peserta

- Tabel peserta DU menampilkan status redeem dan kode apabila sudah terbit.
- Tabel menyediakan tindakan per peserta sesuai status penerbitan.
- Detail pendaftaran peserta menampilkan redeem code hanya setelah status `issued`.
- Redeem code hanya terlihat oleh peserta pemilik pendaftaran dan admin yang memiliki izin melihat submission.
- Kode yang sudah terbit tetap tersedia untuk dibagikan manual meskipun email gagal.

### FR-8: Email

- Email DU-issued dikirim setelah kode sukses tersimpan.
- Email memuat QR absensi yang sudah ada dan redeem code DU.
- Email untuk penerbitan DU menggunakan delivery identity berbeda dari email accepted lama agar catatan/idempotency email accepted yang sudah terkirim tidak mencegah pengiriman kode.
- Jika email gagal, redeem code dan status `issued` tetap tersimpan; email failure dicatat melalui mekanisme audit email yang ada.
- Jika DU belum menghasilkan kode, email yang memuat kode tidak dikirim.

## 8. Kebutuhan nonfungsional dan keamanan

- Semua request DU berjalan dari backend. Browser tidak mengakses endpoint POST DU dan tidak pernah menerima API key.
- Base URL dan API key berasal dari konfigurasi environment server. Tidak ada secret pada source control, log, atau props Inertia.
- Gunakan HTTPS pada environment deployed.
- Terapkan timeout HTTP yang terbatas dan error handling yang sesuai.
- Jangan mencatat API key, isi response sensitif, atau PII berlebihan.
- Redeem code diperlakukan sebagai credential: hanya pemilik submission dan admin berizin yang dapat melihatnya.
- Penerbitan per peserta harus aman terhadap double-click/concurrent request.
- Timeout atau respons server yang ambigu tidak boleh diulang otomatis karena kontrak API belum menjanjikan idempotency.
- Integrasi tidak boleh membuat request DU selama form non-DU diproses.

## 9. Kontrak API DU

| Kebutuhan            | Kontrak                                                                      |
| -------------------- | ---------------------------------------------------------------------------- |
| Daftar kelas         | `GET /courses/?page=<n>&per_page=100`, public, kelas aktif saja              |
| Pagination           | `data.courses`, `data.meta.total_pages`                                      |
| Identitas kelas      | Full `uid` dari list, dikirim sebagai `course_uid`                           |
| Buat redeem          | `POST /external/redeem-codes`                                                |
| Auth                 | `X-API-Key`, dicocokkan dengan `REDEEM_CODE_API_KEY` pada DU                 |
| Target               | `target_email` peserta                                                       |
| Jumlah redeem        | Usulan MVP: `max_uses: 1`                                                    |
| Expiry               | Usulan MVP: tidak diset, sehingga tidak kedaluwarsa                          |
| Sukses               | HTTP 201; kode pada `data.code`                                              |
| Error terdokumentasi | 400, 401, 404, 409, 500 dengan envelope `success: false`, `message`, `error` |

Katalog kursus hanya menampilkan kelas aktif. API create menerima course yang masih ada meskipun tidak aktif/published. Keputusan MVP adalah menggunakan UID yang dipilih peserta dan membiarkan DU menentukan validitas saat kode dibuat.

## 10. Status dan notifikasi yang diusulkan

| Status redeem | Arti                                                                                         | Tindakan                                                   |
| ------------- | -------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| `pending`     | Belum ada hasil penerbitan; termasuk submission accepted lama yang menunggu tindakan admin   | Issue/retry per peserta                                    |
| `issued`      | DU mengembalikan kode valid dan D-Form menyimpannya                                          | Tampilkan kode; email dikirim atau dicatat gagal           |
| `failed`      | DU memberikan penolakan yang jelas atau tidak tersedia recipient email valid sebelum request | Tampilkan alasan aman; retry setelah penyebab diselesaikan |
| `unknown`     | Request mungkin berhasil di DU, tetapi D-Form tidak dapat memastikan hasilnya                | Tidak ada retry sampai direkonsiliasi dengan DU            |

Email delivery adalah status terpisah dari status penerbitan. Kegagalan email tidak mengubah `issued` menjadi `failed` dan tidak menghapus kode.

## 11. Modul produk dan rancangan teknis tingkat tinggi

Modul ini adalah batas tanggung jawab yang diharapkan, bukan keharusan membuat setiap item sebagai class/file terpisah.

1. **DU Course Catalog:** mengambil, memvalidasi, dan memetakan katalog kelas aktif serta pagination API.
2. **DU Form Type and System Field:** menandai form DU, memastikan selector wajib, dan mendukung jawaban single/bundle per peserta.
3. **DU Redeem Issuance:** mengirim request untuk peserta, mengklasifikasi response, menyimpan kode/status, mencegah request bersamaan, serta melayani retry terbatas.
4. **Review Orchestration:** memastikan keputusan accepted lokal tidak bergantung pada keberhasilan DU dan email DU tidak dikirim sebelum kode tersimpan.
5. **DU Email Delivery:** mengirim QR dan redeem code dengan delivery identity khusus yang tidak bertabrakan dengan email accepted yang telah tercatat.
6. **Admin and Participant Presentation:** menampilkan status/kode, notifikasi, pilihan kelas override untuk historis, serta aksi per peserta.
7. **Configuration and Persistence:** konfigurasi server-only, status/kode peserta, dan migrasi yang mempertahankan jawaban historis.

## 12. Keputusan yang sudah dikonfirmasi pengguna

- Integrasi ditujukan untuk event DU yang memiliki empat form, termasuk single dan bundle.
- Jenis form DU baru dipisahkan dari form biasa; service DU tidak mengubah flow form umum.
- Field kelas adalah field sistem DU, otomatis ada, wajib, dan opsinya berasal dari API DU.
- Kelas dipilih per peserta. Setiap anggota bundle dapat memiliki pilihan berbeda.
- Approval memanggil DU langsung saat admin menekan approve, bukan menunggu antrean untuk request awal.
- Keputusan review accepted tetap accepted jika DU gagal. Status redeem code terpisah.
- Tabel admin memiliki status redeem baru dan kolom/baris kode redeem; detail pendaftaran peserta menampilkan kode.
- Kode dikirim setelah diterbitkan; jika email gagal, admin dapat melihat dan membagikan kode manual.
- Penerbitan dan retry berlangsung per peserta, bukan bulk.
- Submission lama termasuk lingkup. Accepted lama diproses satu per satu, tanpa backfill massal.
- Jika kelas historis tidak dapat dipetakan pasti, admin boleh memilih kelas DU aktif pengganti tanpa menimpa jawaban asli.
- Retry terbatas untuk kegagalan yang pasti; hasil timeout/ambigu tidak boleh diulang buta.
- Cakupan test yang diusulkan di bagian Testing Decisions disetujui untuk dimasukkan dalam PRD.

## 13. Asumsi dan pertanyaan untuk review tim

1. **Idempotency/reconciliation DU:** dokumentasi tidak menyebut idempotency key atau endpoint lookup. Pemilik DU perlu mengonfirmasi cara memeriksa request timeout sebelum retry `unknown`.
2. **Klasifikasi error retry:** tim perlu menyepakati mapping status HTTP DU yang pasti `failed` versus hasil yang harus `unknown`. Sebagai baseline, response 4xx terdokumentasi dapat diperlakukan sebagai penolakan; network timeout, 5xx, dan body sukses malformed tetap `unknown`.
3. **Identitas form lama:** implementasi harus memverifikasi empat record DU dan field dropdown lama sebelum menggantinya; jangan mengandalkan title form atau fuzzy matching.
4. **Konfigurasi key:** nama env pada D-Form perlu dipilih oleh implementasi; nilainya harus identik dengan key di DU. Nama env D-Form belum dikunci dalam PRD.
5. **Recipient tanpa email valid:** usulan MVP adalah review tetap accepted, status redeem `failed`, tidak ada request DU/email, dan admin melihat error.
6. **Email log dan notifikasi:** implementasi perlu memverifikasi bahwa admin dapat melihat kegagalan email pada mekanisme audit yang tersedia.
7. **Kelas yang berubah status:** bila kelas menjadi tidak aktif setelah peserta memilihnya, D-Form tetap mengirim UID tersimpan. API DU mengizinkan pembuatan kode untuk course yang ada tetapi tidak aktif; tim perlu mengonfirmasi ini sesuai kebijakan bisnis.
8. **Submission historis tanpa course yang tersedia:** admin hanya dapat memilih pengganti dari katalog aktif yang disediakan DU. Jika kelas lama sudah tidak ada dan admin tidak menyetujui pengganti, kode historis itu tidak dapat diterbitkan melalui API yang terdokumentasi.
9. **Kebijakan kuota dan expiry:** `max_uses: 1` dan tidak mengirim `expires_at` adalah usulan berdasarkan default API, belum menjadi kebijakan bisnis yang disahkan tim.
10. **Kegagalan katalog saat pendaftaran:** usulan MVP adalah fail-closed, peserta tidak dapat submit sampai kelas aktif berhasil dimuat.

## 14. Kriteria penerimaan

1. Form DU menampilkan field kelas yang wajib dan tidak dapat dihapus oleh admin.
2. Field menampilkan seluruh kelas aktif dari semua halaman API dan mengirim full UID.
3. Kegagalan katalog mencegah submission DU yang tidak memiliki pilihan kelas dan menampilkan pesan aman.
4. Single menyimpan satu UID; bundle menyimpan pilihan terpisah untuk tiap peserta.
5. Form biasa tetap submit, review, membuat QR, dan mengirim email seperti sebelum integrasi.
6. Approval DU mengubah review menjadi accepted dan mempertahankan QR walaupun DU menolak request.
7. Request DU menggunakan `target_email`, full `course_uid`, max uses satu, dan expiry tidak dikirim.
8. API key tidak muncul pada browser, response halaman, atau log.
9. Response sukses valid menyimpan kode dan status `issued` sebelum email dikirim.
10. Email DU-issued memuat QR dan redeem code serta menggunakan delivery identity terpisah.
11. Kegagalan DU menampilkan notifikasi admin dan status redeem yang terpisah; email kode tidak dikirim sebelum kode tersedia.
12. Hasil timeout/ambigu masuk status `unknown` dan tidak memiliki aksi retry biasa.
13. Retry/issue hanya berlaku per peserta accepted; aksi bersamaan untuk orang yang sama tidak membuat request ganda.
14. Accepted historis dapat diproses satu per satu; kelas lama yang tidak cocok dapat diganti dari katalog aktif tanpa menghapus jawaban asli.
15. Peserta hanya dapat melihat redeem code miliknya sendiri; admin mengikuti otorisasi yang berlaku untuk submission.
16. Email gagal tidak menghapus kode atau mengubah status redeem `issued`; kode tetap dapat dibagikan admin.

## 15. Keputusan pengujian

Tes harus memverifikasi behavior eksternal, bukan detail internal seperti nama class atau urutan pemanggilan private method.

### Backend feature/integration tests

- **DU Course Catalog:** pagination multi-page, filter status aktif, response valid, response malformed, dan kegagalan upstream.
- **DU Form Type/System Field:** form DU wajib memiliki selector, form biasa tidak terpengaruh, UID tervalidasi, serta jawaban per anggota bundle tersimpan dengan benar.
- **Approval dan issuance:** review accepted tetap tersimpan pada error DU; payload POST benar; sukses menyimpan kode/status; penolakan 4xx, error 5xx, timeout, dan body malformed mengikuti klasifikasi `failed`/`unknown` yang disepakati.
- **Retry/per peserta:** accepted lama dapat diissue satu per satu, override kelas historis menjaga jawaban asli, hasil unknown tidak dapat diulang, dan concurrent issue tidak membuat duplikasi lokal.
- **Email/outbox:** email DU baru hanya dibuat setelah kode tersimpan; email menyertakan QR dan kode; outbox lama yang sudah terkirim tidak mencegah email DU; email failure tidak menghapus kode.
- **Otorisasi/privasi:** API key tidak ada pada props/browser/log; kode hanya tampak bagi pemilik dan admin berizin.

### Playwright E2E

- Admin memilih form DU dan melihat field kelas sistem.
- Peserta single memilih kelas, mendaftar, lalu admin approve; status/kode terlihat dan detail peserta menampilkan kode setelah berhasil.
- Bundle dapat menyimpan pilihan kelas berbeda per peserta.
- Kegagalan katalog, error DU, status unknown, dan notifikasi admin dapat dilihat dan tidak menyebabkan email berkode terkirim.
- Admin menjalankan issue/retry per baris untuk peserta accepted lama, termasuk pilihan pengganti kelas historis.
- Form non-DU tetap melewati alur approval/email lama.

### Validasi tambahan

- Unit test untuk mapper/status classifier murni jika logic tersebut dibuat sebagai fungsi terpisah.
- Test migration memastikan nilai default aman untuk `form_answers` lama dan tidak menulis ulang jawaban existing.
- Lint, typecheck, build, test suite backend terkait, dan E2E UI dijalankan pada implementasi.

## 16. Risiko dan mitigasi

| Risiko                                    | Dampak                                                    | Mitigasi dalam MVP                                                                              |
| ----------------------------------------- | --------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| DU tidak tersedia saat approval           | Admin menunggu; kode belum ada                            | Review accepted tetap tersimpan, tampilkan status/notifikasi; retry hanya untuk rejection pasti |
| Timeout setelah DU membuat kode           | Retry dapat membuat kode tambahan                         | Tandai `unknown`; tidak ada retry otomatis/manual sampai DU menyediakan rekonsiliasi            |
| Kelas historis tak dapat dipetakan        | Peserta lama tidak bisa menerima kode sesuai jawaban lama | Exact mapping saja; admin memilih pengganti aktif per peserta dan jawaban asli dipertahankan    |
| Email gagal                               | Peserta tidak menerima email                              | Kode tetap terlihat di detail/admin dan dapat dibagikan manual; kegagalan email diaudit         |
| Key salah atau kosong                     | DU menolak semua penerbitan                               | Key hanya server-side, validasi konfigurasi saat boot/deploy, admin melihat error aman          |
| Pilihan kelas per anggota salah tersimpan | Redeem terikat ke course/email yang salah                 | Tes bundle end-to-end dan pemisahan jawaban per anggota                                         |
| Double-click/concurrent action            | Request ganda                                             | Lock/status claim per peserta sebelum HTTP request                                              |

## 17. Rollout dan rollback

### Rollout yang diusulkan

1. Konfigurasikan base URL dan key server D-Form serta pastikan nilainya cocok dengan konfigurasi server DU.
2. Deploy perubahan schema/back-end/FE dalam urutan yang menjaga kompatibilitas data jawaban lama.
3. Verifikasi empat form yang dimaksud berdasarkan record aktual, lalu tandai sebagai jenis DU.
4. Ganti dropdown kelas statis dengan field sistem; pertahankan jawaban lama dan verifikasi pemetaan UID.
5. Uji katalog kelas, approve satu peserta uji, email, detail peserta, failure state, dan manual issue untuk data lama sebelum membuka alur ke peserta.
6. Pantau status `failed`/`unknown`, respons DU, dan email log setelah rilis.

### Rollback/mitigasi

- Nonaktifkan jenis DU atau kosongkan konfigurasi integrasi untuk menghentikan request baru tanpa mengubah QR dan review accepted yang sudah ada.
- Jangan menghapus redeem code yang sudah diterbitkan atau mengubah jawaban peserta saat rollback.
- Migration rollback tidak boleh membuang kode yang sudah disimpan tanpa backup dan persetujuan eksplisit.
- Kode yang statusnya `unknown` harus direkonsiliasi dengan DU sebelum percobaan berikutnya.

## 18. Referensi

- Kontrak endpoint dan contoh response: [`external-redeem-api.md`](external-redeem-api.md).
- Arsitektur event/form, review, email, QR, dan submission per anggota: `CONTEXT.md` pada root proyek.
- Standar keamanan, validasi, struktur backend/frontend, serta test minimum: `AGENTS.md` dan `docs/rules/`.
