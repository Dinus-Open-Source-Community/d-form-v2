# DFORM-201 — Hapus trik supresi & non-null assertion, tegakkan lewat ESLint

- **Issue:** DFORM-201
- **Tanggal:** 27-10-2026
- **Branch:** `task-201-hapus-semua-trik-supresi-dan-non-null-assertion`
- **Commit:** `refactor(ui): hilangkan trik supresi dan non-null assertion, tegakkan lewat ESLint`

## Ringkasan (TL;DR)

Seluruh trik supresi (`@ts-expect-error`, `eslint-disable`), non-null assertion (`!`), dan nilai runtime di `resources/js/types/` dihapus. Aturan ESLint ditegakkan agar pelanggaran yang sama tidak bisa masuk lagi: `no-non-null-assertion`, `ban-ts-comment`, `vue/no-mutating-props`, dan `linterOptions.reportUnusedDisableDirectives: 'error'`.

## Pelanggaran yang ditemukan setelah aturan ditegakkan

| Jenis                                   | Jumlah | Catatan                                                                 |
| --------------------------------------- | -----: | ----------------------------------------------------------------------- |
| `@typescript-eslint/no-non-null-assertion` (ESLint) | 7 | `formBanner.ts`, `FormSubmissionDetailSheet.vue`, `EventDashboardForm.vue` (3), `userAvatarFallback.ts` |
| Non-null assertion di template (tidak terdeteksi rule ESLint) | 7 | `DashboardTopbar.vue`, `FormPreviewDialog.vue` (2), `FormFillParticipantEmailsSection.vue` (2), `MyInterviews/Index.vue` (2) |
| `@ts-expect-error`                      | 1      | `EventDashboardForm.vue`                                                |
| `eslint-disable`                        | 2      | `FormFillFieldSlotRows.vue`, `FormFillParticipantEmailsSection.vue`     |
| Nilai runtime yang diekspor dari `types/` | 3    | `emptyFormRegistrationMetadata`, `parseFormRegistrationMetadata`, `toFormMetadataPayload` |
| **Total non-null assertion dihapus**    | **14** | Termasuk 2 yang hanya muncul lewat regex karena ada di template          |

`resources/js/components/ui/**` tidak menghasilkan pelanggaran baru, jadi tidak perlu override khusus.

## Keputusan (review Gate langkah 2)

1. **Mutasi props → method composable.** Akar masalah `vue/no-mutating-props` adalah anak memutasi `ctx.answerForm` (Inertia `useForm`) yang dioper sebagai prop. Mutasi dipindahkan ke pemilik state (`useFormFillPage`): `setAnswer()` dan `setTeamMemberEmails()`. Anak hanya memanggil method; tidak ada prop yang dimutasi. Opsi `provide/inject` atau emit `update:*` ditolak karena seluruh `ctx` adalah objek composable besar yang sudah dipakai lintas 5 file — mengubahnya jadi event per-field akan menambah permukaan API tanpa manfaat perilaku.
2. **`useFormFillPage` dipindah ke `resources/js/hooks/`.** File disentuh, jadi mengikuti aturan penempatan AGENTS.md (`hooks/` untuk stateful, `utils/` legacy tidak boleh ditempati file baru). Import di 5 pemakai diperbarui.
3. **`@ts-expect-error` di `EventDashboardForm`.** Diganti `form.setError(k, 'Wajib diisi.')` dengan `REQUIRED_FIELD_NAMES` bertipe `as const satisfies` kunci payload — bukan sekadar menghapus komentar. `form.errors[k]` tetap dipakai untuk cek eksisting.
4. **`event!`.** `buildFormPayload` melempar error eksplisit bila varian `edit` tidak membawa `event`; submit URL memakai guard `props.event` dan early return.
5. **Runtime function `types/form.ts` → `lib/formRegistrationMetadata.ts`.** `types/` kembali hanya berisi tipe. Semua pengimpor diperbarui (`Dashboard/Events/Create.vue`, `Forms/Create.vue`, `Forms/Show.vue`).
6. **`noInlineConfig: true` + `reportUnusedDisableDirectives: 'error'`.** `reportUnused…` saja hanya menangkap disable yang basi; `noInlineConfig` menolak semua `eslint-disable`/`eslint-enable` inline, dipakai maupun tidak. Saat ini 0 komentar supresi di `resources/js`.
7. **`vue/no-restricted-syntax` untuk template.** `@typescript-eslint/no-non-null-assertion` tidak menjangkau ekspresi template Vue (7 dari 14 `!` ada di template). Selector `TSNonNullExpression` lewat `vue/no-restricted-syntax` menutup celah itu; sudah diuji dengan probe template merah.
8. **Payload `EventDashboardForm` satu tipe.** `buildFormPayload()` sebelumnya mengembalikan union, sehingga overload `form.setError(k, msg)` tidak resolve. Diganti satu `EventFormPayload` dengan `_method?: 'PUT'`; overload dua argumen kembali valid tanpa `@ts-expect-error`.
9. **Palet avatar bertipe non-empty tuple.** `USER_AVATAR_FALLBACK_PALETTE` diberi tipe `readonly [T, ...T[]]`, sehingga `?? PALETTE[0]` yang mati (dan tidak sound bila palet kosong) dihapus.

