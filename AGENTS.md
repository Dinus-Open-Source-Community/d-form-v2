# AGENTS.md — d-form-v2

## Aturan wajib

Baca sebelum menulis kode:

- `docs/rules/general.md` — git flow, conventional commits, PR, environment & security.
- `docs/rules/front-end.md` — aturan Vue, TypeScript, component, layout, accessibility, dan data flow.
- `docs/rules/front-end-structure.md` — struktur folder frontend, dependency direction, penamaan, dan larangan per folder.
- `docs/rules/back-end.md` — Laravel 12, controller/request/model/observer/policy, storage.
- `CONTEXT.md` — konteks proyek: arsitektur, domain, alur, skema data. Wajib dibaca setiap sesi; jangan bertanya ulang hal yang sudah ada di sana.

### Aturan user (13 butir, mengikat semua kode baru/pindahan)

1. Prefix `I`/`G`/`T` untuk interface, generic, type alias.
2. Utility TS (`Pick`/`Omit`/`Partial`/`Readonly`/`ReturnType`) daripada duplikasi tipe; varian diturunkan dari tipe sumber (`Partial<T>`, `Record<keyof T, ...>`), bukan ditulis ulang.
3. `any` dilarang. `unknown` hanya boleh pada boundary third-party yang benar-benar tidak diketahui dan wajib segera dipersempit dengan guard atau validasi runtime; response internal yang kontraknya diketahui wajib memakai tipe eksplisit.
4. Dilarang menggunakan non-null assertion operator (`value!`); gunakan `?` dan penanganan nullable (early return/fallback/guard). Operator logika `!` dan perbandingan `!==` tetap diperbolehkan.
5. Optimal & readable: hindari alokasi/serialisasi berulang di path panas.
6. Reusable & dedup: sebelum menulis, cari dulu implementasi serupa (simbol, path, atau grep). Satu duplikasi → satu hook, satu fungsi `lib/`, atau satu layer lintas-potong yang dipakai semua pemakai. Fitur yang bisa dipakai ulang wajib dipusatkan, bukan disalin per task — contoh: rate limiter global, sanitasi input, sanitasi query.
7. Jika fungsi memiliki lebih dari 3 parameter, parameter tidak berhubungan, atau kombinasi parameter sulit dibaca, gunakan object argument. Batas jumlah parameter adalah heuristik, bukan larangan absolut.
8. Nama spesifik domain (`useBannerFilePicker`, `formatRupiahPrice`), bukan `useFile`/`formatDate2`.
9. Nama human-readable, bukan AI-slop.
10. Satu fungsi = satu tanggung jawab (satu alasan berubah); komposisi lewat pemanggilan; larang god function.
11. Softcoded default; wajib-softcode: magic number tersebar, string duplikat lintas file, config env. Hardcode lintas-pakai wajib beralasan tertulis satu baris.
12. Larang nama AI-slop (`foo`/`bar`/`tmp2`/`data2`, singkatan samar, suffix `-Util`/`-Helper`/`-Manager`).
13. Dokumentasi singkat wajib untuk exported function, public class method, fungsi yang memiliki side effect, precondition/invariant, algoritma non-obvious, dan adapter boundary. Fungsi trivial yang sudah jelas dari nama dan tipe tidak wajib diberi komentar.

### Prinsip engineering

- **Best practice + optimal** untuk penulisan kode maupun perilaku fitur. Bila ada opsi yang lebih baik, pakai opsi itu; bila ditolak, catat alasan satu baris (lihat Gate langkah 2).
- Ukuran keberhasilan **bukan jumlah baris**: kode banyak ≠ optimal, kode sedikit ≠ optimal. Yang dinilai: performance, optimalisasi, konsistensi, maintainability, scalability.
- **Konsisten dengan pola yang sudah ada.** Pola baru hanya bila pola lama tidak memenuhi kebutuhan, dan alasannya dicatat.
- **Dedup bukan rename.** Menghilangkan duplikasi dengan mengganti nama fungsi dilarang; dedup berarti satu implementasi, bukan satu nama.
- Bebas dari **if hell / kondisi bersarang**, **query N+1**, dan kompleksitas yang menciptakan tech debt. Ukur Big-O pada path data besar sebelum menulis.

### Typing (front-end)

