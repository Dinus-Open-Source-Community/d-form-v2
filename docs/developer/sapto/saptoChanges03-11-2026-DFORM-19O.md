# Sapto Changes — 3 November 2026 (DFORM-19O)

## Ringkasan

Ticket `dform-19o` meminta baseline E2E Playwright, padahal deskripsinya sudah basi: konfigurasi, script, dan spec sudah ada di `dev-zapp`. Verifikasi dilakukan terhadap kode aktual, bukan pengerjaan ulang. Satu celah nyata ditemukan dan ditutup: script E2E belum terdokumentasi di `README.md`.

| Issue       | Commit                                    |
| ----------- | ----------------------------------------- |
| `dform-19o` | `docs(e2e): document Playwright baseline` |

## Verifikasi terhadap kode aktual

| Kriteria                  | Bukti                                                                                                                                                            |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `playwright.config.*` ada | `playwright.config.ts`: `testDir ./tests/e2e`, project `setup`, `chromium-desktop` (1440×900), `chromium-mobile` (Pixel 7), `globalSetup`/`globalTeardown`       |
| Smoke login → dashboard   | `tests/e2e/auth.setup.ts` (POST `/auth/login`, lalu `/dashboard` harus 200 + cookie sesi) dan `tests/e2e/auth-required.spec.ts` (`authenticated-shell` terlihat) |
| Script terdokumentasi     | Sebelum perubahan: tidak ada bagian E2E di `README.md`. Setelah perubahan: bagian "E2E Playwright" + tabel script dan variabel environment                       |

Jumlah spec aktual: 13 file `*.spec.ts` + `auth.setup.ts` di `tests/e2e/`. Klaim "repo belum punya Playwright" pada deskripsi ticket sudah tidak berlaku.

## Perubahan

- `README.md`: menambah bagian "E2E Playwright" — perintah menjalankan, tabel `test:e2e*`, variabel `E2E_*`, dan lokasi artefak.
- Tidak ada perubahan kode produksi dan tidak ada spec baru: flow wajib (login → dashboard) sudah tercakup spec yang ada.

## Verifikasi

```text
$ E2E_BASE_URL=http://localhost:3000 E2E_ADMIN_EMAIL=stress-user-1@stress.test E2E_ADMIN_PASSWORD=*** npm run test:e2e:setup
  ✓ 1 [setup] › tests/e2e/auth.setup.ts:11:1 › H-auth-setup: login admin dan simpan storageState (3.3s)
  1 passed (23.6s)

$ ... npx playwright test --project=chromium-desktop tests/e2e/auth-required.spec.ts
  ✓ 2 [chromium-desktop] › auth-required.spec.ts:7:1 › dashboard menampilkan authenticated shell (1.3s)
  ✓ 3 [chromium-desktop] › auth-required.spec.ts:15:1 › user aktif tidak dapat membuka root auth (880ms)
  ✓ 4 [chromium-desktop] › auth-required.spec.ts:22:1 › back tidak memulihkan snapshot auth untuk sesi aktif (1.5s)
  4 passed (25.5s)
```

Catatan environment: run pertama gagal `EACCES` karena `storage/app/e2e` tidak dapat ditulis user host. Diselesaikan dengan `chmod` direktori tersebut dari dalam container (`podman exec`), bukan perubahan repo. Pada checkout baru, direktori itu perlu dibuat/diberi izin tulis sebelum suite dijalankan.

## Keputusan

README dipilih sebagai tempat dokumentasi karena acceptance criteria ticket menyebut README secara eksplisit; `CONTEXT.md` §12 sudah menyebut keberadaan 13 spec dan script `test:e2e*`. Alternatif yang ditolak: menambah spec smoke baru — duplikasi dengan `auth.setup.ts`/`auth-required.spec.ts`, dan akan menyentuh area yang sedang dikerjakan sesi Codex.
