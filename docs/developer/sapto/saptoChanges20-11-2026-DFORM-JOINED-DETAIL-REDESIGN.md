# DFORM — Joined event detail redesign

| Issue | Commit |
| --- | --- |
| DFORM-JOINED-DETAIL-REDESIGN | Pending |

## Summary

`/joined/{event_segment}` dirombak mengikuti mockup desktop dan mobile: banner dengan fallback, judul plus chip status, empat kartu fakta, "Tentang acara", daftar formulir dengan status per form, dan kartu check-in hijau. Halaman hanya berisi konten; topbar dan sidebar tetap milik layout.

Keputusan produk yang dipakai:

- State "tidak ada form tersedia" memakai `EmptyState` yang sudah ada, dengan copy baru.
- Sel "Sesi" menampilkan durasi hari sebagai nilai dan token sesi sebagai keterangan.
- Setelah QR dipindai, QR dan kode manual hilang dari halaman detail; halaman detail pendaftaran tetap menampilkan QR.
- Kartu status pendaftaran dihapus; tombol "Detail pendaftaran" pindah ke baris formulir pendaftaran.
- "Form terkait" dihapus dari kartu check-in; kartu hanya menampilkan "Belum dipindai" sebelum scan.

## Perubahan

### Backend

- `UserEventPortalController`: `formSummary()` sekarang mengirim `purpose` (`registration` atau `other`), dan payload detail mengirim `checked_in_at` (dari `event_attendances.scanned_at` untuk jawaban aktif) serta `check_in_form_title`.
- Test baru: `test_event_portal_exposes_form_purpose_and_empty_check_in_before_scan`, `test_event_portal_exposes_check_in_time_after_scan`, `test_event_portal_labels_participant_forms_as_other_purpose`.

### Frontend

- `types/form.ts`: `IEventPortalFormSummary.purpose`.
- `lib/eventDetailSummary.ts`: `eventDurationLabel`, `compactDateRangeLabel`, `formPurposeLabel`, `eventRegistrationStatusLabel`, `FORM_ACCESS_STATUS_LABEL`, dengan 15 unit test.
- `components/modules/dashboard/events/`: `MemberEventHero`, `MemberEventFactGrid`, `MemberEventAboutCard`, `MemberFormAccessList`, `MemberCheckInCard`, `MemberTeamInvitationCard`.
- `pages/Dashboard/User/EventDetail.vue`: komposer tipis, dua kolom di desktop dan bertumpuk di mobile, aside menyusut saat tidak ada kartu.
- `skeletons/pages/UserEventDetailSkeleton.vue`: mengikuti hierarki baru.

### Alasan keputusan visual (R-31)

- Ikon fakta dan ikon baris formulir tanpa kotak latar: latar lingkaran hanya dekorasi dan membuat baris terasa berat.
- Ikon per konteks: kalender untuk jadwal, pin untuk lokasi, jam untuk sesi, uang untuk biaya, clipboard untuk formulir pendaftaran, balon pesan untuk survei.
- Satu skala spasi: kartu `gap-0 py-0` dengan header `px-5 pt-4 pb-3`, body `px-5 py-4`, tumpukan `gap-5` di halaman dan `gap-2.5` di dalam kartu.
- Label informasi ("Pendaftaran", "Survei") berupa teks biasa, bukan pill; chip hanya untuk status yang benar-benar state.
- Kategori acara tidak ditampilkan lagi karena hanya mengulang konteks dan terlihat seperti badge hiasan.
- Kartu check-in memakai tint `success` karena menandai status kehadiran, bukan dekorasi.

## Verifikasi

| Check | Command | Hasil |
| --- | --- | --- |
| Typecheck | `npm run typecheck` | exit 0 |
| ESLint | `npx eslint` pada file tersentuh | exit 0 |
| Vitest | `podman exec d_form_app npx vitest run` | 118 file, 1225 test passed |
| PHPUnit | `php artisan test tests/Feature/Dashboard/User/UserEventRegistrationPortalTest.php` | 23 passed, 269 assertions |
| Pint | `pint --test` dua file | PASS PSR 12 |
| E2E | `npx playwright test tests/e2e/joined-event-detail.spec.ts` | 7 passed |

Bukti browser pada `/joined/ai-and-machine-learning-summit`:

- Desktop: banner fallback, judul plus chip, empat fakta sejajar, kartu formulir dengan tombol "Detail pendaftaran" di baris pendaftaran, kartu check-in hijau dengan "Belum dipindai", QR, dan kode manual.
- Mobile 390 px: urutan bertumpuk, tanpa overflow (`document` dan `content` selisih 0).
- Setelah baris attendance dibuat: QR dan kode hilang, "Check-in berhasil" tampil dengan waktu; halaman `/registration` tetap menampilkan QR (`alt="Attendance QR code"`).

