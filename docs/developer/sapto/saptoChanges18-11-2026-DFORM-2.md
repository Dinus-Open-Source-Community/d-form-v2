# DFORM-2 — Redesign dialog "Bagikan formulir"

**Issue:** DFORM-2 (nomor tiket placeholder atas permintaan user)
**Tanggal:** 18-11-2026
**Klasifikasi:** `refactor` + `design`
**Commit:** `refactor: regroup share dialog by audience with inline copy`

## Ringkasan (TL;DR)

Dialog `Bagikan formulir` dirombak tampilannya tanpa mengubah satu pun fungsi: tetap ada salin link peserta, generate/unduh/ulang/tutup barcode, buat & simpan alias short link, salin short link, salin link admin, proteksi draft, dan pesan error/sukses. Perubahan: dialog dibagi per audiens (Peserta, Short link, Admin), tombol salin pindah ke dalam field sebagai satu aksi per link, hanya ada **satu** tombol primary, barcode menempel pada blok peserta, konfirmasi salin berupa **toast** plus **ikon centang selama 1 detik** pada tombol yang ditekan, dan copy/pesan memakai token warna sistem.

## Perubahan

| File | Perubahan |
| --- | --- |
| `components/modules/dashboard/FormShareDialog.vue` | Header: `DialogTitle` "Bagikan formulir" + baris subjek nama form (`data-testid="share-form-title"`) + deskripsi yang menyebut isi dialog. Badge "Peserta" dihapus. Urutan blok: Peserta → Barcode (inline, tanpa garis pemisah) → Short link (bertanda "Opsional") → Admin. Copy tombol `Generate barcode` → `Buat barcode` agar satu bahasa dengan `Salin`, `Buat short link`, `Simpan alias`, `Unduh`, `Tutup`, `Selesai`. Tiga field link memakai `ShareLinkField`. Pesan error/sukses menjadi baris alert berbingkai dengan ikon; teks memakai `text-foreground`, ikon memakai `text-destructive`/`text-success`. Footer: satu tombol `Selesai` outline, rata kanan di ≥sm. `@open-auto-focus` mengarahkan fokus awal ke judul, bukan ke field readonly. |
| `components/modules/dashboard/ShareLinkField.vue` (baru) | Satu komponen untuk tiga field link: `InputGroup` + input readonly mono + `InputGroupButton` di dalam field (`Salin`). Ikon bertukar `Copy` → `Check` (fade + scale 150 ms) selama `confirmed`, lalu kembali; `data-copy-feedback="confirmed|idle"` menjadi kontrak test. Props: `value`, `fieldLabel`, `copyLabel`, `testId`, `disabled`, `primary`, `confirmed`. |
| `hooks/useCopyFeedback.ts` (baru) + `hooks/__tests__/useCopyFeedback.test.ts` | State umpan balik salin selama 1 detik (`COPY_FEEDBACK_DURATION_MS`), `trigger()` me-reset timer (tidak menumpuk), dan timer dibersihkan lewat `onScopeDispose`. 4 unit test dengan fake timer. |
| `components/modules/dashboard/FormShareDialog.vue` | Salin memakai `copyParticipantLink`/`copyShortLink`/`copyAdminLink`: bila aksi hook mengembalikan `true`, ikon tombol itu menjadi centang selama 1 detik dan `showSuccessToast(...)` (seam `useToastNotifications`, `id: 'form-share-copy'`) menampilkan toast yang menggantikan toast sebelumnya. Live region `sr-only` lama dihapus karena pengumuman ditangani `AppToaster`. |
| `tests/e2e/form-share-dialog-redesign.desktop.spec.ts` (baru) | 4 test: struktur & hierarki (heading, subjek, tepat satu `button[data-variant="default"]`, semua kontrol terlihat bersamaan), konfirmasi salin berupa toast yang tidak menumpuk + ikon `confirmed` yang kembali `idle` setelah ≥800 ms, keyboard Enter + focus ring, serta tinggi target sentuh 44 px dan nol overflow di 320/360/390/768/1280 px. |