- `strict: true`; tipe eksplisit pada setiap parameter dan return.
- `unknown` tidak digunakan untuk response internal yang kontraknya diketahui. Pada boundary third-party yang benar-benar tidak diketahui, `unknown` wajib segera dipersempit dengan guard atau validasi runtime.
- Type assertion (`as`) dilarang jika hanya menutupi error tipe. Diperbolehkan setelah runtime validation, pada generated/library boundary, atau sebagai `as const` untuk literal inference. Alasan dicatat dekat kode atau di changelog jika berdampak arsitektur.
- Tanpa trik supresi (`@ts-ignore`, `@ts-expect-error`, `eslint-disable`, `noqa`) — perbaiki akar masalah.
- Setiap `.vue` wajib `<script setup lang="ts">`; dilarang Options API.

### Arsitektur front-end

- `.vue` UI-only: `<script setup>` hanya memanggil hooks, memetakan props ke komponen anak, dan mengikat event. Layout yang digunakan dipilih berdasarkan domain halaman; tidak ada kewajiban memakai nama `AppLayout`.
- `resources/js/hooks/` satu-satunya rumah logic stateful; nama `useXxx`, named export, tanpa default export di file baru.
- Factory objek kompleks bernama `createXxx` di modul builder/lib, bukan di hooks.
- **Pengecualian builder:** factory dan guard murni yang terikat siklus hidup form builder boleh co-locate di `resources/js/components/modules/builder/` (`formBuilderFieldFactory.ts`, `formBuilderPalette.ts`, `fieldMapping.ts`, `formBanner.ts`, `autosaveGuard.ts`, `dirtyFields.ts`, `optionImage.ts`). Helper murni umum tetap wajib di `lib/`.
- Alias import tetap `@/`.
- Struktur folder frontend, dependency direction, penamaan, dan larangan mengikuti `docs/rules/front-end-structure.md`.
- Tindakan destruktif (hapus file/data, instal/uninstal paket) wajib tampilkan rencana + konfirmasi user dulu.

### Code splitting (frontend)

Wajib untuk refactor frontend. Target: tidak ada satu bundel JS raksasa; route dan fitur berat dimuat saat dipakai.

- **Route-level:** `import.meta.glob('./pages/**/*.vue')` tanpa `eager` untuk lazy page resolution. Targetnya route-level loading; bundler boleh membuat shared chunk, sehingga klaim satu page selalu menjadi satu chunk harus dibuktikan dari hasil build. Dilarang `eager: true`.
- **Vendor berat hanya di chunk pemakainya:** Tiptap, `chart.js`/`vue-chartjs`, `html5-qrcode`, `lottie`/`vue3-lottie`. Impor hanya dari page/komponen yang merendernya; dilarang statis dari `app.js`, `bootstrap.js`, layout, atau `components/core/` (pakai `defineAsyncComponent`/dynamic import).
- **Komponen berat di dalam page** (form builder, broadcast composer, scanner, chart) dimuat async bila tidak terlihat saat first paint.
- **Vendor bersama tetap dapat di-cache bersama** (Vue, Inertia, Reka UI/shadcn) melalui konfigurasi bundler yang dibuktikan tidak menduplikasi dependency per page. `manualChunks` digunakan berdasarkan hasil pengukuran, bukan sebagai kewajiban yang dapat membuat shared chunk terlalu besar.
- **Prefetch:** Inertia prefetch hanya untuk tautan yang diprediksi dipakai; dilarang prefetch seluruh halaman.
- **Bukti wajib:** ukuran entry + 3 chunk terbesar sebelum vs sesudah `npm run build` (dijalankan di container) dicatat di changelog. Tidak ada klaim "lebih cepat" tanpa angka.
- **Budget:** entry JS ≤ 350 kB gzip; tidak ada chunk page > 200 kB gzip. Pelanggaran harus dipecah lagi atau dicatat alasannya satu baris.

### Penempatan berbasis folder

Satu folder = satu peran isi. Kode yang bukan tanggung jawab folder itu dilarang diletakkan di sana, sekalipun "masih berhubungan".

