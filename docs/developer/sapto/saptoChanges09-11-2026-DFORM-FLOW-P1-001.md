# Sapto Changes — 9 November 2026 (FLOW-P1-001)

## Ringkasan

Panel register pada halaman auth tetap ter-mount (keep-alive) tetapi efek
`useEmailAvailability` tidak lagi berjalan saat tab Daftar tidak dipilih.
`AuthTabs.vue` menurunkan prop `isActive` ke `AuthRegisterForm`, yang
meneruskannya sebagai opsi `enabled` ke hook. Watcher email menjadi no-op saat
non-aktif; timer/request berjalan dibatalkan dan `checking` kembali `idle`.
Hook mencatat `resolvedEmail`, sehingga status final `available`/`taken` hanya
dilewati bila email saat ini sama; email yang berubah saat panel tersembunyi
dicek ulang sekali dan tidak mewarisi indikator email lama.

| Issue | Commit |
| ----- | ------ |
| FLOW-P1-001 | `45bd586` — `fix: stop duplicate auth and event search requests` |

Status tiket di `docs/big-changes/patching.md`: **In Review**. Commit terpisah
ditunda oleh orchestrator karena `resources/js/hooks/useEmailAvailability.ts`
masih memuat migrasi adapter (`axios` → `@/adapters/http/auth`) milik lane lain
yang belum di-commit, dan `docs/developer/sapto/README.md` memuat baris changelog
dari banyak lane lain. Commit scoped harus dilakukan setelah lane tersebut
stabil, memakai `git add <path>` spesifik + `git add -f` untuk `docs/big-changes/**`.

Laporan lengkap: `docs/big-changes/patching-ticket/FLOW-P1-001.md`
(`docs/big-changes/**` gitignored; orchestrator perlu `git add -f`).

## Akar masalah (bukti)

- `AuthTabs.vue` memakai `force-mount` + `unmount-on-hide="false"`; kedua panel
  benar-benar ter-mount (reka-ui me-render slot saat `forceMount || isSelected`).
- `useEmailAvailability` memasang `watch(email, handleEmailChange, { immediate: true })`
  tanpa konsep aktif/non-aktif, sehingga panel register tersembunyi tetap
  menjadwalkan request saat nilai email berubah (autofill/script).
- E2E merah: 1 POST `/auth/email-availability` dari panel yang tidak dipilih.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/hooks/useEmailAvailability.ts` | Opsi `enabled?: Ref<boolean> \| boolean`; `resolvedEmail`; `isEffectEnabled()`; `resetAvailability()`; guard no-op di `handleEmailChange`; watcher enable/disable; `onBeforeUnmount(resetAvailability)`. |
| `resources/js/components/modules/auth/AuthRegisterForm.vue` | Prop wajib `isActive: boolean` → `computed isEffectEnabled` → `enabled`. |
| `resources/js/components/modules/auth/AuthTabs.vue` | `<AuthRegisterForm :is-active="activeTab === 'signup'" />`; `force-mount` tidak diubah. |
| `resources/js/hooks/__tests__/useEmailAvailability.test.ts` (baru) | 9 test: disabled, enable, cancel, abort, no-repeat email sama, **email berbeda saat tersembunyi**, available/taken, error/422, invalid. |
| `tests/e2e/auth-tabs-request-count.desktop.spec.ts` (baru) | 5 skenario: hidden = 0 (poll) + kontrol positif; valid = tepat 1; regresi status basi A→B; keep-alive; invalid = 0. |

Tidak ada perubahan layout, route, schema, atau kontrak response.

## Verifikasi

| Perintah | Hasil |
| -------- | ----- |
| `npx vitest run resources/js/hooks/__tests__/useEmailAvailability.test.ts` | 9 passed, exit 0 |
| `npx vitest run ... -t "memeriksa ulang email berbeda"` (guard lama sementara) | 1 failed (expected `checking`, received `available`), lalu dipulihkan |
| `npm run test:unit` | 279 passed / 29 files, exit 0 |
| `npm run lint` | exit 0 |
| `npm run typecheck` (`vue-tsc --noEmit`) | exit 0 |
| `npx tsc -p tsconfig.e2e.json --noEmit` | exit 0 |
| `npx playwright test --project=chromium-desktop tests/e2e/auth-tabs-request-count.desktop.spec.ts --retries=0` | 6 passed (termasuk setup), exit 0 |
| `npx playwright test --project=chromium-desktop tests/e2e/email-availability.spec.ts --retries=0` | 5 passed (termasuk setup), exit 0 |
| `npx playwright test --project=chromium-desktop tests/e2e/auth.spec.ts --retries=0` | 9 passed (termasuk setup), exit 0 |

Flake pre-existing Inertia DevTools OOM (500 pada login) sempat muncul di run
regresi sebelumnya dan lulus pada run terbaru; tidak ada retry yang dipakai.

## Risiko dan rollback

- Email yang berubah saat panel tersembunyi di-request sekali saat tab dibuka;
  email yang sama dengan status final tidak di-request ulang.
- Kedua panel tetap ter-render (DOM + tinggi grid 675 px); duplikasi tombol OAuth
  dicatat sebagai follow-up tanpa mount effect.
- Rollback: kembalikan tiga file kode dan hapus dua file test; tidak ada schema,
  queue, atau data yang perlu dibersihkan.

## Checklist

- [x] Tidak ada request/efek dari panel yang belum dipilih (unit + E2E).
- [x] Keep-alive form dan tinggi card tidak berubah (E2E keep-alive).
- [x] Status final email lama tidak dipakai untuk email baru (unit + E2E).
- [x] Unit/lint/typecheck/e2e-types hijau.
- [x] E2E baru dan regresi hijau.
- [x] Temuan luar scope dicatat di laporan.
- [ ] Commit dan update status tiket ke In Review (orchestrator).
