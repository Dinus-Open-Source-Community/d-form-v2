# Sapto Changes — 29 Oktober 2026 (DFORM-039)

## Ringkasan

P0-1 diperbaiki: di halaman Create form, menyembunyikan judul toolbar tidak lagi ikut menyembunyikan tombol Simpan dan Pratinjau. Tiga baris P0-1 yang ditulis merah di TASK-036 sekarang hijau di desktop, dan form hanya terkirim satu kali walaupun tombol simpan diklik dua kali saat request masih berjalan.

| Issue | Commit |
| ----- | ------ |
| TASK-039 | `fix(builder): pisahkan visibilitas judul toolbar dari tombol simpan` |

Branch: `task-036-write-and-run-the-p0-1-scenarios-red`, di atas commit merah `a9b2537`.

## Akar masalah (bukti, bukan gejala)

- `resources/js/pages/Dashboard/Events/Forms/Create.vue:97` mengirim `:hide-toolbar-titles="true"`.
- `FormBuilderToolbar.vue` memakai `hideTitles` di **tiga** `v-if` tingkat wadah: baris 41 (wadah non-teleport), 98 (teleport kiri), dan 118 (teleport kanan). Tombol Pratinjau/Simpan hidup di dalam dua wadah terakhir, jadi keduanya hilang bersama judul.
- Satu-satunya tombol simpan yang tersisa adalah blok mobile di `FormBuilderWorkspace.vue` dengan pembungkus `sm:hidden`, sehingga di lebar desktop tidak ada kontrol simpan sama sekali. Dibuktikan tangkapan layar merah TASK-036: draft lengkap dan valid, nol tombol simpan.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/components/modules/builder/FormBuilderToolbar.vue` | Tiga `v-if` wadah hanya memeriksa `hideToolbar`. Blok judul di dalamnya tetap diperiksa `hideTitles`, jadi menyembunyikan judul kini hanya menyembunyikan judul dan tombol kembali tetap ada. |
| `resources/js/components/modules/builder/FormBuilderWorkspace.vue` | Jalur simpan dipusatkan di `submitForm()`: menolak klik saat `processing` benar, lalu memanggil `wb.requestSave()`. Toolbar, blok mobile, dan `defineExpose().requestSave` memakai fungsi yang sama, sehingga tetap satu jalur simpan (syarat P0-02) dan klik ganda tidak mengirim dua POST. |
| `tests/e2e/form-answers-mobile.spec.ts` | Satu baris `test.use({ storageState: { cookies: [], origins: [] } })`. Spec ini login sendiri; dengan storageState admin, `/auth/login` ter-redirect ke landing sehingga `#login-email` tidak pernah ada. |

`Create.vue:97` **tidak** diubah: `hideTitles` masih sah sebagai niat halaman (judul sudah ada di layout dashboard), yang salah adalah efek sampingnya pada aksi.

## Verifikasi

Server uji adalah container `e2e036` (`localhost/d-form-v2_app:latest`) yang menyajikan worktree branch ini di `:3002`, dengan `public/hot` dihapus dan `public/build` hasil build dari branch (`npm run build` di container `d_form_app`, sukses 14,68 detik). Artinya kode yang diuji benar-benar kode branch, bukan `dev-zapp`.

| Pemeriksaan | Hasil |
| ----------- | ----- |
| Tiga baris P0-1 (sebelum perbaikan, basis `b9c0789`) | **3 failed** — assertion tercatat di `saptoChanges29-10-2026-DFORM-036.md` |
| Tiga baris P0-1 (sesudah perbaikan) | **3 passed** — `P0-01` 11,3 dtk, `P0-02` 21,1 dtk, `P0-03` 21,6 dtk, `--retries=0` |
| `P0-02` bukti satu jalur simpan | `requestCount('POST', /admin/events/{id}/forms)` = 1 dan `e2e:count-rows --marker` melaporkan `forms` = 1 |
| `P0-03` bukti klik ganda | Klik ganda pada tombol yang sama dengan respons ditunda 1,5 dtk tetap menghasilkan 1 POST dan 1 baris. Yang menahan klik kedua adalah `:disabled="processing"` di toolbar, bukan penjaga `submitForm()` — lihat batas di bawah |
| `form-answers-mobile.spec.ts` | 2 passed sesudah opt-out storageState |
| `npx eslint` pada 3 file yang disentuh | exit 0, tanpa temuan |
| `npx vue-tsc --noEmit` | tidak ada error pada `FormBuilderToolbar.vue` / `FormBuilderWorkspace.vue` |
| Suite penuh (`npx playwright test`, kedua project, `:3002` + `--retries` bawaan) | **109 passed, 2 flaky, 0 failed** (16,7 mnt). Flaky keduanya spec lama di project mobile: `auth.spec.ts:153` (throttle 429) dan `otp-verification.spec.ts:290` (hitung mundur), hijau pada retry pertama. |
| Bukti kegagalan `form-answers-mobile` sudah ada sebelum perbaikan | Spec yang sama dijalankan terhadap `:3000` (root checkout `dev-zapp`, tanpa perbaikan toolbar): **2 failed** dengan `locator.fill` timeout pada `#login-email`, identik dengan kegagalan di `:3002`. |

## Penyimpangan dan batas (satu baris masing-masing)

- Penjaga klik ganda diletakkan di `FormBuilderWorkspace.submitForm()`, bukan di tiap halaman, karena dua permukaan simpan (toolbar dan blok mobile) serta dua halaman (Create dan Show) memakai komponen yang sama; menaruhnya di halaman berarti empat salinan.
- Tombol Simpan tetap `:disabled="processing"` — dialah lapisan yang benar-benar menahan klik kedua. Diukur dengan probe: dari `dblclick()`, event `click` kedua mendarat di `DIV` pembungkus karena `BUTTON` sudah `disabled` (kontrol disabled tidak menerima event), sementara `disabledDuringProcessing=true`. Jadi penjaga `submitForm()` tidak pernah dieksekusi pada jalur klik, dan premis "klik yang datang sebelum re-render" tidak terjadi: flush render Vue selesai sebelum klik kedua.
- Penjaga `submitForm()` tetap dipertahankan sebagai lapisan untuk panggilan programatik — `defineExpose().requestSave` dipakai `Show.vue:173` (`requestSaveAll`) dan tidak dilindungi atribut `disabled`. Penjaga ini belum punya test sendiri; `P0-03` bukan buktinya.
- Baris P0-1 hanya dijalankan di project `chromium-desktop`; alasannya di `saptoChanges29-10-2026-DFORM-036.md`.
- Perbaikan `form-answers-mobile.spec.ts` adalah utang harness dari TASK-009 (spec lama login sendiri tetapi mewarisi storageState admin), bukan akibat perubahan P0-1; dibuktikan dengan menjalankan spec itu pada basis yang sama sebelum perbaikan toolbar.
