# Sapto Changes — 8 November 2026 (BE-P3-011)

## Ringkasan

`TrustProxies` sekarang dikonfigurasi dengan policy yang memvalidasi daftar proxy
saat boot. Di belakang ingress yang menerminasi TLS, `$request->ip()` kembali ke
IP klien, `$request->isSecure()` mengikuti `X-Forwarded-Proto`, dan rate limiter
per-IP kembali bermakna. Default dibatasi ke jaringan privat Docker; `TRUSTED_PROXIES`
dapat mempersempitnya ke CIDR ingress. Wildcard `*` dan prefix `/0` ditolak saat
production (fail fast).

| Issue | Commit |
| ----- | ------ |
| BE-P3-011 | belum di-commit (working tree berisi perubahan sesi lain pada `bootstrap/app.php`; commit terpisah menunggu koordinasi) |

Laporan lengkap: `docs/big-changes/patching-ticket/BE-P3-011.md`.
Plan: `docs/big-changes/patching-ticket/BE-P3-011-plan.md`.

## Akar masalah (bukti, bukan gejala)

- `grep -rn 'TrustProxies|trustProxies' app/ bootstrap/ config/` → 0 hasil.
- Production hanya `expose: 8000` dan bergabung ke jaringan eksternal
  `doscom_ingress` (`docker-compose.prod.yml`), jadi peer request adalah IP ingress.
- Bukti runtime sebelum patch (audit F-08): 8 `forgot-password` dengan XFF berbeda
  tetap 429 mulai attempt ke-6; `$request->ip()` = `REMOTE_ADDR`.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `config/trusted_proxies.php` (baru) | `proxies` dari `TRUSTED_PROXIES`, `default_proxies` jaringan privat Docker, `headers` mask `FOR\|PROTO\|HOST\|PORT`. Komentar alasan PREFIX/AWS_ELB tidak dipercaya dan catatan IPv4-mapped. |
| `app/Support/Security/TrustedProxyPolicy.php` (baru) | Normalisasi string koma/array, trim, dedupe; validasi IP/CIDR per famili; tolak wildcard dan prefix `0` di production; `REMOTE_ADDR` diteruskan; `headers <= 0` dan `default_proxies` kosong → `RuntimeException` (tidak ada fallback diam-diam). |
| `app/Providers/TrustedProxyServiceProvider.php` (baru) | `boot()` me-resolve policy lalu `TrustProxies::at()`/`withHeaders()`. Berjalan untuk HTTP, Octane, dan setiap artisan command, sehingga misconfig gagal saat deploy tanpa bergantung pada paket dev. |
| `bootstrap/providers.php` | Daftarkan `TrustedProxyServiceProvider` setelah `AppServiceProvider`. |
| `bootstrap/app.php` | Blok trustProxies lama dihapus; hanya komentar penunjuk ke provider. Middleware lain tidak diubah. |
| `.env.example` | Blok `TRUSTED_PROXIES=` + semantik + peringatan `*` + contoh CIDR sempit `172.18.0.0/16`. |
| `phpunit.xml` | `<server name="TRUSTED_PROXIES" value=""/>` mem-pin test agar tidak bergantung `.env` developer. |

Tidak ada perubahan schema, migration, route, atau kontrak response.

## Verifikasi

| Pemeriksaan | Hasil |
| ----------- | ----- |
| `php artisan test --filter=TrustedProxy` | 36 passed (77 assertions) |
| `php artisan test tests/Feature/Security tests/Unit/Security` | 103 passed (291 assertions) |
| `php artisan test` (suite penuh) | 1073 passed, 7 skipped (6235 assertions), 79,7 s |
| `pint --test` 8 file tiket | PASS 8 files |
| E2E `tests/e2e/trusted-proxies.desktop.spec.ts` (`chromium-desktop`, `--retries=0`) | 6 passed (25,4 s), tanpa skip |
| `npx tsc -p tsconfig.e2e.json` / Prettier check spec | exit 0 / bersih |
| Runtime: 2 request XFF sama lalu 1 XFF lain pada `/events` | `X-RateLimit-Remaining` 59 → 58 → 59 |
| Runtime: `forgot-password` 8× XFF berbeda vs 6× tanpa XFF | 302 ×8 vs 429 pada attempt ke-6 |
| Fail-fast: `TRUSTED_PROXIES='*'` / `'0.0.0.0/0'` + `APP_ENV=production` | exit 1, `RuntimeException` menyebut `TRUSTED_PROXIES` |
| Fail-fast CLI: `config:cache`, `route:cache`, `list`, `migrate --pretend`, `queue:work` | semuanya exit 1 |

