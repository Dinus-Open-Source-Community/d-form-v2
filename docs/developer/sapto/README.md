# Dokumentasi developer — Sapto

Folder ini berisi catatan perubahan dan desain yang dikerjakan oleh **Sapto** pada branch/sesi pengembangan dashboard & UI terkait.

## Isi folder

| File                                                     | Deskripsi                                                                                              |
| -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| [saptoChanges27-10-2026-DFORM-203.md](./saptoChanges27-10-2026-DFORM-203.md) | Pindahkan 7 validasi statis dari 9 controller ke Form Request; 4 kasus dinamis + 1 urutan precondition tetap di controller dengan alasan |
| [saptoChanges27-10-2026-DFORM-TBD.md](./saptoChanges27-10-2026-DFORM-TBD.md) | Revert kolom pratinjau live form builder; sidebar dashboard tidak dirender di halaman builder (editor + tab Jawaban) |
| [saptoBundleBaseline-DFORM-1487.md](./saptoBundleBaseline-DFORM-1487.md) | Baseline bundel (DFORM-1487): entry gzip 25,56 KiB, 58 page lazy + 227 chunk, tabel byte gzip per chunk, aturan komparabilitas untuk pengukuran ulang 15.8 |
| [saptoChanges24-10-2026-events.md](./saptoChanges24-10-2026-events.md) | Pencarian & paginasi `/events` + `/browse`: cache payload mentah, negative cache + lock, FULLTEXT, cursor, rate limit 429 ramah, index ronde-2 |
| [saptoChanges24-10-2026-DFORM-TBD.md](./saptoChanges24-10-2026-DFORM-TBD.md) | Optimasi auth: code splitting per halaman, lottie lazy, rate limit login/register, handler 429, font & favicon |
| [saptoChanges21-10-2026.md](./saptoChanges21-10-2026.md) | Aturan proyek (14 aturan baru), `CONTEXT.md`, stress harness k6, dataset 1 juta baris, benchmark query |
| [saptoChanges10-06-2026.md](./saptoChanges10-06-2026.md) | Bundle submissions, member dashboard, reusable routes, re-registrasi, password reset                   |
| [saptoChanges04-05-2026.md](./saptoChanges04-05-2026.md) | Konsolidasi M1–M4, hapus Livewire/Filament, publik event dari DB                                       |
| [saptoChanges19-04-2026.md](./saptoChanges19-04-2026.md) | Dashboard admin, halaman event, landing navbar, data seed, Docker dev                                  |

- Bagian **Stress harness: load test, query capture, report** (§10) di `saptoChanges21-10-2026.md`
  merinci `make stress-load`, artefak per run, dan cara baca `report.html`.

## Cara membaca (untuk tim)

1. Buka changelog **terbaru** (`saptoChanges27-10-2026-DFORM-TBD.md`) untuk perubahan terakhir.
2. Bagian **Ringkasan (TL;DR)** di setiap dokumen cocok untuk sync singkat.
3. Bagian **Checklist** di akhir dokumen bisa dipakai sebelum merge atau saat onboarding anggota baru.

## Hubungan dengan dokumen lain

- Perubahan backend terkait **`registered_count`**, validasi tanggal event, dan endpoint `registration-status` dijelaskan di [nafanChanges17-04-2026.md](../nafan/nafanChanges17-04-2026.md). Desain dashboard Sapto memanfaatkan data tersebut (misalnya statistik & list event).
- Dokumen instalasi dan struktur proyek tetap mengacu ke root `docs/` (misalnya `01-installation.md`, `02-directory-structure.md`).

Jika ada pertanyaan spesifik tentang komponen UI atau alur dashboard, rujuk ke file changelog di atas atau tanyakan langsung di channel tim.