| Folder                                                                     | Fungsi                   | Hanya boleh berisi                                                  | Dilarang                                                       |
| :------------------------------------------------------------------------- | :----------------------- | :------------------------------------------------------------------ | :------------------------------------------------------------- |
| `resources/js/types/`                                                      | Kontrak tipe lintas file | `interface` (`I…`), `type` (`T…`), type-only enum/union, `declare module`, `*.d.ts` | Nilai runtime, runtime enum/object, fungsi, `ref`, tipe longgar |
| `resources/js/lib/`                                                        | Fungsi murni umum        | Helper/utility deterministik (`formatRupiahPrice`, `isHttpUrl`)     | `ref`/`watch`/lifecycle, DOM, `localStorage`, efek samping     |
| `resources/js/hooks/`                                                      | Logic stateful           | Composable `useXxx` + `__tests__` bersebelahan, barrel `index.ts`   | Fungsi murni tanpa state, komponen `.vue`, deklarasi tipe saja |
| `resources/js/constants/`                                                  | Runtime constants        | Nilai konfigurasi statis lintas domain tanpa behavior                | Hook, request, query, mutable global state                     |
| `resources/js/adapters/`                                                   | Third-party/browser seam | Adapter `http/`, `browser/`, `scanner/`, `third-party/`             | Business rule, page, layout, wrapper pass-through tanpa seam    |
| `resources/js/plugins/`                                                    | Plugin Vue global       | Registrasi plugin global yang benar-benar digunakan                 | Feature logic, fetch data, eager vendor page-only              |
| `resources/js/directives/`                                                 | Custom Vue directive    | Directive element-level reusable yang benar-benar digunakan          | Request, business rule, redirect, authorization                |
| `resources/js/components/core/`                                            | Komponen reusable global | Komponen presentational, props/emits eksplisit                      | Fetch data, logika bisnis, transformasi berat                  |
| `resources/js/components/layout/`                                          | Pendukung layout         | Navbar, sidebar, footer, user menu                                  | Komponen yang dipakai di luar `layouts/`                       |
| `resources/js/components/modules/`                                         | Komponen spesifik fitur  | Komponen milik satu modul                                           | Komponen lintas fitur (pindah ke `core/`)                      |
| `resources/js/components/ui/`                                              | Shadcn-Vue asli          | File bawaan library                                                 | Perubahan langsung; kustomisasi lewat `core/`                  |
| `resources/js/layouts/`                                                    | Template layout halaman  | `AuthenticatedLayout`, `GuestLayout`, `AdminLayout`                 | Logic fitur, fetch data milik modul                            |
| `resources/js/pages/`                                                      | Halaman level route      | Mapping props → komponen, `<Head>`                                  | Komponen reusable (pindah ke `core/`/`modules/`)               |
| `resources/js/routes/`, `resources/js/actions/`, `resources/js/wayfinder/` | Hasil generate framework | File hasil generate                                                 | Edit manual                                                    |

- Tipe dipakai lintas file → `types/`; tipe lokal cukup di file pemakainya.
- `resources/js/utils/` adalah legacy-only. File baru tidak boleh dibuat di sana; pure function baru berada di `lib/`, stateful composable baru berada di `hooks/`, dan kontrak lintas file berada di `types/`. File legacy hanya dipindahkan saat ada scope migrasi atau ketika disentuh untuk perbaikan yang relevan.
- Folder di luar tabel tidak boleh dipakai sebagai tempat parkir kode; folder baru wajib alasan tertulis satu baris di changelog.
- Komponen non-shadcn yang sudah ada di `components/ui/**` adalah utang teknis historis: jangan ditambah, jangan dijadikan contoh.
- Jangan membuat folder global `helpers/`, `common/`, `misc/`, `services/`, atau `data/` sebagai dumping ground. Buat module domain atau gunakan seam yang sudah ditentukan.
- `pages/`, `layouts/`, dan `components/core/` tidak boleh menyimpan business logic domain. `components/ui/` tidak boleh diedit langsung.
- `types/` tidak boleh berisi runtime object, runtime enum, factory, atau side effect. `lib/` tidak boleh mengakses state reactive, DOM, storage, atau network.
- `hooks/` tidak boleh mengimpor page dan tidak boleh menjadi tempat pure utility tanpa state.
- `pages/` dan component tidak boleh mengakses third-party/browser API secara langsung jika adapter yang sesuai sudah tersedia.
- `adapters/` boleh mengimpor third-party/browser API, tetapi tidak boleh mengimpor page, layout, component, atau business rule domain. `unknown` harus dipersempit di dalam adapter.
- `plugins/` dan `directives/` bersifat conditional; jangan membuatnya untuk satu konfigurasi lokal atau satu perilaku yang lebih tepat berada di `bootstrap`, component, atau hook.
- Jangan membuat barrel `index.ts` di setiap folder; barrel hanya boleh pada seam publik yang stabil.
- Dilarang membuat folder global `services/`, `repositories/`, `helpers/`, `common/`, `misc/`, `api/`, `mappers/`, `transformers/`, `managers/`, atau `controllers/` sebagai dumping ground.
- Back-end mengikuti `docs/rules/back-end.md`; tabel ini hanya untuk `resources/js`.

