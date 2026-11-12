# Sapto Changes — 28 Oktober 2026 (DFORM-208)

Menutup dua temuan review TASK-205 pada pekerjaan TASK-201 dan TASK-204: tipe payload metadata form yang masih `Record<string, unknown>` dan `development` yang belum memicu gate CI.

> **Nomor tiket:** `TASK-208` (Sokudo). Dokumen Jira belum dibuat saat dikerjakan.

| Kolom  | Isi |
| ------ | --- |
| Issue  | TASK-208 |
| Commit | `refactor(types): tipe payload metadata form turunan FormRegistrationMetadata, tambah development ke gate CI` |
| Branch | `task-208-revisi-temuan-task-205-pada-task-201-dan-task-20` |

---

## Ringkasan (TL;DR)

| Area | Sebelum | Sesudah |
| ---- | ------- | ------- |
| `toFormMetadataPayload` | `Record<string, unknown>` | `TFormMetadataPayload` turunan `FormRegistrationMetadata` |
| `CreateDashboardFormPayload.metadata` | `FormRegistrationMetadata \| Record<string, unknown>` | `TFormMetadataPayload \| null` |
| Doc hook | `useFormFillPage` tanpa doc | doc 1 baris; `FormFillPageContext` juga berdoc |
| Trigger CI | `dev`, `dev-zapp`, `temp` | `development`, `dev`, `dev-zapp`, `temp` (push + PR) |

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/types/form.ts` | **baru** `TFormMetadataPayload` = `Required<Pick<FormRegistrationMetadata, 'purpose' \| 'requires_form_id' \| 'registration_mode' \| 'max_team_size' \| 'team_size'>>`; `CreateDashboardFormPayload.metadata` memakai tipe itu (`\| null` untuk state awal form) |
| `resources/js/lib/formRegistrationMetadata.ts` | return type `toFormMetadataPayload` → `TFormMetadataPayload` |
| `resources/js/hooks/useFormFillPage.ts` | doc untuk `useFormFillPage` dan `FormFillPageContext` |
| `.github/workflows/quality.yml` | `development` di `push.branches` + `pull_request.branches`; komentar header menyebutnya sebagai target PR fitur |
| `docs/developer/sapto/README.md` | baris tabel untuk dokumen ini |

## Review keputusan (gate langkah 2)

- **Turunan eksplisit, bukan alias `FormRegistrationMetadata`.** `Required<Pick<...>>` mengunci lima key wire payload. Kalau nanti `FormRegistrationMetadata` mendapat field opsional baru, payload create/update tidak ikut berubah tanpa keputusan sadar — bentuk kiriman ke backend tetap sama persis. Hari ini keduanya identik secara struktural (semua field sudah wajib), jadi `Required` hanya menyatakan niat "semua key selalu dikirim".
- **Tanpa `as` baru.** `TFormMetadataPayload` diturunkan dari sumbernya, dan semua call site (`Events/Create.vue`, `Events/Forms/Create.vue`, `Events/Forms/Show.vue`) mengonsumsi bentuk yang sama; tidak ada cast yang ditambahkan atau dihapus.
- **`parseFormRegistrationMetadata(raw: unknown)` tidak disentuh.** `unknown` di batas eksternal (JSON/Inertia) diizinkan `AGENTS.md` dan nilainya langsung dipersempit di dalam fungsi.
- **`CreateDashboardFormPayload.metadata` ikut diketatkan.** Tipe lama masih memuat `Record<string, unknown>`, yang membuat tipe baru tidak ada artinya di titik pemakaian. Satu-satunya pemakai (`Events/Forms/Create.vue`) mengisi `null` saat mount lalu `toFormMetadataPayload(...)` saat submit, jadi `TFormMetadataPayload | null` cukup tanpa cast.
- **`development` ditambahkan ke push dan PR.** `docs/rules/general.md` §1 menetapkan PR fitur ke `upstream:development`; gate yang tidak jalan di sana tidak menjaga alur yang sebenarnya dipakai. Job/gate tidak diubah: `lint` dan `test` tetap blocking, `formatting` tetap `continue-on-error`.

## Verifikasi

Dijalankan di container `d_form_app` dari worktree (`node_modules` symlink ke `/app/node_modules`, `vendor` disalin nyata, `database/seeders/Stress` disalin agar `php artisan wayfinder:generate` jalan).

| Pemeriksaan | Hasil |
| ----------- | ----- |
| `npx eslint "resources/js/**/*.{ts,vue}"` | **611 file, 0 error, 0 warning** (sama dengan baseline orchestrator) |
| `npx tsc --noEmit` (filter 3 file yang diubah) | tidak ada error pada `formRegistrationMetadata.ts`, `useFormFillPage.ts`, `types/form.ts`; error `TS2307` untuk impor `*.vue` tetap pre-existing (tanpa `vue-tsc`) |
| `npm run build` | sukses, `✓ built in 13.17s` |
| Entry | `app-BQqUlGse.js` — 75,94 kB (gzip 26,45 kB) |
| 3 chunk terbesar | `index-DAWjRt64.js` 462,60 kB (gzip 145,65), `index-DVGUZA9i.js` 334,65 kB (gzip 99,40), `vue3-lottie` 314,47 kB (gzip 80,99) |
| Chunk page terbesar | `Show-BdDKqDsr.js` 171,11 kB (gzip **41,45 kB**) — sesuai baseline 41 kB |
| `./vendor/bin/pint --test --dirty` | tidak dijalankan: tidak ada file PHP yang tersentuh |
| YAML `quality.yml` | parse OK; `push`/`pull_request` = `development`, `dev`, `dev-zapp`, `temp`; jobs `lint`/`test`/`formatting` utuh |

Bundle tidak berubah secara substansi karena perubahan hanya tipe dan komentar; angka di atas adalah build worktree apa adanya.

## Yang sengaja tidak dijalankan

- **Playwright/e2e tidak dijalankan dari worktree.** Server `:3000` menyajikan checkout root, bukan worktree, sehingga hasilnya tidak membuktikan apa pun tentang perubahan ini. Suite penuh dijalankan orchestrator setelah merge. Tidak ada klaim e2e di dokumen ini.
- **Tidak ada perubahan perilaku runtime**, jadi tidak ada skenario e2e baru; gate e2e-first tidak berlaku untuk perubahan tipe + konfigurasi CI.
- **Tidak menjalankan `prettier --write`.** Ketiga file `.ts` yang disentuh sudah gagal `prettier --check` sebelum tiket ini (repo memakai gaya tanpa titik koma sementara `.prettierrc` menetapkan `semi: true`; baseline 429 file). Memformatnya akan menenggelamkan diff tipe di dalam ratusan baris perubahan kosmetik. Formatting tetap report-only di CI.

## Checklist

- [x] `toFormMetadataPayload` mengembalikan `TFormMetadataPayload`, bukan `Record<string, unknown>`
- [x] Tidak ada `as` baru, tanpa `any`/`!`/trik supresi
- [x] `useFormFillPage` dan `FormFillPageContext` berdoc
- [x] `quality.yml` memicu `push` dan `pull_request` untuk `development`, `dev`, `dev-zapp`, `temp`
- [x] ESLint 0 error / 0 warning dan build sukses (angka di atas)
- [x] Diff hanya menyentuh 4 file sumber + changelog + README
