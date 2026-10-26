# Sapto Changes — 27 Oktober 2026 (DFORM-TBD)

Kolom **Pratinjau live** di form builder desktop. Muncul hanya bila baris builder cukup lebar; di bawah ambang itu tombol **Pratinjau** yang lama (modal `FormPreviewDialog`) tetap dipakai.

> **Nomor tiket:** `DFORM-TBD` — belum ada tiket Jira untuk pekerjaan ini (konteks worker hanya memuat `TASK-200`), jadi nama file dan baris Issue tetap `DFORM-TBD`. Ganti `<N>` di keduanya begitu nomor Jira terbit, sebelum merge.

| Kolom  | Isi                                                                                                    |
| ------ | ------------------------------------------------------------------------------------------------------ |
| Issue  | DFORM-TBD (Sokudo TASK-200)                                                                            |
| Commit | `ac4ca61` (implementasi) — perubahan putaran review pertama ada di worktree `task-200-implement-live-preview-column-in-form-builder-de`, di-commit oleh harness setelah review   |
| Branch | `sokudo/task-200-implement-live-preview-column-in-form-builder-de`                                     |
| Dasar  | TASK-200 (deskripsi tiket + `AGENTS.md`)                                                                |

---

## 1. Perubahan

| File | Isi |
| ---- | --- |
| `resources/js/components/modules/builder/FormPreviewBody.vue` **(baru)** | Body pratinjau responden hasil ekstraksi `FormPreviewDialog`: banner, judul/deskripsi, heading/paragraph/divider/image, semua input disabled, state bintang lokal, stub submit. Prop `variant: 'dialog' \| 'inline'`. Seluruh teks responden diambil dari satu peta `PREVIEW_COPY` per varian. |
| `resources/js/components/modules/builder/FormPreviewDialog.vue` | Chrome/backdrop/header/footer tetap; body memakai `FormPreviewBody`. Perilaku modal tidak berubah. |
| `resources/js/components/modules/builder/FormBuilderLivePreview.vue` **(baru)** | Kolom kanan 360 px (`data-testid="form-builder-live-preview"`), border kiri, scroll sendiri, header `Pratinjau` + badge `Live`, isi dibungkus frame ala ponsel (`rounded-2xl border bg-card`). Visibilitas murni dari `v-if` pemanggil — tidak ada `hidden`/`lg:flex` di dalam komponen. |
| `resources/js/lib/formBuilderPreview.ts` **(baru)** | `FORM_BUILDER_LIVE_PREVIEW_MIN_WIDTH = 1000` + `shouldShowLivePreview(width)` (murni). |
| `resources/js/hooks/useFormBuilderLivePreview.ts` **(baru)** | `ResizeObserver` pada baris builder; `{ rootRef, livePreviewActive }`; guard `typeof ResizeObserver === 'undefined'` dan `disconnect()` di `onBeforeUnmount`. |
| `resources/js/components/modules/builder/FormBuilderWorkspace.vue` | Pasang `rootRef`, render kolom live setelah `<main>`, ekspos `livePreviewActive`, teruskan ke toolbar. |
| `resources/js/components/modules/builder/FormBuilderToolbar.vue` | Tombol Pratinjau `v-if="!livePreviewActive"` + `data-testid="form-builder-preview-trigger"`. Save tidak berubah. |
| `resources/js/pages/Dashboard/Events/Forms/Show.vue` | Tombol Pratinjau header `v-if="!builderLivePreviewActive"` + testid yang sama; Save All tetap dan tetap bersebelahan. |
| `resources/js/types/form-builder.ts` | `FormPreviewField` dipindah ke `types/` (dipakai 3 file; aturan folder: tipe lintas file wajib di `types/`). |
| `tests/e2e/form-builder-live-preview.spec.ts` **(baru)** | Dua skenario: (a) 1440×900 → kolom live tampil + tombol Pratinjau tidak ada + edit judul form langsung terlihat di kolom; (b) 1100×800 → kolom hilang, tombol Pratinjau tampil, klik membuka modal. Fixture dibuat lewat `tinker` (organizer + event + form + satu field). |
| `tests/unit/formBuilderPreview.test.ts` **(baru)** | Batas `shouldShowLivePreview`: 999 / 1000 / 1001 + keselarasan dengan konstanta. |
| `tests/unit/useFormBuilderLivePreview.test.ts` **(baru)** | Composable dengan `ResizeObserver` stub: aktif di atas ambang, mati di bawah ambang, `disconnect()` saat unmount, aman tanpa `ResizeObserver`. |
| `tests/unit/alias-loader.mjs` **(baru)** | Hook `resolve` Node untuk alias `@/` (Vite-only) supaya modul aplikasi bisa diimpor `node --test` tanpa bundler. |
| `package.json` | Script `test:unit` (`node --import ./tests/unit/alias-loader.mjs --test tests/unit/*.test.ts`); tanpa dependency baru. |

### Keputusan putaran review pertama