### Alur kerja agent

- Setiap tiket wajib diklasifikasikan sebelum planning: `feature`, `bug`, `security`, `performance`, `refactor`, `migration`, `infrastructure`, `documentation`, atau `dependency`.
- Alur perubahan: scope dan klasifikasi → reconnaissance dan bukti → dependency/caller/risk mapping → brainstorming → review best practice dan trade-off → writing plan → persetujuan user → test/regression test → implementasi → validasi → code review → dokumentasi/Jira → commit → monitoring → rollback atau follow-up bila indikator gagal.
- Fitur/perubahan behavior memakai `brainstorming` → `writing-plans` (plan disetujui user dulu) → TDD → eksekusi. Bug memakai `systematic-debugging` sebelum perubahan.
- **Pecah task lewat subagent** sesuai capability (recon, implementasi, review, test); jangan satu agent mengerjakan seluruh task. Setiap lane menyertakan scope, bukti yang sudah dikumpulkan, dan pemilik validasi (lihat Penggunaan skill & MCP).
- **Tidak tahu → bertanya.** Dilarang menebak, mengarang API, atau menulis kode dari asumsi. Jangan meyakinkan dengan klaim tanpa bukti. Posisi agent = team lead: membagi kerja, menetapkan standar, memvalidasi hasil.
- **Commit setelah fitur selesai utuh**, bukan commit atomik per langkah kecil.
- **Semua perintah PHP dijalankan di container**. Developer boleh menggunakan Docker atau Podman sesuai environment yang aktif, tetapi runtime harus dipilih konsisten dalam satu sesi. Dilarang menjalankan PHP proyek dari host. Tool yang tidak ada di image (mis. `k6`) boleh dijalankan di host.
- `git add <path spesifik>`, **jangan** `git add -A`.
- Jangan pernah commit `Makefile` dan `database/seeders/UserSeeder.php`.
- Commit wajib English-only, tanpa emoji, dan memakai Conventional Commits: `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`, `perf:`, atau `security:`.
- Tanpa push kecuali diminta.
- Perubahan `AGENTS.md`/`SKILL.md` wajib ditampilkan dulu dan dikonfirmasi user.

### Gate sebelum menulis kode

Urutan wajib berlaku sesuai klasifikasi perubahan; gate yang tidak relevan harus diberi alasan tertulis.

1. **Test minimum berdasarkan klasifikasi:**
   - UI/user flow → Playwright E2E.
   - Controller/API → feature/integration test.
   - Pure function → unit test.
   - Query/database → query test + `EXPLAIN`.
   - Queue/job → job test + retry/idempotency test.
   - Security → regression test + authorization test.
   - Refactor tanpa behavior → existing test suite + regression check.
   - Styling/copy/docs → lint + visual verification.
2. **Review keputusan.** Plan/changelog harus menjawab apakah solusi best practice, optimal, dan alternatif apa yang ditolak beserta alasannya.
3. **Implementasi.** Kode produksi ditulis setelah plan disetujui dan test minimum tersedia.
4. **Stress test bila berisiko:** endpoint publik/sensitif; list/export/report; lock atau counter; upload; batch processing; query tabel domain besar; perubahan queue/scheduler; atau perubahan yang memengaruhi concurrency dan idempotency.
5. Untuk perubahan kecil yang tidak memenuhi kriteria stress, regression test dan query plan yang relevan sudah cukup.

Pengecualian: typo, copy, styling murni, dokumentasi — cukup lint + verifikasi visual.

### Definition of Done

Perubahan dianggap selesai jika:

- behavior sesuai acceptance criteria;
- test yang relevan hijau;
- tidak ada temuan Critical/High terbuka;
- lint, typecheck, dan build yang relevan berhasil;
- security impact sudah direview;
- performance impact diukur jika relevan;
- migration memiliki forward path dan rollback/mitigasi yang jelas;
- GitHub Actions/CI yang relevan selesai atau kegagalannya memiliki bukti dan alasan;
- E2E selesai untuk perubahan UI/user flow;
- changelog dan Jira diperbarui;
- tidak ada secret atau dataset lokal ikut commit.

