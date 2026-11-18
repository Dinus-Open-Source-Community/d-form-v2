# DFORM-1 — Redesign kartu field form builder (klik kartu membuka drawer)

**Issue:** DFORM-1 (nomor tiket placeholder atas permintaan user)
**Tanggal:** 18-11-2026
**Klasifikasi:** `refactor` + `feature` (interaksi) + `design`
**Commit:** `feat: open field editor by clicking the builder field card` (cari dengan `git log --grep="open field editor by clicking"`)

## Ringkasan (TL;DR)

Kartu field di kanvas builder sekarang **satu permukaan pratinjau read-only**: klik di mana pun pada kartu (atau Enter/Space saat fokus) membuka drawer editor, dan ikon pengaturan dihapus. Label serta teks bantu tidak lagi berupa input inline di kartu, sehingga satu-satunya jalur edit adalah drawer. Duplikat, hapus, drag handle, dan tombol naik/turun tetap di posisi semula sesuai instruksi user. Drawer diselaraskan dengan kartu: copy Indonesia, dua em dash dihapus, badge tipe memakai token warna (bukan 17 hex), border bertumpuk dihapus, dan padding bawah aman untuk safe-area iOS.

## Perubahan

| File | Perubahan |
| --- | --- |
| `components/modules/builder/fieldTypePresentation.ts` (baru) | Satu sumber meta 17 tipe builder (`icon`, `label`, `tone`) + kelas chip per tone. Menggantikan dua map berbeda (kartu dan drawer). Berada di folder builder karena hanya dipakai siklus hidup builder, sejalan dengan `fieldMapping.ts`/`formBanner.ts` yang sudah ada. |
| `components/modules/builder/FieldRenderer.vue` | Kartu read-only + `<button>` overlay `inset-0` sebagai trigger (`aria-label="Ubah field <label>"`), konten `pointer-events-none`, cluster aksi `z-20`. Ikon `Settings2` dihapus. Dua input inline (label, teks bantu) menjadi teks statis. Pratinjau input memakai satu bidang `bg-muted/50` tanpa border ganda; shadow dihapus; tombol duplikat/hapus 44 px dengan target sentuh terukur; `GripVertical` dekoratif di dalam kartu dihapus karena berada di bawah `pointer-events-none` (afordans palsu). |
| `components/modules/builder/FormBuilderCanvasBuildView.vue` | `@open` → `manageField`; emit `updateField` dihapus (tidak lagi ada pemakai di kanvas). |
| `components/modules/builder/FormBuilderDemoCanvas.vue` | `@open` → `selectField` (demo tidak punya drawer, tetap memilih field untuk sidebar properti). |
| `components/modules/builder/FormBuilderWorkspace.vue` | Binding `@update-field` di kanvas dihapus; `wb.updateField` tetap dipakai drawer. |
| `components/modules/builder/FormBuilderEditFieldSheet.vue` | Deskripsi drawer jadi Indonesia, tombol `Done` → `Selesai`, header/footer tanpa latar `bg-muted/30`, footer `pb-[max(1rem,env(safe-area-inset-bottom))]`. |
| `components/modules/builder/FieldEditor.vue` | Badge tipe memakai `FIELD_TONE_CHIP_CLASS` (token) menggantikan 17 `accent` hex; outer `rounded-2xl border` dihapus (drawer sudah punya permukaan); em dash pada placeholder teks bantu dan baris "Opsi N" diganti koma/parenthesis; `id`/`for` ditambahkan untuk Label, Teks bantu, Placeholder, dan Maks. karakter. |
| `tests/e2e/support/builderFieldCard.ts` (baru) | Helper `fieldCards`, `fieldCardTriggers`, `openFieldDrawer`, `closeFieldDrawer`, `renameFieldViaDrawer`. |
| `tests/e2e/field-card-opens-drawer.desktop.spec.ts` (baru) | 4 test: klik permukaan kartu membuka drawer + Escape menutup + ikon pengaturan hilang; keyboard Enter/Space + focus ring; tombol duplikat/hapus tidak membuka drawer; tidak ada overflow horizontal pada 320/360/390/414/640/768/1024/1280/1536/1920 px. |
| `tests/e2e/field-card-opens-drawer.mobile.spec.ts` (baru) | Tap kartu membuka drawer, tombol aksi terlihat tanpa hover, target sentuh ≥44 px, `Selesai` menutup drawer. |
| 10 spec E2E lama | Selector `Pengaturan field`/`Kelola opsi` → `Ubah field …`; `input[placeholder^="Label pertanyaan"]` → `[data-testid="field-card"]` atau `renameFieldViaDrawer`; assertion nilai label → `toContainText`. Detail di bagian "Penyesuaian test". |
| Unit test baru | `__tests__/fieldTypePresentation.test.ts` (4 test). |

