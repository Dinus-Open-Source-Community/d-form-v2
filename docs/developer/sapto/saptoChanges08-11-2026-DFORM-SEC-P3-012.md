# Sapto Changes — 8 November 2026 (SEC-P3-012)

## Ringkasan

Request dengan `Host` efektif di luar allowlist — termasuk `X-Forwarded-Host` yang
dipercaya dari peer proxy — sekarang ditolak `400 Bad Request` sebelum routing dan
sebelum URL absolut disusun. Allowlist dibangun dari host `APP_URL` dan env
`TRUSTED_HOSTS` (exact host; subdomain tidak otomatis; wildcard ditolak), memakai
mekanisme resmi Symfony `Request::setTrustedHosts()` dengan pattern ter-anchor.
Middleware berjalan di semua environment karena `$middleware->trustHosts()` bawaan
Laravel no-op di `local`/PHPUnit (vendor terverifikasi).

| Issue | Commit |
| ----- | ------ |
| SEC-P3-012 | belum di-commit (working tree berisi perubahan BE-P3-011 dan lane lain yang belum di-commit; commit terpisah menunggu koordinasi) |

Laporan lengkap: `docs/big-changes/patching-ticket/SEC-P3-012.md`.
Plan: `docs/big-changes/patching-ticket/SEC-P3-012-plan.md`.

## Akar masalah (bukti, bukan gejala)

- `grep -rn 'trustHosts' app/ bootstrap/ config/` → 0 hasil.
- `TrustProxies` (BE-P3-011) mempercayai `HEADER_X_FORWARDED_HOST` dari peer privat,
  sehingga host efektif request berasal dari header kiriman klien/ingress.
- Baseline terukur sebelum patch: `Host: evil.example` →
  `302 Location: http://evil.example/auth/login`; `X-Forwarded-Host: evil.example`
  menghasilkan hal identik. Permukaan: redirect guest/login,
  `PublicStorage::url()`, `HandleInertiaRequests::absolutePublicUrl()`,
  `sitemap.xml`.
- `TrustHosts::shouldSpecifyTrustedHosts()` = false saat `environment('local')`
  atau `runningUnitTests()`; `TrustHosts::at()` menerima regex (pola tanpa anchor
  `localhost` cocok dengan `evil-localhost.com`).

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `config/trusted_hosts.php` (baru) | `hosts` = `env('TRUSTED_HOSTS')`; host `APP_URL` selalu diterima, host tambahan exact, kosong = `APP_URL` saja. |
| `app/Support/Security/TrustedHostAllowlist.php` (baru) | Policy murni: normalisasi string/array, trim, lowercase, dedupe; validasi DNS (termasuk `_` untuk nama container)/IPv4/IPv6-bracket; loopback hanya bila caller minta (`local`/`testing`); pattern `^preg_quote(host)$`; entry invalid/`APP_URL` tanpa host → `RuntimeException` (fail-closed). |
| `app/Http/Middleware/EnforceTrustedHost.php` (baru) | Bangun allowlist dari config, `Request::setTrustedHosts()`, eager `getHost()`, tolak `400` (`no-store`, JSON untuk klien JSON) + log `http_400_untrusted_host` (`host`, `x_forwarded_host`, `ip`, `path`), reset static di `finally`. |
| `bootstrap/app.php` | `$middleware->append(EnforceTrustedHost::class)`: setelah `TrustProxies` agar XFH tepercaya terbaca, di dalam `SecurityHeaders` agar header keamanan ikut pada 400. |
| `.env.example` | Blok `TRUSTED_HOSTS=` + semantik + contoh. |
| `phpunit.xml` | `<server name="TRUSTED_HOSTS" value=""/>` mem-pin test. |
| `tests/Feature/Security/TrustedProxyTest.php` | Dua test XFH `dform.example` dari peer tepercaya mengizinkan host itu lewat config; test peer tidak tepercaya tidak berubah. |
| `tests/Unit/Security/TrustedHostAllowlistTest.php` (baru) | 15 test policy. |
| `tests/Feature/Security/TrustedHostTest.php` (baru) | 12 test HTTP host/XFH/400/log/state. |
| `tests/e2e/trusted-hosts.desktop.spec.ts` (baru) | 7 test browser + anomali host. |

