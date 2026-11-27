# Peta alur & audit perilaku D-Form v2 (6 section)

Issue: DFORM-TBD · Commit: TBD · Tanggal: 27-11-2026

## Ringkasan (TL;DR)

Menambahkan satu peta alur Excalidraw untuk seluruh aplikasi **kecuali modul
recruitment/oprec**, ditambah dokumen audit pendamping. Peta berisi enam frame dengan jenis yang dibedakan:
Business Flow (siklus layanan event), User Flow per role (guest, member, admin, super admin),
System Flowchart fitur (F1–F16), Page Flow per role (39 kartu halaman: guest, shared, member, admin, super admin, dorman; tiap kartu memuat query/filter yang terverifikasi di browser, plus tabel route non-page), BPMN gaya (lima lane
multi-role dengan gateway dan message flow), serta System Flowchart relasi modul, transisi
status, kontrak respons, dan anomali hasil audit. Alur diverifikasi dengan membaca kode
dan menjalankan sendiri di browser non-headless, bukan dari asumsi. Modul broadcast yang
dorman (`FEATURE_BROADCAST=false`) digambar sebagai frame/section bertanda **NONAKTIF**.

Tidak ada kode produksi yang diubah. Perubahan hanya dokumentasi + generator diagram.

## Deliverable

| File                                    | Isi                                                                             | Commit             |
| --------------------------------------- | ------------------------------------------------------------------------------- | ------------------ |
| `docs/flows/d-form-v2-flows.excalidraw` | Diagram 6 frame hasil generate                                                  | Tidak (gitignored) |
| `docs/flows/build-flows.mjs`            | Generator layout, tanpa dependency                                              | Ya                 |
| `docs/flows/README.md`                  | Dokumen audit: ruang lingkup, legenda, flow per role, temuan, data uji, batasan | Ya                 |
| `docs/flows/preview/frame1..6.png`      | Render PNG untuk review visual                                                  | Tidak (gitignored) |

`.gitignore` menambah dua entri: `docs/flows/*.excalidraw` dan `docs/flows/preview/`.

Regenerate: `node docs/flows/build-flows.mjs --preview /tmp/flows-preview.svg`.

## Keputusan desain

- **Satu file, enam frame** (bukan enam file) supaya konteks bisnis, aplikasi, fitur,
  halaman, auth, dan audit terlihat berurutan dalam satu dokumen, sementara Excalidraw tetap
  bisa mengekspor per frame.
- **Generator, bukan gambar tangan.** Diagram berisi 2764 elemen; mengedit manual akan cepat
  basi. Generator menyimpan struktur sebagai data, sehingga perubahan flow cukup mengubah
  satu blok kode.
- **Self-check di generator:** id unik, frame tidak tumpang tindih, elemen di dalam frame,
  kotak tidak bertumpuk, teks muat di kotaknya (0,62 em), dan panah tidak memotong kotak
  (uji Liang–Barsky). Kegagalan menghentikan proses dengan error.
- **Lebar teks diukur pada 0,62 em/karakter** (bukan 0,58) supaya pembungkusan kata lebih
  konservatif dan teks tidak melimpah keluar kotak saat dibuka di Excalidraw.
- **Panah dinormalkan ke bounding box positif** (x/y di titik minimum, `points` sebagai
  offset relatif) agar panah ke kiri/atas tetap valid menurut model data Excalidraw.
- **Jenis diagram dilabeli per frame** supaya pembaca tahu tingkatannya: Business Flow /
  User Flow / System Flowchart / Page Flow / BPMN gaya. BPMN di sini memakai kosakata lane,
  task, gateway, dan message flow, tetapi tidak diekspor sebagai BPMN 2.0 XML.
- **Recruitment dikecualikan total**, termasuk flow yang sebenarnya aktif di sidebar. Titik
  singgung yang masih hidup di kode non-recruitment dicatat sebagai temuan (T1–T4 di
  `docs/flows/README.md`), bukan digambar.

## Bukti

Tidak ada test suite PHP/Vitest yang dijalankan karena tidak ada perubahan kode produksi.
Bukti yang tersedia:

- Generator: `node docs/flows/build-flows.mjs` → `ok: 2764 elements, 6 frames`; seluruh
  self-check lolos (0 kotak bertumpuk, 0 teks melimpah, 0 panah memotong kotak).
- JSON hasil: semua elemen memiliki 25 key wajib Excalidraw, 6 frame bernama, 0 `frameId`
  menggantung.
- Fakta aturan di frame 5 dan 6 diambil langsung dari FormRequest: `RegisterStoreRequest`,
  `LoginStoreRequest`, `VerifyOtpRequest`, `ResetPasswordStoreRequest`,
  `UpdateDashboardProfile*`, `Users/StoreUserRequest`, `StoreEventRequest`,
  `StoreEventFormRequest`, `RulesBuilder`, `ReviewFormAnswerRequest`.
- Browser walkthrough (Chromium headed, `localhost:3000`) dengan empat akun: guest, member,
  admin, super admin. Bukti per langkah ada di §5 `docs/flows/README.md` dengan label
  `Reproduced`.
- Render visual: 6 PNG di `docs/flows/preview/` untuk pemeriksaan manual.

## Temuan yang perlu tindak lanjut

Ringkas; detail dan level bukti ada di `docs/flows/README.md` §10.

- **Keamanan:** tidak ada enforcement verifikasi email; ganti email tanpa re-auth membuka
  jalur ambil alih akun; login OAuth tanpa `session()->regenerate()`; panjang OTP hardcode 6;
  route recovery 2FA tanpa throttle; enumerasi akun lewat `check-email` (mengembalikan nama,
  email, `created_at`).
- **Inkonsistensi:** portal memakai slug tetapi halaman fill memakai UUID event; `/admin/reports`
  dan `/admin/events/{event}/scan` hanya redirect; `/admin/du/courses` mengembalikan JSON;
  kartu statistik scan tidak bertambah saat duplikat (perlu verifikasi definisi metrik).
- **Penghambat penulisan ulang oprec:** cabang recruitment live di scan global (controller,
  resolver, feed, export) dan limiter bernama `recruitment-export` yang dipakai export event.

## Data uji yang dibuat

Satu pendaftaran nyata di DB dev saat walkthrough: FormAnswer `01a21bad-6491-79c2-a043-cc8055a3a9e1`
(Design Systems Workshop, user `ahmad@student.dinus.ac.id`), `review_status=accepted`,
`registration_code=L394-5ZH3`, plus 1 baris `event_attendances` dari check-in manual.

## Batasan

Aksi destruktif tidak dijalankan (hapus/force delete/restore/hapus akun). Kamera scan, OAuth
nyata, dan pengiriman email tidak diuji end-to-end. Recruitment/oprec tidak diaudit.
