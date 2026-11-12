# Sapto Changes — 9 November 2026 (FLOW-P1-002)

## Ringkasan

Reset pencarian acara tidak lagi menggandakan request. `resetSearch()` membatalkan
timer debounce 350 ms yang masih pending, dan pengiriman request diberi guard
`lastSentTerm` agar term yang identik tidak menjadi visit baru. Respons lama tidak
menimpa hasil baru karena Inertia 3 sudah membatalkan visit sync yang digantikan
(dibuktikan E2E), sehingga tidak ada layer abort kedua. Tidak ada perubahan
backend, route, schema, atau dependency.

| Issue | Commit |
| ----- | ------ |
| FLOW-P1-002 | `45bd586` — `fix: stop duplicate auth and event search requests` |

Laporan lengkap: `docs/big-changes/patching-ticket/FLOW-P1-002/patching-ticket.md`.

## Akar masalah (bukti)

- Reproduksi Playwright: input + klik Reset dalam tick yang sama → **2 GET
  `/events`** (keduanya tanpa `q`), jarak ±355 ms ≈ `SEARCH_DEBOUNCE_MS`.
  Kontrol reset tanpa ketik, reset setelah debounce selesai, dan ketik tanpa reset
  masing-masing **1** request.
- `resources/js/hooks/useEventSearch.ts:103-113` (sebelum): `useDebounceFn` tanpa
  `cancel()`; `resetSearch()` mengirim request eksplisit sementara timer tetap
  firing dengan `query.value` yang sudah dikosongkan.
- Klaim audit "reset memicu watcher" tidak akurat: hook tidak memiliki `watch`.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/hooks/useEventSearch.ts` | `resetSearch()` memanggil `requestDebouncedSearch.cancel()`; `sendSearch()` menolak term yang sama dengan kiriman terakhir; guard direset pada `onError`/`onNetworkError` dan semua respons 4xx/5xx agar term sama bisa dicoba ulang; debounce dibatalkan saat unmount. |
| `resources/js/hooks/__tests__/useEventSearch.test.ts` (baru) | 10 unit test fake timer: debounce term terbaru, reset+debounce (hitungan timer), reset ganda, batas 3 karakter, spasi, filter server sama, retry setelah validasi gagal/jaringan/5xx, cancel saat unmount. |
| `tests/e2e/event-search.spec.ts` | 3 test baru: request count reset+debounce, urutan respons (stale), anomali 2 karakter/spasi/tag script; helper marker cache diekstrak. |
| `docs/big-changes/patching.md` | Status `FLOW-P1-002` → `In Review`, blok resolusi, tiket baru `FLOW-P1-002-F1`. |

Red evidence: 4 unit test gagal pada hook HEAD; test E2E reset gagal
`Expected: 1, Received: 2`. Mutation check: menghapus tiap baris fix
(`cancel()` reset, `onNetworkError`, guard 4xx/5xx, cancel unmount) menggagalkan
tepat satu test. Setelah fix: 10 unit test dan 6 E2E desktop + 6 E2E mobile
(2 run) lulus; full suite `vitest` 305 passed (30 file); `typecheck`, eslint,
prettier (file baru/spec) bersih.

Temuan di luar scope: input 101 karakter (batas server `max:100`) di-redirect
302 ke `/events` tanpa pesan validasi — dicatat sebagai `FLOW-P1-002-F1`, tidak
diperbaiki di tiket ini.
