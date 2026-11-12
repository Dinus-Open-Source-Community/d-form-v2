# Sapto Changes, 12 November 2026 (DFORM-FORM-SHARE)

## Ringkasan

Menambahkan alur berbagi form dari editor: link peserta, link admin, short link
internal dengan custom alias, login-intended redirect, dan barcode QR PNG yang
dibuat server. Dialog share dibuat accessible dan responsif. Padding summary card
di halaman recruitment period juga diseragamkan sesuai permintaan UI.

| Issue | Commit |
| --- | --- |
| Tanpa tiket backlog, permintaan pemilik, 12-11-2026 | `PENDING` |

## Perubahan

- `forms.share_slug` nullable unique ditambahkan dengan migration reversible. Alias
  3 sampai 50 karakter memakai huruf kecil, angka, dan `-`.
- Short link internal memakai route publik `/s/{slug}`. Alias lama menjadi 404 saat
  diganti dan dilepaskan agar dapat dipakai form lain.
- Redirect short link hanya menuju route peserta `/joined/{event}/{form}/fill`.
  Guest tetap melewati login dan mempertahankan `url.intended` yang aman.
- Mutation alias dan barcode membutuhkan authorization organizer pada event/form.
  Barcode selalu dibuat dari URL peserta absolut yang dibangun server, bukan dari
  URL request.
- Editor memiliki satu tombol `Bagikan` dengan salin link peserta, custom alias,
  salin link short, `Generate barcode`, download barcode setelah image load, dan
  salin link admin secara terpisah.
- Draft form menonaktifkan aksi peserta dan membersihkan barcode yang sebelumnya
  sudah dibuat.
- Error barcode memiliki pesan visible, announcement accessible, dan retry.
- Summary card recruitment period tetap full-width dengan padding horizontal dalam
  yang simetris pada desktop dan mobile.
- Test logging export diperbarui agar kontrak `SafeExceptionReporter` tercermin
  ketika logger utama sengaja dibuat gagal. Production code tidak diubah.
- Runbook migration mencatat rollback `share_slug` sebagai data-loss-on-rollback
  karena alias aktif dapat mematikan short link saat kolom dihapus.

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `npm run typecheck` | PASS, exit 0 |
| `npm run typecheck:unit` | PASS, exit 0 |
| `npm run test:unit` | **924 passed**, 84 file |
| `npm run lint` | PASS, exit 0, satu warning lama `SAMPLE_FIELDS` tidak terpakai |
| `npx prettier --check` pada file scope | PASS, exit 0 |
| `podman exec ... npm run build` | PASS, exit 0 |
| `node scripts/build/check-bundle-budget.mjs` | PASS, entry 15,7 kB gzip, chunk terbesar 142,2 kB gzip |
| `podman exec ... php artisan test` | **1605 passed, 12 skipped, 0 failed**, 10.593 assertions |
| `FormShareLinkTest` | 11 tests, 48 assertions |
| Task 2 endpoint tests | 21 tests, 116 assertions |
| Task 3 adapter/hook tests | 9 tests |
| `npm run test:e2e:types` | PASS, exit 0 |
| Targeted `form-share-link.spec.ts` dengan `E2E_ARTISAN` container | **3 passed**, setup + Chromium desktop + Chromium mobile |

## Batasan verifikasi

E2E memakai fixture owner yang sama dengan admin authenticated, mempertahankan
authorization path asli. Dependency analytics opsional `seeyu.doscom.org` diisolasi
di test dengan stub exact URL; console error, page error, dan request failure lain
tetap menyebabkan test gagal. Tidak ada perubahan production pada analytics.

## Rollback

- Tidak ada commit atau push.
- Rollback schema memakai `down()` migration `2026_11_12_000001_add_share_slug_to_forms_table`.
- Sebelum rollback production, ekspor alias short link dan deploy kode yang tidak
  lagi membaca/menulis `share_slug`; penghapusan kolom membuat short link yang sudah
  dibagikan tidak valid.
