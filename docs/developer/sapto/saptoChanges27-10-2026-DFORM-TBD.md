# Sapto Changes — 27 Oktober 2026 (DFORM-TBD)

Kolom **Pratinjau live** di form builder desktop. Muncul hanya bila baris builder cukup lebar; di bawah ambang itu tombol **Pratinjau** yang lama (modal `FormPreviewDialog`) tetap dipakai.

> **Nomor tiket:** `DFORM-TBD` — belum ada tiket Jira. Ganti `<N>` pada nama file dan baris ini sebelum push/PR.

| Kolom  | Isi                                                       |
| ------ | --------------------------------------------------------- |
| Issue  | DFORM-TBD (Sokudo TASK-200)                               |
| Commit | belum di-commit — worktree `task-200-implement-live-preview-column-in-form-builder-de` |
| Branch | `sokudo/task-200-implement-live-preview-column-in-form-builder-de` |
| Dasar  | TASK-200 (deskripsi tiket + `AGENTS.md`)                  |

---

## 1. Perubahan

| File | Isi |
| ---- | --- |
| `resources/js/components/modules/builder/FormPreviewBody.vue` **(baru)** | Body pratinjau responden hasil ekstraksi `FormPreviewDialog` apa adanya: banner, judul/deskripsi, heading/paragraph/divider/image, semua input disabled, state bintang lokal, stub submit. Prop `variant: 'dialog' \| 'inline'` hanya mengatur padding + copy empty state (Indonesia untuk `inline`). |
| `resources/js/components/modules/builder/FormPreviewDialog.vue` | Chrome/backdrop/header/footer tetap; body memakai `FormPreviewBody`. Perilaku modal tidak berubah. |
| `resources/js/components/modules/builder/FormBuilderLivePreview.vue` **(baru)** | Kolom kanan 360 px (`data-testid="form-builder-live-preview"`), border kiri, scroll sendiri, header `Pratinjau` + badge `Live`, isi dibungkus frame ala ponsel (`rounded-2xl border bg-card`). |
| `resources/js/lib/formBuilderPreview.ts` **(baru)** | `FORM_BUILDER_LIVE_PREVIEW_MIN_WIDTH = 1000` + `shouldShowLivePreview(width)` (murni). |
| `resources/js/hooks/useFormBuilderLivePreview.ts` **(baru)** | `ResizeObserver` pada baris builder; `{ rootRef, livePreviewActive }`; guard `typeof ResizeObserver === 'undefined'` dan `disconnect()` di `onBeforeUnmount`. |
| `resources/js/components/modules/builder/FormBuilderWorkspace.vue` | Pasang `rootRef`, render kolom live setelah `<main>`, ekspos `livePreviewActive`, teruskan ke toolbar. |
| `resources/js/components/modules/builder/FormBuilderToolbar.vue` | Tombol Pratinjau `v-if="!livePreviewActive"` + `data-testid="form-builder-preview-trigger"`. Save tidak berubah. |
| `resources/js/pages/Dashboard/Events/Forms/Show.vue` | Tombol Pratinjau header `v-if="!builderLivePreviewActive"` + testid yang sama; Save All tetap. |
| `resources/js/types/form-builder.ts` | `FormPreviewField` dipindah ke `types/` (dipakai 3 file; aturan folder: tipe lintas file wajib di `types/`). |

Alasan keputusan: `FormPreviewField` tidak dideklarasikan di `FormPreviewBody.vue` seperti bunyi step 1 tiket, karena aturan folder proyek mewajibkan tipe lintas file di `resources/js/types/`. Tetap satu definisi, dua pemakai mengimpor dari `types/`.

## 2. Ambang lebar (aritmetika)

| Komponen | Lebar |
| -------- | ----- |
| Palet kiri (`w-[260px]`, `lg` ke atas) | 260 |
| Kanvas minimum yang masih sehat | 320 |
| Kolom pratinjau live | 360 |
| Border kiri kolom | 1 |
| **Total minimum** | **941** → dibulatkan `1000` |

Baris builder = viewport − sidebar dashboard 256 − padding halaman `lg:px-8` (64) = **viewport − 320**.
Maka kolom live muncul pada viewport ≥ **1320 px**; pada 1600 px baris ≈ 1280 px (kanvas ≈ 660 px), pada 1100 px baris ≈ 780 px sehingga tombol Pratinjau modal yang tampil. Kolom live dan tombol Pratinjau tidak pernah tampil bersamaan karena keduanya membaca satu state `livePreviewActive`.

## 3. Verifikasi

- `npx eslint` pada 9 file yang disentuh: bersih (exit 0).
- `tsc --noEmit` (config sementara) untuk `lib/`, `hooks/`, `types/`: bersih.
- Kompilasi SFC (`vue/compiler-sfc`: parse + `compileScript` + `compileTemplate`) untuk 6 file `.vue`: OK.
- `grep -rn "FormPreviewDialog" resources/js`: hanya `FormBuilderWorkspace.vue` (import + mount), tidak ada call site rusak.

**Belum diverifikasi:** perilaku visual di browser (1600 px / 1100 px) — dev server di `:3000` menyajikan checkout root, bukan worktree ini, jadi e2e Playwright tidak bisa menguji perubahan ini tanpa menyajikan worktree. Angka 1320 px di atas adalah hitungan class Tailwind, bukan hasil ukur.
