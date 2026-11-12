# Sapto Changes — 29 Oktober 2026 (DFORM-215)

## Ringkasan

`check:row-ownership` sekarang membuktikan tiga pemeriksaan kepemilikan baris e2e, bukan satu. Dua yang sebelumnya tidak terbukti — marker ilegal **di dalam window run**, dan **teardown yang menyentuh baris run lain** — sekarang dihitung dari ledger per run, sehingga `ok: true` hanya keluar bila ketiganya nol. Temuan P1 review Vicar (TASK-214) tertutup.

| Issue | Commit |
| ----- | ------ |
| TASK-215 (menutup TASK-024) | `feat(e2e): buktikan tiga pemeriksaan kepemilikan lewat ledger per run` |

Basis: `dev-zapp` = `e2371a3`.

## Akar masalah (bukti, bukan gejala)

- `CheckRowOwnershipCommand` hanya memanggil `FixtureScope::countRun()` (pemeriksaan #1) lalu memindai grammar marker ilegal **global** di `users.email` + `events.slug` (baris 33–42 versi lama). Pemindaian global itu bukan pemeriksaan #2: baris lama di luar run ikut terhitung, dan baris tanpa marker tidak pernah diperiksa.
- `FixtureCommand` mencatat journal **in-memory** lalu membuangnya; satu-satunya artefak persisten adalah heartbeat. Jadi setelah `DELETE`, tidak ada bukti apa pun tentang baris mana yang dihapus — pemeriksaan #3 mustahil dijawab post-hoc.
- Efek samping terukur: run bersih tetap `exit 1` karena 6 event legacy `e2e-pratinjau-live*` (changelog DFORM-009 §10.5). Direproduksi di sini: `check:row-ownership --run-id=20000101T000008-abcdef` versi lama mengembalikan `"illegalTotal":6` padahal run itu tidak punya baris ilegal.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `app/Support/E2e/RunLedger.php` (baru) | Satu-satunya penulis `<storage>/app/e2e/runs/<runId>/`. `recordBaseline()` write-once (`startedAt`, `markerPrefix`, `maxIds` per tabel domain), `recordTeardown()` hanya menulis `teardown.json` bila ada temuan, plus pembaca `baseline()`/`teardown()`. `runId` divalidasi sebelum dipakai sebagai segmen path. |
| `app/Console/Commands/E2e/FixtureCommand.php` | Menulis baseline tepat sebelum baris pertama dibuat. |
| `app/Support/E2e/FixtureScope.php` | `deleteRun()` mengaudit sebelum delete: baris yang hanya masuk lewat klausa induk (marker miliknya bukan milik run ini) dihitung per tabel lalu dicatat ke ledger. Perilaku hapus tidak berubah — tetap exact-id. |
| `app/Console/Commands/E2e/CheckRowOwnershipCommand.php` | Pemeriksaan #2 dipersempit ke `created_at >= baseline.startedAt`; pemeriksaan #3 dibaca dari `teardown.json`; key JSON lama dipertahankan (`runId`, `leftover`, `leftoverTotal`, `illegal`, `illegalTotal`, `ok`), ditambah `window` dan `crossRun`. `ok` mensyaratkan `window.source = "ledger"`. |
| `tests/e2e/harness.spec.ts` | Dua baris baru: `H-ownership-window`, `H-ownership-cross-run`. |
| `docs/developer/sapto/saptoChanges29-10-2026-DFORM-215.md` | Dokumen ini. |
| `docs/developer/sapto/README.md` | Satu baris tabel changelog. |

Bentuk JSON baru (contoh nyata dari run di bawah):

```json
{"runId":"20000101T000009-abcdef","leftover":{"users":0,"events":0,"forms":0,"form_fields":0,"form_answers":0,"event_attendances":0},"leftoverTotal":0,"window":{"source":"ledger","startedAt":"2026-10-29 17:05:38"},"illegal":{"users":0,"events":0},"illegalTotal":0,"crossRun":{"violations":2,"tables":{"form_fields":1,"forms":1}},"ok":false}
```

## Bukti merah

Kode `app/` di-stash, hanya test baru yang tersisa; dijalankan terhadap `e2e215` (`:3003`, worktree ini).

```bash
E2E_BASE_URL=http://localhost:3003 E2E_ADMIN_EMAIL=stress-user-1@stress.test \
E2E_ADMIN_PASSWORD=password E2E_ARTISAN="podman exec -i -w /app e2e215 php artisan" \
npx playwright test --project=chromium-desktop --retries=0 harness.spec.ts -g "H-ownership"
```

Hasil: **2 failed, 1 passed** (setup).

| Baris | Gagal di | Bukti |
| ----- | -------- | ----- |
| `H-ownership-window` | `expect(check).toThrow(/"illegalTotal":1/)` | Kode lama mengembalikan `"illegal":{"users":0,"events":6},"illegalTotal":6` — 6 event legacy di luar window ikut terhitung, dan setelah baris ilegal di-backdate hasilnya **tidak berubah** (tidak ada scoping). |
| `H-ownership-cross-run` | `toThrow(/"crossRun":.../)` | Output lama tidak punya key `crossRun` sama sekali: `{"...","illegalTotal":7,"ok":false}`. Tidak ada bukti teardown menyentuh baris run lain. |

## Bukti hijau

```bash
npx playwright test --project=chromium-desktop --workers=2 --retries=0 harness.spec.ts
```

`23 passed (4.6m)` — termasuk `H-ownership-window` (40,7 s), `H-ownership-cross-run` (51,0 s), dan empat regresi perilaku hapus: `H-cleanup-slug-prefix`, `H-teardown-scope`, `H-harness-parallel`, `H-cleanup-failure`.

`H-ownership-window` membuktikan scoping, bukan sekadar "ada baris aneh": baris ilegal `e2e+broken-marker@e2e.invalid` dibuat dengan `created_at = now()` → `exit 1`; setelah `created_at` dimundurkan 2 hari → `"ok":true`.

`H-ownership-cross-run` membuktikan audit: form run B dipindah ke event run A, `e2e:cleanup --marker-prefix` run A menghapus form + field run B (perilaku lama tetap), lalu `check:row-ownership` run A melaporkan `"crossRun":{"violations":2,"tables":{"form_fields":1,"forms":1}}`.

## Gerbang statis

| Gerbang | Hasil |
| ------- | ----- |
| `npm run test:e2e:types` | exit 0 |
| `eslint tests/e2e/harness.spec.ts` | exit 0 |
| `vendor/bin/pint --test app/Console/Commands/E2e app/Support/E2e` (di container) | PASS, 12 file |

## Batas cakupan yang tidak ditutup

- **Pemeriksaan #2 hanya menjangkau baris ber-marker.** Baris `@e2e.invalid` yang dibuat di dalam window **tanpa marker sama sekali** tidak bisa dibedakan dari traffic sah tanpa baseline seluruh tabel (1 juta baris). Yang dijamin: semua baris yang *terlihat seperti* marker e2e (prefix `e2e+` / `e2e-` / suffix ` [e2e+`) di dalam window diperiksa grammarnya. Ini batas yang sama dengan predikat lama, hanya dipersempit window-nya — dicatat di sini supaya tidak dianggap cakupan penuh.
- **Biaya query tidak berubah orde.** Pemindaian marker ilegal tetap `like '%@e2e.invalid'` (full scan `users`); satu invokasi `check:row-ownership` pada 1.000.050 baris terukur **11,3 s**. Filter window hanya memperkecil hasil, bukan plan. Indeks `created_at` sengaja tidak ditambah karena di luar scope tiket ini.
- **Ledger run lama tidak ada.** `window.source = "absent"` dan `ok` dipaksa `false` — run sebelum perubahan ini tidak bisa lulus pemeriksaan #2, dan itu disengaja: "belum terbukti" bukan "lulus".

## Tidak ada penyimpangan dari design tiket

Satu-satunya koreksi saat implementasi: `runId` run B pada test awalnya memakai `20000101T00000a-abcdef` yang tidak lolos pola `\d{8}T\d{6}`; diganti `20000101T000010-abcdef` sebelum bukti hijau direkam.