## Review

Tiga putaran. Review keamanan menemukan gate production yang melewati
`SecurityEnvironment`, prefix `/0` yang lolos guard wildcard, dan fallback
`headers`/`default_proxies` yang fail-open — semuanya diperbaiki. Review
arsitektur menemukan tiga hal material:

- **Override `TRUSTED_PROXIES` belum diuji end-to-end** → `TrustedProxyOverrideTest`
  membuktikan override menggantikan default (bukan menambah).
- **Fail-fast CLI bergantung pada Debugbar** (dev dependency) → wiring pindah ke
  `TrustedProxyServiceProvider::boot()`; trace error sekarang menunjuk provider.
- **`APP_URL` http memutus signed URL** setelah skema request menjadi https →
  prasyarat deploy + `INFRA-P3-019`.

Klaim review yang saya tolak dengan bukti: "`RefreshDatabase` berlebihan"
(`GET /events` menyentuh DB). Klaim "policy tidak dievaluasi di CLI" benar secara
gejala, tetapi mekanismenya Debugbar — bukan desain; sudah dihilangkan.

Temuan di luar scope: `SEC-P3-012` (trustHosts), `INFRA-P3-013` (hygiene header
ingress), `INFRA-P3-014` (env produksi + segmentasi), `INFRA-P3-015`
(REMOTE_ADDR IPv4), `INFRA-P3-019` (APP_URL https), `INFRA-P3-020` (image
`--no-dev`), `INFRA-P3-021` (CI hanya Unit).

## Prasyarat deploy

1. `APP_URL` produksi `https://<origin publik>` (`INFRA-P3-019`).
2. `TRUSTED_PROXIES` diisi CIDR ingress (`INFRA-P3-014`); default hanya jaring
   pengaman.
3. Ubah env → `php artisan config:cache` → `php artisan octane:reload`
   (`config:cache` membekukan daftar proxy). Deploy yang membawa config file baru
   wajib menghapus `bootstrap/cache/config.php` atau menjalankan `config:cache`
   lebih dulu: cache lama tanpa key `trusted_proxies` membuat artisan gagal
   (termasuk `config:clear`), dan bila `config:cache` dilewati provider tidak
   terdaftar sehingga daftar proxy kembali `null` tanpa error. Entrypoint prod
   sudah memaksa `config:cache`.
4. Pastikan ingress menambahkan `X-Forwarded-For` (bukan meneruskan header klien)
   dan menormalkan `X-Forwarded-Host` (`INFRA-P3-013`).

## Batas

- Angka suite penuh mencakup test dari sesi/tiket lain yang berjalan bersamaan;
  hasil tiket ini adalah `--filter=TrustedProxy` (36 passed) dan
  `tests/Feature/Security tests/Unit/Security` (103 passed).
- Subnet `doscom_ingress` tidak tersedia di repo/host; nilai persis tidak dapat
  dibuktikan. Penyempitan produksi dan hygiene header ingress dicatat sebagai
  tiket infra.
- Perilaku peer tidak tepercaya hanya diuji lewat `REMOTE_ADDR` di feature test;
  browser E2E tidak dapat mengubah IP peer.
- Fail-fast mematikan seluruh artisan command production bila konfigurasi salah
  (termasuk `migrate` di entrypoint). Disengaja: deploy gagal terlihat, bukan
  login rusak diam-diam.
