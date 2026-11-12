# Sapto Changes — 29 Oktober 2026 (DFORM-036)

## Ringkasan

Tiga baris P0-1 ("jalur simpan Create form") ditulis dan dijalankan **merah** di branch ini, tanpa satu pun perubahan kode produksi. Penyebab merahnya satu: halaman Create mengirim `hide-toolbar-titles="true"`, dan `FormBuilderToolbar` membungkus **seluruh** toolbar — termasuk tombol Simpan dan Pratinjau — di `v-if` yang ikut memeriksa `hideTitles`. Di lebar desktop, satu-satunya tombol simpan yang tersisa adalah blok mobile `sm:hidden`, sehingga pengguna desktop tidak punya jalan menyimpan sama sekali.

| Issue | Commit |
| ----- | ------ |
| TASK-036 | `test(e2e): tulis baris P0-1 jalur simpan Create form merah` (sub-tiket TASK-037 catat tiga skenario, TASK-038 jalankan merah + rekam assertion) |

Branch: `task-036-write-and-run-the-p0-1-scenarios-red`, basis `b9c0789` (`dev-zapp` setelah TASK-009 di-merge).

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `tests/e2e/create-p0-save.desktop.spec.ts` | Tiga baris P0-1: `P0-01-save-visible-with-hidden-titles`, `P0-02-single-post`, `P0-03-double-click-guard`. Memakai fixture ber-marker (`fixtureEvent`), `startRequestLog`, dan `countFixtureRows`; tanpa kredensial di dalam spec. |
| `docs/developer/sapto/saptoChanges29-10-2026-DFORM-036.md` | Dokumen ini. |
| `docs/developer/sapto/README.md` | Satu baris tabel changelog. |

## Bukti merah

Perintah (dari worktree, server `d_form_app` di `:3000` menyajikan `dev-zapp` yang belum memuat perbaikan):

```bash
E2E_BASE_URL=http://localhost:3000 E2E_ADMIN_EMAIL=stress-user-1@stress.test \
E2E_ADMIN_PASSWORD=password npx playwright test --project=chromium-desktop --retries=0 create-p0-save
```

Hasil: **3 failed, 1 passed** (setup). Tidak ada baris yang gagal karena salah setup: ketiganya menyelesaikan pengisian draft valid lebih dulu (judul, subtitle, tanggal tutup, visibility `Public`, satu field hasil drag dari palet).

| Baris | Assertion yang gagal | Keluaran |
| ----- | -------------------- | -------- |
| `P0-01-save-visible-with-hidden-titles` | `await expect(saveControls(page)).toHaveCount(1)` (baris 52) | `Error: expect(locator).toHaveCount(expected) failed` — `Expected: 1`, `Received: 0`. Prasyarat `New form for …` juga terbukti berjumlah 0, jadi state "titles hidden" terkonfirmasi. |
| `P0-02-single-post` | `await saveControls(page).first().click()` (baris 67) | `Error: locator.click: Test timeout of 30000ms exceeded` — locator `getByRole('button', { name: /^(Simpan|Save Form)$/ }).visible()` tidak pernah muncul, jadi POST tidak mungkin dikirim. |
| `P0-03-double-click-guard` | `await saveControls(page).first().dblclick()` (baris 98) | Sama seperti P0-02: `locator.dblclick` timeout pada locator yang sama. |

P0-02 dan P0-03 merah pada aksi pertama, bukan pada hitungan POST, karena tombolnya memang tidak ada. Penyebabnya identik dengan P0-01 dan sudah tercatat sebagai akar P0-1 di design spec; menambah assertion tombol yang sama di dua baris itu hanya akan menduplikasi P0-01, jadi tidak dilakukan.

Bukti visual: tangkapan layar kegagalan `test-results/…P0-01…/test-failed-1.png` memperlihatkan draft lengkap (judul 62/200, subtitle, `Tanggal tutup 31/10/2026`, `Public`, satu field "Short Text") pada halaman tanpa satu pun tombol Simpan/Pratinjau.

## Verifikasi

| Pemeriksaan | Hasil |
| ----------- | ----- |
| `npx tsc -p tsconfig.e2e.json` | exit 0 |
| `npx eslint tests/e2e/create-p0-save.desktop.spec.ts` | exit 0, tanpa temuan |
| Baris merah | 3/3 merah, alasan sesuai tabel di atas, `--retries=0` |
| Kode produksi disentuh | Tidak ada. `git diff --stat` hanya berisi spec + dokumentasi. |

## Batas dan penyimpangan (satu baris masing-masing)

- Nama berkas memakai `create-p0-save.desktop.spec.ts`, bukan `create-p0-save.spec.ts` seperti tabel design, karena baris P0-1 hanya punya permukaan desktop (blok simpan mobile sudah terlihat sebelum perbaikan) dan suite melarang test di-skip.
- "Valid wizard data" diisi lewat panel yang ada sekarang (judul, subtitle, tanggal tutup, visibility, satu field), sebab wizard Create empat langkah belum ada di branch ini; helper `fillValidFormDraft()` adalah satu-satunya tempat urutan itu ditulis.
- Tanggal tutup dipilih dari sel kalender terakhir yang tampil, bukan tanggal tetap, agar tidak bergantung pada bulan berjalan.
- Pemilihan admin memakai `stress-user-1@stress.test` karena fixture event dimiliki user `e2e+…@e2e.invalid`, dan hanya pemilik event atau super-admin yang lolos `EventPolicy::update`.