## Delivery Gate antislop

| Butir | Hasil |
| --- | --- |
| R-02 tanpa em dash | PASS: tidak ada karakter em dash pada file baru |
| R-03 mobile | PASS: E2E 360 px dan 390 px, overflow dokumen dan kontainer 0 |
| R-25 kontras | PASS: teks memakai token `foreground`, `muted-foreground`, `success` yang sudah lolos kontras |
| R-26 kontrol berfungsi | PASS: setiap tombol dan tautan menuju route nyata (`fill_url`, `/registration`, undangan) |
| R-27 state | PASS: loading skeleton, empty state `EmptyState`, status per form, error boundary dari layout |
| R-32 keyboard | PASS: E2E CTA terjangkau Tab dan `:focus-visible` |
| R-35 verifikasi | PASS: typecheck, lint, unit, feature, dan E2E dijalankan; klik-through terdokumentasi di atas |
| R-09/R-06 | PASS: tidak ada pill dekoratif, tidak ada eyebrow di atas H1, label memakai kalimat biasa |
| R-31 alasan | PASS: alasan visual dicatat di bagian atas |
| R-40 angka terukur | PASS: semua angka berasal dari perintah di tabel verifikasi |

## Di luar scope

- Data seed menandai "Feedback Survey" tanpa `metadata.purpose`, sehingga sistem menganggapnya form pendaftaran: label "Pendaftaran" dan status "Sudah pilih form lain" berasal dari data ini, bukan dari tampilan baru.
- Sisa fixture E2E (`e2e-joined-detail-*`) masih ada di database lokal dan dipakai hanya untuk verifikasi visual empty state.

## Permukaan baca deskripsi WYSIWYG

Deskripsi acara pada kartu "Tentang acara" memakai pembungkus `.member-event-description` dengan override CSS khusus, tanpa mengubah data HTML:

- Deskripsi memakai lebar penuh kartu (`width: 100%`, tanpa `max-width`), mengikuti lebar perangkat.
- H1/H2/H3 di dalam deskripsi diperkecil agar tidak bersaing dengan judul halaman.
- Blockquote tanpa latar, hanya garis kiri tipis dan teks muted, tidak miring.
- Tabel tanpa garis vertikal: header muted, pemisah baris, sudut membulat, `min-width` sel dihapus agar muat di 390 px (terukur 301 px di dalam 370 px).
- Kode blok memakai latar muted, radius 12 px, tanpa shadow; gambar tanpa border dan shadow.
- Kata dan URL panjang membungkus (`overflow-wrap: anywhere`); kode blok memakai `overflow-x: auto` sehingga hanya area kode yang bisa digeser di layar kecil, bukan halaman.

CSS dasar `.dform-rich-text` tidak diubah, sehingga editor TipTap dan permukaan lain tetap memakai aturan lama.

Bukti: `podman exec d_form_app npm run build` exit 0; browser 1440, 1024, 768, 390, dan 360 px, overflow dokumen dan kontainer 0. Lebar deskripsi mengikuti kartu (1058 px di 1440, 642 px di 1024, 658 px di 768, 301 px di 390, 271 px di 360).

## Audit performa dan Lighthouse

Baseline pengguna di `docs/reports/lighthouse/before/` tidak valid sebagai pembanding produksi: 219 request ke `localhost:5173` dan node `phpdebugbar` di DOM. Audit ulang dilakukan pada build produksi dengan debugbar dimatikan.

Skor akhir (mobile / desktop):

| Halaman | Performance | A11y | Best Practices | SEO |
| --- | --- | --- | --- | --- |
| `/` | 85 / 100 | 100 / 100 | 100 / 100 | 100 / 100 |
| `/events` | 90 / 99 | 100 / 100 | 100 / 100 | 100 / 100 |
| `/browse` | 99 / 100 | 100 / 100 | 100 / 100 | 100 / 100 |
| `/joined/{event}` | 97 / 100 | 100 / 100 | 100 / 100 | 100 / 100 |
| `/admin/events` | 99 / 100 | 100 / 100 | 100 / 100 | 58 / 58 (noindex) |

Optimasi yang diterapkan:

- Analytics eksternal hanya dimuat di luar `local`/`testing`.
- Poppins di-host sendiri dengan preload 400/600/700; stylesheet Google Fonts dihapus.
- Wordmark di-preload dan `fetchpriority="high"`.
- `LocalLottie` menunda animasi sampai idle.
- Kontras badge kategori, badge status sorotan, dan label grup sidebar diperbaiki.
- `aria-label` tautan profil dihapus agar cocok dengan teks terlihat.
- Heading kartu menjadi `h2`; fakta memakai `dt`/`dd` valid.
- Meta description untuk `/browse` dan `/joined/{event}`.

Ringkasan lengkap: `docs/reports/lighthouse/after/summary.md`.