1. **Satu sumber kebenaran visibilitas.** `hidden … lg:flex` dihapus dari `FormBuilderLivePreview`; kolom hanya hidup dari `v-if="livePreviewActive"`. Sebelumnya ada state di mana kolom ter-render tapi CSS-hidden bersamaan dengan semua tombol Pratinjau tersembunyi.
2. **Bahasa panel tidak campur.** Sebelumnya empty state `inline` berbahasa Indonesia sementara label lain (`Untitled form`, `Choose an option`, `Submit`) berbahasa Inggris. Karena tiket mewajibkan empty state Indonesia, seluruh teks varian `inline` dilokalisasi lewat `PREVIEW_COPY` (judul fallback, pilih opsi, hint rating, label unggah, `Batal`/`Kirim`). Varian `dialog` tetap 100 % Inggris — perilaku modal tidak berubah.
3. **Tanpa dependency test baru.** Repo belum punya runner unit (tidak ada `vitest`/`jest`/`@vue/test-utils`) dan `AGENTS.md` mewajibkan konfirmasi sebelum instal paket, jadi unit test memakai runner bawaan Node (`node --test`, type stripping) + renderer kustom Vue. `as ResizeObserverEntry` dipakai satu kali di test karena `ResizeObserverEntry` adalah tipe DOM yang tidak bisa dikonstruksi di Node — alasan tertulis: hanya `contentRect.width` yang dibaca composable.
4. **E2E menyajikan worktree, bukan checkout root.** Server terpisah dijalankan dari container aplikasi dengan worktree sebagai bind mount, sehingga angka di bawah berasal dari kode tiket ini.

## 2. Ambang lebar (aritmetika + hasil ukur)

| Komponen | Lebar |
| -------- | ----- |
| Palet kiri (`w-[260px]`, `lg` ke atas) | 260 |
| Kanvas minimum yang masih sehat | 320 |
| Kolom pratinjau live | 360 |
| Border kiri kolom | 1 |
| **Total minimum** | **941** → dibulatkan `1000` |

Baris builder = viewport − sidebar dashboard 256 − padding halaman `lg:px-8` (64) = **viewport − 320**.
Maka kolom live muncul pada viewport ≥ **1320 px**.

Diukur langsung di browser (bukan hitungan class) pada build worktree ini:

| Viewport | Lebar baris builder (ukur) | Kolom live | Tombol Pratinjau |
| -------- | -------------------------- | ---------- | ---------------- |
| 1600 px | 1270 px | tampil (360 px) | tidak ada |
| 1440 px | 1110 px | tampil (360 px) | tidak ada |
| 1100 px | 770 px | tidak ada | tampil, membuka modal |

Kanvas tidak menyusut di bawah 320 px pada ketiga lebar di atas, jadi konstanta `1000` tidak perlu diubah.

## 3. Verifikasi

Semua perintah dijalankan dari worktree ini.

**Unit test** — `npm run test:unit`:

```
> node --import ./tests/unit/alias-loader.mjs --test tests/unit/*.test.ts
ℹ tests 8
ℹ pass 8
ℹ fail 0
```

**E2E** — server worktree disiapkan lebih dulu (lihat §4), lalu:

```
E2E_BASE_URL=http://127.0.0.1:3001 npx playwright test tests/e2e/form-builder-live-preview.spec.ts --reporter=list
Running 2 tests using 1 worker
  ✓ 1 … desktop lebar menampilkan kolom pratinjau dan menyembunyikan tombol Pratinjau (2.4s)
  ✓ 2 … layar tab menyembunyikan kolom live dan tombol Pratinjau membuka modal (2.3s)
  2 passed (5.2s)
```

**Lint** — `npx eslint` pada 9 file `resources/js` yang disentuh: bersih (exit 0, tanpa warning).

**Call site** — `grep -rn "FormPreviewDialog" resources/js`: hanya `FormBuilderWorkspace.vue` (import + mount), tidak ada call site rusak.

**Bukti visual** — screenshot 1440 px (kolom live + `Batal`/`Kirim`, tanpa tombol Pratinjau) dan 1100 px (tanpa kolom, tombol `Pratinjau` di samping `Save All`) dilampirkan di komentar tiket.

**Belum diverifikasi:**

- Perilaku pada viewport sangat lebar/ultra-wide tidak diukur ulang; yang diuji 1100/1440/1600 px.
- `disconnect()` saat route berubah di aplikasi nyata hanya diuji lewat unit test (observer di-unmount), bukan lewat navigasi browser.
- Tampilan mobile/tablet di bawah 1024 px tidak diubah dan tidak diuji ulang di browser pada tiket ini.

## 4. Cara menyajikan worktree untuk e2e

Dev server `:3000` menyajikan checkout root, bukan worktree, jadi e2e dijalankan terhadap container kedua yang memakai image yang sama:

```bash
W=$(pwd)/.sokudo/worktrees/task-200-implement-live-preview-column-in-form-builder-de
# .env, vendor, database/seeders/Stress, dan public/build tidak ada di worktree (gitignored)
cp .env "$W/.env"
cp -r database/seeders/Stress "$W/database/seeders/Stress"
podman run -d --name d_form_task200 --entrypoint sh \
  --network d-form-v2_d-form-network -p 127.0.0.1:3001:8000 \
  -v "$W":/app:z -v "$(pwd)/vendor":/app/vendor:z \
  --env-file "$W/.env" -e DB_HOST=db -e REDIS_HOST=redis \
  -e APP_URL=http://127.0.0.1:3001 \
  localhost/d-form-v2_app:latest \
  -lc "cd /app && php artisan octane:frankenphp --host=0.0.0.0 --port=8000"
```

Catatan operasional: `public/build` dibangun sekali di dalam container (`npm run build` dengan volume `node_modules` musl), `public/hot` harus dihapus sebelum start agar Laravel memakai manifest hasil build, dan `bootstrap/ssr/ssr.js` dihapus agar render jatuh ke client-side.