Tidak ada perubahan pada `hooks/useFormShareActions.ts`, `types/formShare.ts`, route, controller, atau `components/ui/**`.

## Keputusan & alternatif

1. **Grup audiens dalam satu scroll (dipilih)** vs tab segmented: tab memendekkan scroll tetapi menyembunyikan kontrol (E2E draft-state mengassert lima kontrol sekaligus) dan menambah klik untuk aksi yang jarang dipakai. vs daftar baris ikon-saja: lebih ringkas tapi melemahkan discoverability dan tidak memuat form alias.
2. **Salin inline di dalam field** (memakai `InputGroup` yang sudah dipakai `core/input/EmailInput.vue`) menghapus tiga tombol selebar dialog dan menempatkan aksi tepat di sebelah nilainya.
3. **Hanya satu aksi primary**: `Salin link peserta`. `Buat short link`/`Simpan alias` menjadi outline karena bersifat utilitas; salin admin/short link menjadi outline karena sekunder.
4. **Badge "Peserta" dihapus** karena hanya mengulang judul blok dan tidak menandai status (R-09). Sebagai gantinya, label "Opsional" pada Short link tetap ada karena menandai properti nyata.
5. **Konfirmasi salin = toast + ikon centang 1 detik.** Toast memakai seam `useToastNotifications` (satu-satunya jalur `vue-sonner`) dengan `id` tetap sehingga salin berulang menggantikan toast, bukan menumpuk. Ikon centang memberi umpan balik lokal tepat di tombol yang ditekan, dan hanya menyala bila aksi hook mengembalikan `true` (salin gagal tidak menampilkan centang). Teks tombol tetap "Salin" sehingga accessible name dan aturan "label in name" utuh.
6. **Fokus awal ke judul** (pola yang sama dengan `FormBuilderEditFieldSheet`): auto-focus reka-ui jatuh ke field readonly pertama dan memunculkan ring biru pada elemen yang bukan aksi.
7. **Teks alert memakai `text-foreground`, ikon yang berwarna**: `text-destructive` di atas tint `destructive/5` terukur **4,39:1** (di bawah 4,5 untuk teks), sedangkan ikon hanya butuh 3:1.

## Bukti verifikasi

| Perintah | Exit | Hasil |
| --- | --- | --- |
| `npm run lint` | 0 | 0 error, 1 warning pre-existing (`resources/js/lib/__tests__/formBuilderAutosave.test.ts`) |
| `npm run typecheck` | 0 | bersih |
| `npx prettier --check` (3 file tersentuh) | 0 setelah `--write` | bersih |
| `npm run test:unit` | 0 | 92 file, **996 test passed** (4 test baru `useCopyFeedback`) |
| `npm run build` (php shim ke container) | 0 | entry `app-*.js` 50.546 B / **15.910 B gzip**; chunk terbesar tetap 145.729 B gzip |
| `npx playwright test form-share-dialog-redesign.desktop.spec.ts` | 0 | **4 passed** |
| `npx playwright test form-share-link.spec.ts` (desktop **dan** mobile) | 0 | **2 passed** — fungsi salin, alias, short link, barcode, proteksi draft, keyboard, dan fit mobile tetap utuh |

### Kontras terukur (oklch → sRGB, WCAG 2.x)

| Pasangan | Light | Dark | Ambang |
| --- | --- | --- | --- |
| `text-muted-foreground` di chip "Opsional" (`bg-muted`) | 6,34:1 | 5,82:1 | 4,5:1 (teks) |
| `text-foreground` di alert sukses (`success/5`) | 17,68:1 | 14,29:1 | 4,5:1 (teks) |
| Ikon `success` di alert sukses | 3,74:1 | 6,89:1 | 3:1 (non-teks) |
| `text-foreground` di alert error (`destructive/5`) | ≈15:1 | ≈13:1 | 4,5:1 (teks) |
| Ikon `destructive` di alert error | 4,39:1 | 5,62:1 | 3:1 (non-teks) |
| `text-primary` link unduh di `muted/20` | 5,45:1 | 5,47:1 | 4,5:1 (teks) |
| Ikon centang (`primary-foreground`) di tombol primary | 5,42:1 | 6,06:1 | 3:1 (non-teks) |