Tidak ada perubahan schema, migration, route, dependency, atau kontrak respons lain
selain `400` baru untuk host asing.

## Verifikasi

| Pemeriksaan | Hasil |
| ----------- | ----- |
| `php artisan test tests/Unit/Security/TrustedHostAllowlistTest.php tests/Feature/Security/TrustedHostTest.php` | 34 passed (111 assertions) |
| `php artisan test tests/Feature/Security tests/Unit/Security` | 137 passed (402 assertions) |
| `php artisan test` (suite penuh) | 1127 passed, 8 skipped (6917 assertions) |
| `pint --test` 7 file PHP tiket | PASS 7 files |
| E2E `tests/e2e/trusted-hosts.desktop.spec.ts` (`chromium-desktop`, `--retries=0`) | 8 passed (7 spec + setup) |
| E2E gabungan dengan regresi `trusted-proxies.desktop.spec.ts` | 13 passed |
| `npx tsc -p tsconfig.e2e.json` / Prettier check spec | exit 0 / bersih |
| Runtime: `Host: evil.example`, `X-Forwarded-Host: evil.example`, subdomain, lookalike, port asing | `400 Bad Request` tanpa refleksi host |
| Runtime: host valid, uppercase, port valid, XFH valid, `/up` | `200` |
| Runtime: `GET /dashboard` + host asing | `400`, tanpa `Location` ke host penyerang |
| Runtime: `Accept: application/json` + host asing | `400` `{"message":"Bad Request"}`, `Cache-Control: no-store` |

## Review

Review keamanan independen (`@paladin`): verdict **APPROVE**, tanpa Critical/High.
Temuan Low yang diperbaiki pada lane yang sama: gate loopback dipersempit ke
`local`/`testing` saja, label host boleh `_` (nama container), respons 400 memakai
`no-store` + JSON untuk klien JSON, log memuat `x_forwarded_host`/`ip`, dan test
ditambah untuk gate production/staging, wiring env `TRUSTED_HOSTS`, serta assertion
`Request::getTrustedHosts() === []`. Temuan Info (CORS sebelum validasi host, port
tidak dibatasi, static state pasca-middleware, telemetri host bersintaks invalid)
dicatat sebagai residual risk di laporan tiket.

## Prasyarat deploy

1. `APP_URL` produksi harus berisi host publik yang benar; host itu adalah baseline
   allowlist (`INFRA-P3-019` untuk skema https).
2. Tambahkan host tambahan (alias, probe internal) ke `TRUSTED_HOSTS` (comma
   separated, exact host, `_` diizinkan). Probe yang memakai host internal seperti
   `d-form-app` akan `400` bila tidak didaftarkan — daftarkan sebelum menambahkan
   `HEALTHCHECK` pada service `app` (`AUDIT-P1-010`).
3. Ingress wajib menormalkan/menimpa `Host` dan `X-Forwarded-Host`
   (`INFRA-P3-013`).
4. `config:cache` membekukan `TRUSTED_HOSTS`; ubah env → `config:cache` →
   `octane:reload`.

## Batas

- Host bersintaks invalid ditolak lebih awal oleh `TrustProxies` (status tetap 400);
  pada `APP_DEBUG=true` body-nya halaman debug framework dan event
  `http_400_untrusted_host` tidak tercatat untuk jalur itu.
- Log `host` memakai header `HOST`; bila penolakan dipicu `X-Forwarded-Host`, nilai
  efektif tidak ikut di log.
- Peer tidak tepercaya hanya diuji lewat `REMOTE_ADDR` (feature test); browser E2E
  selalu datang dari jaringan privat Docker.
