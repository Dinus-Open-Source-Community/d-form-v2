# Sapto Changes — 9 November 2026 (FE-AUDIT-005)

## Ringkasan

Response JSON untuk review submission form dan feed scan global sekarang divalidasi di
boundary adapter, bukan dibiarkan `unknown`/di-parse di `lib/`. Body review 2xx wajib cocok
kontrak `{id, review_status, reviewed_at, reviewed_by, registration_code, cascaded_rejections}`;
body 2xx yang tidak cocok menjadi `HttpRequestError{kind:'invalid_response'}` (error feedback,
bukan sukses palsu). `fetchScanFeed()` mengembalikan `IScanFeedPayload` yang sudah divalidasi
(baris invalid dibuang, payload non-record → `invalid_response`). `parseApiErrorMessage`
kehilangan dua type assertion `as` lewat guard `lib/isRecord.ts`.

| Issue | Commit |
| ----- | ------ |
| FE-AUDIT-005 | uncommitted |

Laporan lengkap: `docs/big-changes/patching-ticket/FE-AUDIT-005.md`.

## Koreksi premis (bukti)

- `resources/js/utils/composables/` sudah dihapus pada FE-AUDIT-003; file hidup di
  `resources/js/hooks/`.
- `pages/Dashboard/Events/Forms/Submissions.vue` + `useFormSubmissionsPage.ts` dead code
  (route 301 redirect ke tab `jawaban`), milik `FE-AUDIT-003-F2`; review hidup ada di
  `pages/Dashboard/Events/Forms/Show.vue`.
- `adapters/http/scan.ts` mengembalikan `Promise<unknown>`; parser feed diduplikasi di
  `lib/qrScanUi.ts`; `types/scan.ts` sudah punya tipe feed yang tidak dipakai.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/types/forms.ts` (baru) | `TFormReviewStatus`, `TFormReviewDecision`, `IFormReviewSuccessResponse` (field wajib non-null), `IFormReviewErrorBody`. |
| `resources/js/types/scan.ts` | `IScanTargetSession`/`IScanTargetDivision`/`IScanTargetEvent`; catatan cursor/subset row feed. |
| `resources/js/adapters/http/forms.ts` | Validasi ketat body review 2xx (accepted/rejected, reviewed_at/by non-empty, cascaded integer ≥ 0, accepted wajib registration_code); `invalid_response` untuk pelanggaran; non-2xx tetap `message`/`currentStatus`. |
| `resources/js/adapters/http/scan.ts` | `fetchScanFeed(): Promise<IScanFeedPayload>`; `id`+`ts` wajib, `type` tepat event/recruitment, semua baris invalid → `invalid_response`; guard mati dihapus. |
| `resources/js/hooks/useGlobalQrScanPage.ts` | Payload bertipe; parser/guard/catch mati dihapus; `IGlobalScan*`; `typeof` guard props; 2xx envelope invalid → toast kontrak + baris `Respons tidak valid`. |
| `resources/js/pages/Dashboard/Scan/Global.vue` | Props `targets` memakai `IScanTargetSession[]`/`IScanTargetEvent[]`. |
| `resources/js/lib/qrScanUi.ts` | Parser feed dihapus; mapping UI dan `FEED_POLL_MS`/`nextFeedDelayMs` tetap. |
| `resources/js/lib/isRecord.ts` (baru) | Guard record non-array untuk `lib/`. |
| `resources/js/lib/error-message.ts` | Dua `as` di `parseApiErrorMessage` dihapus. |
| `resources/js/lib/__tests__/qrScanUi.test.ts` | Test parser feed dipindah ke test adapter. |
| `resources/js/adapters/http/__tests__/scan.test.ts`, `forms.test.ts`, `support/jsonServer.ts` (baru) | 19 test adapter via server HTTP lokal (termasuk 5 body review melanggar + 4 kasus feed). |
| `resources/js/lib/__tests__/error-message.test.ts` | Kasus body error digabung; file `errorMessage.test.ts` duplikat dihapus. |
| `tests/e2e/fe-audit-005-response-narrowing.desktop.spec.ts` (baru) | 5 test browser: review happy path, review 200 rusak, scan 422, feed rusak + stale marker, store 200 rusak + toast kontrak. |

Tidak ada perubahan route, controller, schema, migration, dependency, atau copy default.
`Show.vue` tidak diubah karena return `status`/`message` tetap kompatibel.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Validasi di adapter, bukan `requestJson` global | `requestJsonWithStatus` dipakai OTP/lookup email; mengubahnya mengubah kontrak semua pemakai. |
| Baris feed invalid dibuang; payload invalid ditolak | Satu baris rusak tidak boleh menghentikan feed; payload non-record tidak boleh dipakai. |
| `isRecord` kecil diduplikasi di adapter | Dependency direction `adapters → types`; `adapters` dilarang mengimpor `lib`. |
| `Show.vue` tidak diubah | Return shape lama dipertahankan; menambah `data`/`currentStatus` bersifat aditif. |

## Verifikasi

| Gate | Hasil |
| --- | --- |
| `npm run test:unit` | 229 passed (22 files), exit 0 |
| `npm run typecheck` | exit 0 |
| `npm run typecheck:unit` | exit 0 |
| `npm run test:e2e:types` | exit 0 |
| `npm run lint` | exit 0 |
| E2E `fe-audit-005-response-narrowing.desktop.spec.ts` (`--retries=0`) | 6 passed (1 setup + 5 test), exit 0 |
| Regresi E2E `fe-audit-003-composable-migration.desktop.spec.ts` | 3 passed (1 setup + 2 test), exit 0 |

E2E membuktikan: review real PATCH → toast sukses + DB `accepted`; review 200 rusak →
toast error generik (copy spesifik kontrak dicatat sebagai `FE-AUDIT-005-F1`), tanpa
sukses, DB tetap `pending`; scan manual invalid → feedback 422;
feed rusak → marker `Feed scan tertunda`, tanpa baris palsu/`pageerror`; store 200 rusak →
toast `Respons server tidak sesuai kontrak.` + baris `Respons tidak valid`.

## Review follow-up (iterasi 2)

Review independen pertama **CHANGES REQUIRED**; 9 temuan diperbaiki: guard review diperketat
(decision non-pending, reviewed_at/by wajib, cascaded integer ≥ 0, accepted wajib kode),
feed mewajibkan `id`+`ts`+type valid dan menolak payload yang seluruh barisnya invalid,
guard `isScanErrorBody` dan cabang catch mati dihapus, props `Global.vue` memakai tipe eksplisit,
2xx envelope rusak menjadi toast kontrak (bukan error jaringan), dua E2E diperketat,
file unit duplikat digabung, interface hook diberi prefix `I`, dan laporan dikoreksi.