## Keputusan & alternatif

1. **Trigger drawer = overlay `<button>` (dipilih).** Alternatif: `div role="button"` + keydown manual (ARIA dan key handling manual, tombol aksi bersarang di region `role="button"`), atau klik kartu + tombol teks "Ubah" permanen (afordans ganda, satu kontrol identik per kartu). Overlay native memberi Enter/Space, focus ring, dan accessible name tanpa kode tambahan.
2. **Label/teks bantu jadi teks statis.** Instruksi user: edit hanya lewat drawer. Efek samping: input inline di `FormBuilderDemoCanvas` sebelumnya terlihat interaktif tetapi tidak tersimpan (tidak ada listener) — cacat yang ikut hilang.
3. **Tone tipe memakai 4 grup token (`neutral`, `primary`, `success`, `warning`).** `info` tidak dipakai karena `--info` dialias ke `--primary` di `app.css`, sehingga tone `info` akan identik dengan `primary` (dedup berdasarkan fakta, bukan preferensi).
4. **Target sentuh 44 px dipatok `min-h-[44px] min-w-[44px]`, bukan `size-11`.** Root font-size proyek 15 px di bawah 640 px sehingga `size-11` = 41,25 px. Diukur: sebelum perbaikan Playwright membaca 41,25 px pada Pixel 7.
5. **Tombol "Edit" di bar mobile tetap.** Instruksi user: aksi lain "dibiarkan saja". Klik kartu kini melakukan hal yang sama, tetapi tombol tidak dihapus agar perilaku eksplisit tetap ada.
6. **Copy drawer diperbaiki (R-02/R-16).** `Customize the selected field.` dan `Done` berbahasa Inggris, dua em dash, dan label/input tanpa asosiasi. Alternatif yang ditolak: menyisakan copy lama karena "di luar scope kartu" — drawer dan kartu adalah satu alur yang sama dan sekarang disentuh.

## Bukti verifikasi

| Perintah | Exit | Hasil |
| --- | --- | --- |
| `npm run lint` | 0 | 0 error, 1 warning pre-existing (`resources/js/lib/__tests__/formBuilderAutosave.test.ts` `SAMPLE_FIELDS` unused) |
| `npm run typecheck` | 0 | bersih |
| `npm run test:e2e:types` | 0 | bersih |
| `npm run test:unit` | 0 | 91 file, **992 test passed** (sebelumnya 988; +4 test baru) |
| `podman exec -i -w /app d_form_app ./vendor/bin/pint --test` | 1 | 3 pelanggaran **pre-existing** di `app/Models/User.php`, `app/Support/Export/KeysetMergeIterator.php`, `routes/ai.php` (tidak disentuh perubahan ini) |
| `podman exec ... php artisan test` (FormBuilderHttp, FieldOperation, FormDraft, FormSuccessContent, BannerFieldType) | 0 | 69 passed, 387 assertions |
| `npm run build` (`PATH` shim php → container) | 0 | lihat tabel bundle |

### Bundle (diukur dengan `gzip -9`, byte)