Catatan: border field memakai `--input` (kontras rendah) seperti seluruh input aplikasi ini, pre-existing, tidak diubah oleh tiket ini.

### Cursor pada kontrol baru

`getComputedStyle` pada tombol salin mengembalikan `cursor: default` (komponen `Button` bersama tidak membawa utilitas cursor, dan tidak ada aturan base-layer). Sesuai R-39, semua kontrol yang dibangun tiket ini mendapat `cursor-pointer` eksplisit: tombol salin, `Generate barcode`, `Buat short link`/`Simpan alias`, `Coba lagi`, `Selesai`, dan `Tutup barcode`. Perbaikan sistemik (satu aturan `button:not(:disabled)` di `@layer base`) sengaja tidak diambil karena mengubah seluruh aplikasi di luar tiket; dicatat sebagai rekomendasi tiket terpisah. Kontrol disabled tetap memakai `disabled:pointer-events-none` dari komponen `Button` bersama, sehingga `cursor: not-allowed` tidak dapat diterapkan tanpa menyentuh `components/ui/**`.

### Metrik responsif terukur (dialog terbuka)

| Lebar | Overflow | Lebar dialog | Tombol primary |
| --- | --- | --- | --- |
| 320 px | −10 px (gutter scrollbar) | 280 px | 68×44 px |
| 360 px | −10 | 320 px | 68×44 |
| 390 px | −10 | 350 px | 68×44 |
| 768 px | −10 | 576 px | 73×32 |
| 1280 px | −10 | 576 px | 73×32 |

Screenshot: `/tmp/opencode/share-dialog-shots/` (`dialog-{320,360,390,768,1280}.png`, `dialog-390-copied.png`, `dialog-390-barcode.png`).

## Checklist

- [x] Semua fungsi lama bekerja: salin peserta/admin/short link, buat & simpan alias, barcode generate/unduh/retry/tutup, proteksi draft, pesan error/sukses.
- [x] Nama aksesibel tidak berubah, sehingga kontrak E2E lama tetap sahih.
- [x] Tepat satu aksi primary; hierarki audiens jelas (Peserta → Short link → Admin).
- [x] Konfirmasi salin: toast muncul (tidak menumpuk) dan ikon tombol bertukar ke centang lalu kembali ke ikon salin setelah 1 detik.
- [x] Keyboard: Enter mengaktifkan tombol salin, focus ring terlihat; fokus awal di judul.
- [x] Target sentuh 44 px di mobile; tanpa overflow 320–1280 px.
- [x] Tanpa em dash pada teks UI baru; copy Indonesia spesifik.
- [x] `cursor: pointer` eksplisit pada semua kontrol baru (terverifikasi lewat `getComputedStyle`).
- [x] Animasi tukar ikon 150 ms dengan `motion-reduce:transition-none`; tanpa loop.
- [x] Lint, typecheck, unit 992, build, dan E2E hijau (desktop + mobile).

## Risiko & tindak lanjut

- Ikon centang memakai `currentColor` agar kontrasnya mengikuti tombol (putih di tombol primary, `foreground` di outline); makna "sukses" dibawa toast, bukan warna ikon.
- Border field berkontras rendah mengikuti token sistem (`--input`), isu pre-existing lintas aplikasi.
- Warning pre-existing `SAMPLE_FIELDS` di `formBuilderAutosave.test.ts` dan 3 pelanggaran Pint di file backend tidak terkait tiket ini.