### Evidence level

Setiap temuan audit atau review wajib diberi level bukti:

- `Confirmed`: terbukti dari source atau test.
- `Reproduced`: berhasil direproduksi.
- `Measured`: didukung angka atau benchmark.
- `Inferred`: kesimpulan dari beberapa bukti.
- `Needs verification`: belum dapat dibuktikan tanpa runtime atau data tertentu.

### Exception handling dan response contract

- `400`: request malformed.
- `401`: authentication tidak ada atau tidak valid.
- `403`: user teridentifikasi tetapi tidak berwenang.
- `404`: resource tidak ditemukan.
- `405`: HTTP method tidak didukung.
- `409`: conflict atau duplicate state.
- `413`: content terlalu besar.
- `415`: media type tidak didukung.
- `422`: isi request tidak dapat diproses atau validasi gagal.
- `429`: rate limit.
- `500`: kegagalan internal yang tidak memiliki mapping lebih spesifik.
- `502`: upstream gateway menerima response tidak valid.
- `503`: service sementara tidak tersedia atau overload.
- `504`: upstream timeout.

Kode lain mengikuti [HTTP Status Code Registry IANA](https://www.iana.org/assignments/http-status-codes). Kode yang tidak terdaftar tidak boleh dibuat atau digunakan tanpa standar resmi. `419` Laravel Page Expired adalah pengecualian framework-specific dan harus didokumentasikan sebagai tersebut. Error internal tidak boleh dikirim mentah ke user; setiap endpoint wajib menjelaskan bentuk error, field validation, dan perbedaan response Inertia versus JSON.

### Observability

Observability bersifat recommended secara umum, tetapi wajib pada sesi development, audit, debugging, dan incident reproduction untuk path yang sedang dikerjakan:

- request/correlation ID;
- structured logging;
- log job ID dan attempt;
- redaction token, password, file sensitif, dan PII;
- public-safe error message;
- exception tidak boleh ditelan tanpa logging.

Metric global seperti latency, error rate, queue lag, dan failed jobs menjadi wajib bila tooling-nya tersedia atau bila path termasuk endpoint berisiko tinggi.

### Migration, queue, dan dependency safety

- Perubahan schema berisiko memakai pola expand → migrate → contract.
- Hindari operasi blocking pada tabel besar dan pertimbangkan ukuran index serta lock.
- Migration tidak boleh bergantung pada data dummy.
- Destructive migration membutuhkan backup/rollback plan dan approval eksplisit.
- Job wajib memiliki retry/backoff, timeout, observability, dan idempotency yang jelas.
- Package baru wajib memiliki alasan, owner, evaluasi license/vulnerability, serta evaluasi bundle impact bila dependency frontend.
- Lockfile wajib berubah konsisten dengan manifest.
- Dependency yang dihapus wajib dicatat sebelum dihapus.
- Source code tidak boleh dikirim ke layanan eksternal tanpa persetujuan dan pemeriksaan secret.

### Waiver process

Pengecualian aturan wajib mencatat aturan yang dilanggar, alasan teknis, risiko, alternatif yang dipertimbangkan, owner, dan tanggal review ulang. Waiver tidak boleh digunakan untuk menutupi test yang belum dibuat atau error yang belum dipahami.

### Back-end

- Security wajib, bukan opsional: validasi di Form Request, otorisasi di setiap endpoint (policy/middleware), parameter binding (tanpa string SQL), escape output, CSRF, whitelist tipe & ukuran upload, secret hanya dari config/env.
- Endpoint publik/sensitif wajib punya rate limit; rate limiter dibuat satu layer global yang dipakai semua pemakai (lihat aturan user 6).
- Validasi dilakukan di boundary request. Normalisasi dilakukan oleh value object atau input mapper. Encoding dilakukan sesuai output context. SQL memakai parameter binding/query builder. Reusable validation atau normalization boleh dipusatkan, tetapi jangan membuat satu sanitizer global yang menjadi god function.
- Output harus diperlakukan sesuai context: HTML text, HTML attribute, URL, JavaScript, JSON, atau rich HTML yang sudah disanitasi.
- Middleware menangani akses area besar, role, dan feature flag. Form Request menangani authorization yang terkait input/resource request. Policy menangani aturan resource/domain. Controller hanya memanggil authorization yang belum dijalankan; jangan menggandakan pemeriksaan tanpa alasan.
- Side effect eksternal seperti email, webhook, file deletion, dan dispatch queue yang bergantung pada data committed harus memakai after-commit behavior. Job wajib aman terhadap retry dan duplikasi.
- Sebelum menambah query baru: cek N+1 (eager load), indeks kolom filter/sort, dan rencana eksekusi pada data besar.

### Seeder & data uji

- Seeder wajib memakai **factory** (`database/factories/`); jangan menulis data literal di seeder.
- **Pengecualian:** `database/seeders/Stress/**` memakai bulk query builder (bukan factory) karena target volume 1 juta baris/tabel; factory tetap wajib untuk seeder biasa.
- Dataset stress dibuat oleh satu pintu: `php artisan stress:seed` (`database/seeders/Stress/**`). Skala default **1.000.000 baris per tabel domain**, dapat diubah lewat `STRESS_SCALE`.
- Tabel **domain** wajib 1 juta baris dan saling berelasi valid: `users`, `events`, `forms`, `form_fields`, `form_answers`, `event_attendances`, `email_logs`, `email_datasets`, `email_dataset_recipients`, `email_broadcasts`, `email_broadcast_recipients`, `email_broadcast_attachments`, dan seluruh `recruitment_*` yang menyimpan data transaksi.
- Tabel **referensi & infra** memakai jumlah kecil/nyata, bukan 1 juta: `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`, `recruitment_divisions`, `recruitment_email_templates`, `recruitment_registration_sequences`, `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`. Alasannya: meledakkan pivot permission/role atau membuat antrean/sesi palsu, bukan menguji query domain. (`event_categories` tidak ada sebagai tabel — kategori event adalah kolom `events.category`.)
- Seeder menulis manifest ID contoh ke `storage/app/stress/manifest.json` untuk dipakai harness (`scripts/stress/`).
- Dataset besar dibuat lokal/on-demand dan **tidak di-commit**; `UserSeeder.php` tetap tidak di-commit.

### Dokumentasi perubahan

- Changelog per-tiket: `docs/developer/sapto/saptoChanges<DD-MM-YYYY>-DFORM-<N>.md` (satu file per tiket, ada kolom Issue/commit) + baris di `docs/developer/sapto/README.md`.
- Jangan menulis ulang god-doc `saptoChanges27-09-2026.md`.

### Jira

- Agent hanya memindahkan status **In Progress → In Review**; penutupan **Done** oleh manusia.

## Agent skills

### Penggunaan skill & MCP

Agent dan subagent wajib memakai aset yang sudah ada sebelum bekerja manual.

- **Skill:** muat skill native yang relevan (`brainstorming`, `writing-plans`, `systematic-debugging`, `test-driven-development`, `impeccable`/`better-interface`, `playwright-cli`, `code-review`) alih-alih mengarang alur sendiri.
- **Peta kode:** `graphify` bila `graphify-out/graph.json` ada; verifikasi simbol, caller, dan snippet lewat `codebase-memory-mcp`. Grep hanya untuk string literal, pesan error, config, file non-kode, atau saat graph gagal — bukan langkah pertama.
- **Browser/e2e:** MCP `playwright`/`agent-browser`, bukan menebak perilaku UI.
- **Tier termurah yang cukup:** scout (lookup cepat, provisional) → verify (default) → auditor (untuk klaim negatif/ekshaustif). Sertakan `check_index_coverage` untuk setiap path yang dikutip.
- **Subagent:** hanya untuk pekerjaan yang benar-benar terpisah. Sebutkan scope tulis, bukti graph/tier yang sudah dikumpulkan, dan pemilik validasi. Revisi lanjutkan sesi yang sama; jangan spawn ulang untuk tujuan yang sama.
- **Bukti, bukan klaim:** hasil subagent wajib dibaca ulang dari artefaknya sebelum dilaporkan selesai.
- **Optimal:** satu tugas = satu alat termurah yang memenuhi; dilarang memanggil MCP berat berulang untuk data yang sudah ada di konteks.

### Issue tracker

Jira penuh (primer) untuk semua case ke depan.

### Triage labels

Default sekarang (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`); opsi custom Jira didokumentasikan untuk evaluasi.

### Domain docs

Single-context: satu `CONTEXT.md` di root (wajib dibaca, lihat Aturan wajib). ADR ditulis ke `docs/adr/` saat keputusan arsitektur pertama diambil; folder dibuat pada saat itu, bukan sebelumnya.