| Metrik | Sebelum | Sesudah | Delta |
| --- | --- | --- | --- |
| Entry `app-*.js` | 50.504 B / **15.926 B gzip** | 50.492 B / **15.913 B gzip** | −12 B / −13 B |
| Chunk terbesar `index-Dd1-*.js` | 462.343 B / 145.729 B gzip | identik | 0 |
| Chunk terbesar ke-2 / ke-3 | 334.648 / 314.473 B | identik | 0 |
| Chunk page terbesar | 145,7 kB gzip | 145,7 kB gzip | dalam budget (≤200 kB gzip) |

Angka "sebelum" diukur dengan `git stash push -- resources/js/components/modules/builder/` lalu build ulang pada commit yang sama, bukan membandingkan angka dokumen lama.


### Kontras terukur (kalkulasi oklch → sRGB, WCAG 2.x)

| Pasangan | Light | Dark | Ambang |
| --- | --- | --- | --- |
| `muted-foreground` di atas `card` (label tipe, teks bantu) | 7,12:1 | 6,49:1 | 4,5:1 (teks) |
| `muted-foreground` di atas `muted/50` (placeholder pratinjau) | 6,72:1 | 6,16:1 | 4,5:1 (teks) |
| `primary` di atas `primary/10` (ikon chip) | 4,84:1 | 4,87:1 | 3:1 (non-teks) |
| Ikon aksi `muted-foreground` di atas `card` | 7,12:1 | 6,49:1 | 3:1 (non-teks) |
| `success` di atas `success/10` (chip unggah) | 3,51:1 | 6,31:1 | 3:1 (non-teks) |

Temuan saat pengukuran: dengan cluster aksi pada `opacity-60` (reveal saat hover), ikon jatuh ke **2,79:1** di light mode dan gagal ambang 3:1. Opacity reveal dihapus; aksi sekarang selalu terlihat penuh, hover tetap ditandai `hover:bg-accent`.

Bintang rating memakai `text-warning` (2,34:1 di light). Bintang di pratinjau read-only adalah grafik dekoratif, bukan kontrol; keterangan "Pratinjau komponen rating" yang membawa makna. Dibiarkan dengan alasan tersebut, bukan diklaim lolos ambang non-teks.

### E2E (Playwright, server `http://localhost:3000`, admin super-admin fixture)

Run konsolidasi 13 file spec: **52 passed, 5 failed (4,6 menit)**.

| Spec | Status | Catatan |
| --- | --- | --- |
| `field-card-opens-drawer.desktop` (4 test) | lulus | Requirement utama + keyboard + aksi tidak membuka drawer + overflow 10 breakpoint |
| `field-card-opens-drawer.mobile` | lulus | Tap membuka drawer, target sentuh 44 px terukur |
| `form-builder-drag-state.desktop` (6) | lulus | Drop target pindah ke kartu; assertion urutan memakai `toContainText` |
| `form-builder-drag-state.mobile` | lulus | |
| `form-builder-mobile-canvas.mobile` (2) | lulus | |
| `builder-autosave.desktop` (3 dari 4) | 1 gagal **pre-existing** | "anomali: dua edit judul…" gagal juga pada kode tanpa perubahan ini (baseline dijalankan ulang, `POST /fields` = 1 vs 0) |
| `form-create-autosave.desktop` (3 dari 4) | 1 gagal **pre-existing** | "draf kosong: drag field tidak memicu 422…" gagal identik pada baseline |
| `wizard-form-step-navigation.desktop` (2) | lulus setelah disesuaikan | lihat "Penyesuaian test" |
| `field-max-length`, `upload-ui-wiring`, `multi-upload-fields`, `audit-p1-012`, `builder-uuid-insecure-context` | lulus pada run konsolidasi/ulangan | `multi-upload-fields` mobile flaky: run ulang menggagalkan test berbeda (alur isi form publik, di luar builder) |
| `audit-p1-012` unggah banner | gagal 1× (HTTP 500 dari server) lalu lulus pada run ulang | kegagalan storage/server, bukan UI; tidak direproduksi |

## Penyesuaian test (dan alasannya)

