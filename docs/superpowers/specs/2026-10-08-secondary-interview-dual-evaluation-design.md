# Desain: Interview Secondary + Evaluasi Ganda + Budget 3× Simpan

Tanggal: 2026-10-08 | Status: disetujui per bagian, menunggu review tertulis
Keputusan induk: pendekatan A — evaluasi milik interview (1 interview + 1 evaluasi per divisi).

## 1. Latar & Tujuan

Halaman my-interviews menilai 1 interview + 1 evaluasi per applicant dan evaluasi
langsung terkunci setelah submit. Kebutuhan baru:

1. Klik Simpan memunculkan modal konfirmasi: data disimpan, total kesempatan
   simpan 3×, lalu terkunci permanen.
2. Setelah interview primary selesai, applicant bisa di-interview divisi
   secondary-nya (opsional, tidak wajib diambil).
3. Penilaian ada 2: dari interviewer primary dan secondary, masing-masing
   terikat divisinya. Interview secondary opsional.

Keputusan yang sudah dikunci bersama user:
- Satu spek penuh sekaligus (tidak bertahap).
- Secondary via klaim di section Secondary tab in_progress (bukan waiting room —
  mekanisme itu sudah dihapus refactor 2026-10-07).
- Budget: total 3× simpan per evaluasi (awal + 2 ubahan), terkunci mutlak,
  staff pun tidak bisa override lagi.
- Primary selesai → langsung FinalReview; secondary pelengkap.
- Satu kali scan QR berlaku untuk kedua interview.
- Final selection: staff menimbang manual melihat kedua evaluasi.

## 2. Data & Migrasi (Bagian 1, disetujui)

1. `recruitment_interviews`: lepas UNIQUE `recruitment_application_id`; tambah
   `interview_kind` (`primary`/`secondary`, default `primary`, backfill existing
   → `primary`); tambah UNIQUE `(recruitment_application_id, interview_kind)`.
2. `recruitment_evaluations`: lepas UNIQUE `recruitment_application_id`; tambah
   UNIQUE `recruitment_interview_id`; tambah `save_count` unsignedTiny default 0.
3. Backfill: semua evaluasi lama yang terkunci → `save_count = 3`
   (dianggap habis, tidak dibuka lagi).
4. Relasi: `Application::interview()` hasOne → `interviews()` hasMany +
   `primaryInterview()` / `secondaryInterview()`; kolom
   `evaluations.recruitment_application_id` dipertahankan untuk query.
5. Absensi tidak berubah.

## 3. Alur (Bagian 2, disetujui)

1. **Eligibility secondary**: applicant punya `secondary_division_id` +
   evaluasi primary tersimpan (≥1×) + belum ada interview secondary +
   stage belum Completed.
2. **Section Secondary** di `my-interviews?tab=in_progress` (slot "Secondary
   menyusul" yang sudah di-scaffold di `Index.vue`) diisi kartu peluang:
   tombol **Ambil** + pilih sesi aktif divisi secondary. Tidak wajib diambil.
3. **Klaim**: `POST my-interviews/secondary-claim {application_id, session_id}`
   membuat interview `kind=secondary`, `interviewer_id` = pengambil. Kartu
   berubah menjadi **Nilai**.
4. **Show/evaluate per-interview**: route `my-interviews/{application}` →
   `my-interviews/{interview}`; presenter menyajikan application + interview +
   evaluasi milik interview tersebut.
5. **Modal simpan** (primary & secondary): "Data akan disimpan. Kesempatan
   simpan tersisa X dari 3. Setelah habis, terkunci permanen termasuk untuk
   staff." Pilihan Batal / Ya, simpan.
6. **Budget**: `save_count` +1 tiap submit sukses (interviewer maupun staff);
   cap 3 → set `locked_at`, ditolak untuk semua pihak.

## 4. Otorisasi & Keputusan Final (Bagian 3, disetujui)

1. Policy `viewAssignedInterview` / `evaluate` menerima `RecruitmentInterview`:
   lolos bila `interview.interviewer_id == user` + permission + applicant
   check-in. Superadmin & staff tetap semua.
2. Endpoint override staff tetap ada tapi ikut budget (ditolak saat cap).
3. Klaim divalidasi ganda di server: pengambil interviewer divisi secondary
   applicant + `evaluations.submit` + applicant eligible.
4. Halaman final selection menampilkan kedua evaluasi berdampingan (skor,
   rekomendasi, catatan, penilai, status sisa/terkunci). Keputusan tetap manual.
   Tanpa secondary → penanda "tidak diinterview secondary (opsional)".

## 5. Testing, Kompatibilitas, Scope (Bagian 4, disetujui)

1. Budget: simpan ke-1/2/3 lolos, ke-4 ditolak (interviewer DAN staff).
2. Klaim: tolak bila tak punya secondary / primary belum dinilai / sudah ada
   secondary / pengambil beda divisi / tanpa permission.
3. Happy path secondary: klaim → nilai → tampil di final. Test lama
   dimutakhirkan ke route per-interview.
4. URL show lama per-application di-redirect ke interview primary (transisi).
5. Di luar scope: timeout penutupan secondary, agregasi aturan final, scan
   ulang, notifikasi klaim baru.

## 6. Risiko & Catatan Konteks

- Tree `dev-zapp` saat spek ditulis sedang di-refactor paralel (waiting room +
  booking dihapus, route assignment staff belum terkabel). Implementasi
  berangkat dari tree pasca-refactor stabil.
- Pendekatan B (slot evaluasi kedua tanpa interview) dan C (tabel khusus
  secondary_*) ditolak: B kontradiksi kebutuhan interview secondary, C
  duplikasi maintenance.