Tidak ada perubahan perilaku UI: submit form, panel email peserta, builder, dan topbar role tetap memakai data dan alur yang sama.

## Perubahan file

- `eslint.config.js` — aturan baru + `linterOptions`.
- `resources/js/lib/formRegistrationMetadata.ts` — **baru**, 3 fungsi runtime + doc.
- `resources/js/types/form.ts` — hanya tipe.
- `resources/js/hooks/useFormFillPage.ts` — dipindah dari `utils/composables/`, tambah `setAnswer`/`setTeamMemberEmails`.
- `resources/js/components/modules/dashboard/FormFillFieldSlotRows.vue`
- `resources/js/components/modules/dashboard/FormFillParticipantEmailsSection.vue`
- `resources/js/components/modules/dashboard/FormFillFieldsList.vue`
- `resources/js/components/modules/dashboard/DashboardTopbar.vue`
- `resources/js/components/modules/dashboard/FormSubmissionDetailSheet.vue`
- `resources/js/components/modules/dashboard/events/EventDashboardForm.vue`
- `resources/js/components/modules/builder/formBanner.ts`
- `resources/js/components/modules/builder/FormPreviewDialog.vue`
- `resources/js/lib/userAvatarFallback.ts`
- `resources/js/pages/Dashboard/Events/Create.vue`
- `resources/js/pages/Dashboard/Events/Forms/Create.vue`
- `resources/js/pages/Dashboard/Events/Forms/Show.vue`
- `resources/js/pages/Dashboard/Events/Forms/Fill.vue`
- `resources/js/pages/OpenRecruitment/Apply.vue`
- `resources/js/pages/Dashboard/Recruitment/MyInterviews/Index.vue`
- `resources/js/pages/OpenRecruitment/Track/Edit.vue` — buang import `ArrowLeft` yang tidak dipakai.

## Verifikasi

### Lint

```
$ npm run lint
(0 error, 0 warning)
```

### Build (`npm run build`, di container, base `1104063` vs sesudah)

| Item                       | Sebelum        | Sesudah                  |
| -------------------------- | -------------: | -----------------------: |
| Entry `app-*.js`           | 75,90 kB / 26,44 kB gzip | 75,94 kB / 26,45 kB gzip |
| Chunk metadata (`form-*` → `formRegistrationMetadata-*`) | 74,77 kB / 20,24 kB gzip | 74,78 kB / 20,25 kB gzip |
| Bukti akhir (byte mentah / gzip) | — | entry 75.935 / 26.270 B; metadata 74.779 / 20.176 B |
| Chunk terbesar #1          | 462,60 kB / 145,65 kB gzip | 462,60 kB / 145,65 kB gzip |
| Chunk terbesar #2          | 334,65 kB / 99,40 kB gzip | 334,65 kB / 99,40 kB gzip |
| Chunk terbesar #3          | 314,47 kB / 80,99 kB gzip | 314,47 kB / 80,99 kB gzip |

Selisih entry +0,04 kB raw (+0,01 kB gzip) dari tambahan dua method composable; tidak ada chunk yang melewati budget.

### E2E (Playwright penuh, server worktree :3001)

```
26 passed (2.2m)
```

Tidak ada flow Sokudo `e2e/` di workspace ini, jadi suite yang dijalankan adalah suite Playwright yang sudah ada (`tests/e2e`).

## Checklist

- [x] `grep "@ts-expect-error|@ts-ignore|eslint-disable" resources/js` → 0 hasil.
- [x] Non-null assertion di luar `components/ui` → 0.
- [x] `resources/js/types/` hanya tipe (0 `function`/nilai runtime).
- [x] `npm run lint` → 0 error, 0 warning.
- [x] `npm run build` sukses; ukuran sebelum/sesudah dicatat.
- [x] Suite Playwright penuh hijau (26 passed).
- [x] Perilaku UI tidak berubah.
- [x] Changelog + baris README.
