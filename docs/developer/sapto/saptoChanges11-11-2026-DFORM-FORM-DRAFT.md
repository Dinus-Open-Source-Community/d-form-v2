# Sapto Changes — 11 November 2026 (form builder: draft, autosave, tab, cleanup)

## Ringkasan

Satu paket perubahan pada alur pembuatan/penyuntingan form: pengaturan form
dipindah ke tab (varian `inline` dihapus total), draft form disimpan di server
sehingga autosave berlaku juga saat membuat form baru, breadcrumb segmen
`Formulir` menjadi teks statis karena halaman daftarnya dihapus, dan dead code
di sekitar alur ini dibersihkan sampai ke routing.

| Issue | Commit |
| ----- | ------ |
| Tanpa tiket backlog (permintaan pemilik, 11-11-2026) | `PENDING` |

## Perubahan

**Draft form di server + autosave**

- `App\Enums\FormStatus` (`Draft`/`Published`) meniru `EventStatus`; kolom
  `forms.status` (default `published`) + index `(event_id, status)`.
- `POST /admin/events/{event}/forms/draft` (`dashboard.events.forms.draft`):
  idempoten, **khusus pembuat event** (403 untuk yang lain, tidak membuat baris),
  dan mengembalikan snapshot penuh (`form` + `fields` berurutan) sehingga draft
  bisa dilanjutkan setelah reload.
- Autosave memakai **satu mesin yang sudah ada** (`useAutosaveSync`). Glue yang
  sebelumnya terduplikasi di wizard diekstrak ke `lib/formBuilderAutosave.ts`
  (murni) + `hooks/useFormBuilderAutosave.ts` (stateful), lalu dipakai wizard,
  halaman edit, dan halaman create. Tidak ada implementasi autosave kedua.
- `Save Form` = publish: flush → satu PUT (`publish: true`) → redirect ke halaman
  edit. Gagal 403 → autosave dimatikan dengan pesan jujur dan simpan manual
  memakai jalur `POST store` (perilaku sebelum fitur draft).
- Hidrasi draft/reload memakai satu modul bersama
  (`components/modules/builder/formBuilderHydration.ts`) yang juga dipakai
  halaman edit; hidrasi hanya dilakukan saat builder masih pristine dan tidak
  memicu autosave tambahan (0 request setelah hidrasi, dibuktikan E2E).
- Draft **tidak bocor**: filter `Published` di halaman publik, `FormAccessGuard`,
  picker, registrant, export, OPRec, dan observer arsip. Di daftar form milik
  event, draft tampil dengan badge `Draf` dan hanya untuk pembuat event.

**Tab menggantikan inline**

- `components/modules/builder/FormBuilderEditorTabs.vue`: satu shell tab
  `Editor` + `Pengaturan Form` dipakai create, edit, dan wizard
  (`aria-label="Konten form"`, actions di header, `:unmount-on-hide="false"`).
- Varian `inline` dihapus total: `formSettingsPlacement`, accordion
  `PENGATURAN FORM` di palette (−243 baris), blok settings mobile,
  `show-settings-tab`, `mobileTab`, dan `FormBuilderMobileTabBar.vue`.
- `FormBuilderToolbar.vue` (138 baris) dihapus — setelah semua halaman memakai
  `hide-toolbar` komponen itu tidak pernah merender apa pun; props/slot/computed
  yang hanya hidup untuknya ikut dihapus.
- Halaman create/wizard/edit kini memakai `hide-toolbar` + header tab sendiri.

**Breadcrumb**

- Bar breadcrumb tetap ada; hanya segmen `forms` (halaman daftar yang dihapus)
  yang menjadi **teks statis** (`IBreadcrumbItem.href: string | null`,
  `{ label: 'Formulir', href: null }`, dirender `<span>` tanpa link).
- Judul topbar halaman edit memakai `Edit form · <event>`, bukan `Edit: <uuid>`.

**Bug yang diperbaiki**

- Tombol Visibilitas di halaman edit sebelumnya tidak bereaksi (emit
  `toggleVisibility` tidak ditangani). Sekarang satu helper murni
  `lib/formVisibility.ts` dipakai `FormBuilderEditorTabs` dan `Forms/Show.vue`,
  plus `aria-pressed` pada tombol.
- `storeDraft` vs `guardFormOnEvent` tidak konsisten (pembuat draft non-creator
  tidak pernah bisa menyimpan) → diselaraskan pada satu predikat
  `Form::viewerOwnsEvent`.
- `MigrationRollbackTest` membaca teks komentar saat mengklasifikasi migrasi
  destruktif → detektor sekarang membuang komentar (`token_get_all`), sehingga
  komentar tidak lagi memengaruhi hasil.
- E2E `pii-log-redaction` membaca seluruh `laravel.log` lalu `substr` sehingga
  kehabisan memori 256 MB pada log besar → sekarang membaca ekor file dengan
  `fseek`/`fread` (bounded).

**Penghapusan (dead code / artefak)**

- Halaman `pages/Dashboard/Events/Forms/Index.vue`, `FormController@index`,
  route `index` (`Route::has(...) === false`, URL 404), `destroy` redirect ke
  halaman event.
- Komponen mati: `AuthInput.vue`, `FormBundleGroupDetailSheet`,
  `FormBundleGroupsCardGridView`, `FormSubmissionDetailSheet`,
  `FormSubmissionsCardGridView`, `FormSubmissionsEmptyState`,
  `FormSubmissionsTableView`, `RegistrantDetailSheet`, `DashboardNavbar`,
  `PeriodPhaseTimeline`, `PeriodStatusHero`; tipe `IAuthToastPayload` /
  `TAuthToastType` yang hanya dipakai komponen mati.
- `database/migrations/*.bak` (4 file skema lama `broadcasts` yang tidak pernah
  dieksekusi; skema aktif ada di `2026_10_01_000002_create_email_broadcasts_tables`).
- Semua kode yang dikomentari di `routes/web.php`, `routes/web/auth.php`,
  `create_events_table`, `create_permission_tables`, dan `ScanTestSeeder`.

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `npm run typecheck` / `typecheck:unit` / `test:e2e:types` | 0 / 0 / 0 |
| `npm run lint` | 0 |
| `npm run test:unit` | **672 passed** (62 file) |
| `npx prettier --check` | 0 |
| `podman exec -w /app d_form_app npm run build` + `check-bundle-budget.mjs` | PASS; entry 15,4 kB gzip, 237 chunk, max non-entry di bawah 200 kB |
| `php artisan test` | **1527 passed, 12 skipped, 0 failed** (11.830 assertions) |
| `vendor/bin/pint --test` | PASS, 776 file |
| E2E Playwright desktop (seluruh suite) | **259 passed, 3 skipped, 0 failed** (`--retries=0`) |

Catatan lingkungan: dua spec E2E (`email-availability`, `form-answers-mobile`)
bergantung pada dataset stress (`stress-user-1@stress.test`); di CI dataset itu
di-seed, di DB E2E lokal saya seed satu user + satu event agar suite bisa
dijalankan penuh. Tidak ada perubahan production/docker/CI.

## Residual

- Draft yang ditinggalkan tidak dibersihkan otomatis (ghost draft) — follow-up.
- `components/ui/**` menyimpan beberapa primitif shadcn yang tidak terpakai
  (`dropdown-menu/*`, `select/SelectScroll*`); sengaja tidak disentuh karena
  folder library.
- `docker-compose.prod.yml.bak` tetap ada (artefak production, di luar izin).