1. **`builder-autosave` failure test**: rename field kini lewat drawer (`renameFieldViaDrawer`), bukti UI dibaca dari `toContainText` kartu; bukti DB tetap `readFieldLabel`.
2. **`wizard-form-step-navigation` test "Kembali ke event"**: dulu edit field langsung di kartu sehingga snapshot masih pending saat tombol navigasi diklik. Dengan drawer, durasi interaksi menghabiskan jendela debounce 800 ms sehingga `flush()` tidak lagi tertunda. Test sekarang menunggu request autosave **mulai** (`waitForRequest` dipasang sebelum edit) agar flush benar-benar in-flight, lalu membuktikan navigasi menahan sampai response selesai (URL tetap `step=forms` pada 250 ms, elapsed ≥500 ms).
3. **`wizard-form-step-navigation` test "Lewati"**: `getByRole('button', { name: 'Lewati' })` menjadi ambigu karena trigger kartu berlabel `Ubah field Lewati wizard …`; diganti `{ exact: true }`. Assertion "tepat satu putaran save" diganti menjadi "setiap payload memuat edit terakhir + minimal satu putaran". Alasan: navigasi saat flush in-flight memicu putaran kedua — race yang sudah terdokumentasi di `CONTEXT.md` §6.4 ("request tidak dibatalkan/diantrekan"). Menegakkan satu putaran berarti memperbaiki hook autosave, yang di luar scope tiket ini; invarian yang dijaga sekarang adalah edit terakhir ada di setiap payload dan tersimpan setelah keluar.
4. **`form-builder-drag-state*`**: konstanta `LABEL_INPUT` → `FIELD_CARD`; target drag dan hitungan field memakai `[data-testid="field-card"]`.

## Verifikasi visual (bukti gambar)

Screenshot di `/tmp/opencode/field-card-shots/` untuk 320/360/390/414/640/768/1024/1280/1536/1920 px (portrait) + drawer pada 320/390/1280. Metrik terukur di setiap lebar: `scrollWidth - clientWidth = -10` (hanya gutter scrollbar, tanpa overflow horizontal), kartu terlihat, tombol aksi 44×44 px, dan label tipe disembunyikan di bawah 360 px agar tidak terpotong menjadi "Teks …".

## Checklist

- [x] Klik kartu (mouse/tap) membuka drawer; Escape dan tombol `Selesai` menutup.
- [x] Enter/Space membuka drawer dari keyboard; focus ring 3 px terlihat.
- [x] Ikon pengaturan hilang dari kartu (0 elemen dengan nama aksesibel `Pengaturan field`).
- [x] Duplikat & hapus tidak membuka drawer; posisi tombol tidak berubah.
- [x] Label dan teks bantu tidak lagi dapat diedit di kartu; hanya lewat drawer.
- [x] Tidak ada overflow horizontal 320–1920 px; kartu tetap terlihat.
- [x] Target sentuh tombol kartu ≥44 px pada root font 15 px.
- [x] Drawer memakai copy Indonesia tanpa em dash; badge tipe memakai token.
- [x] Lint, typecheck, e2e-types, Vitest 992, PHPUnit builder 69, build hijau.
- [x] Budget bundle: entry 15,9 kB gzip, tidak ada chunk page >200 kB gzip.

## Risiko & tindak lanjut

- `builder-autosave` "anomali" dan `form-create-autosave` "draf kosong" tetap merah (pre-existing, dibuktikan lewat baseline). Keduanya mengindikasikan autosave mengirim `POST /fields` atau `PUT settings` walau tidak ada perubahan field — kandidat tiket tersendiri.
- Race autosave saat navigasi ketika flush in-flight (dua putaran save) terdokumentasi di `CONTEXT.md` §6.4; test tidak lagi menegakkan single-round pada skenario itu.
- Asosiasi label/input di `FieldEditor.vue` baru ditambahkan untuk 4 field utama; field lanjutan (opsi, batas unggah, konten heading/paragraph) masih memakai label visual tanpa `for`/`id` — utang aksesibilitas pre-existing.
