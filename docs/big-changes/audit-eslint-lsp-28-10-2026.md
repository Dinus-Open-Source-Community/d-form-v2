# Audit ESLint dan LSP — 28 Oktober 2026

## 1. Ringkasan eksekutif

Audit ini mengukur ulang ESLint pada codebase TypeScript/Vue dan menjalankan LSP secara sampling berprioritas. ESLint memeriksa **611 file (258 `.ts`, 353 `.vue`) dengan 0 error, 0 warning, dan 0 fatal error**. Hasil tersebut hanya menyatakan tidak ada diagnostic ESLint dalam cakupan command, bukan bahwa tipe seluruh aplikasi valid.

Snapshot LSP terbaru (28 Oktober 2026, 06:46–06:48 UTC) memberi hasil yang dapat dibaca untuk **53 dari 611 file ESLint (8,7%)**: 47 bersih dan 6 memiliki diagnostic dengan 11 pesan. Untuk perbandingan source-only yang sepadan dengan worktree LSP, itu **53 dari 469 file TS/Vue (11,3%)**; 142 file tambahan di hasil ESLint adalah output Wayfinder generated yang tidak tersedia/disampel di worktree. Sebanyak 25 file Vue yang dipilih tetap `still starting` setelah satu pengulangan, sehingga berstatus tidak tercakup. **Audit LSP tidak menyeluruh.** Selain itu, 25 file PHP dan 16 file route/config diperiksa, tetapi semuanya masih `still starting` setelah pengulangan dan tidak dihitung bersih.

| Prioritas | Temuan terverifikasi | Dampak dan tindak lanjut |
| --- | --- | --- |
| Blocker | 0 | Tidak ada temuan yang terbukti menghentikan rilis dari pemeriksaan ini. |
| High | 0 | Belum ada bukti dampak keamanan atau kehilangan data. Cakupan backend LSP tidak tersedia. |
| Medium | 1 diagnostic tipe API Inertia terverifikasi | `router.reload({ preserveScroll: true })` tidak sesuai `ReloadOptions` pada versi terkunci; perbaiki pemanggilan atau perilaku scroll. |
| Low | 1 diagnostic kontrak tipe pada input eksternal | `formFieldOptions.ts:68` membandingkan union domain dengan string kosong; validasi nilai mentah perlu dimodelkan sebelum normalisasi. |
| Tidak terkonfirmasi / tooling | Diagnostic event Inertia, import Wayfinder, dan 3 import SFC Vue | Diagnostic event berasal dari LSP yang memakai `@inertiajs/core` 2.3.18, bukan versi lockfile/container 3.9.1. Import generated dan SFC perlu dinilai pada environment LSP yang selaras. |
| Utang tooling | LSP hanya 8,7% dari 611; `vue-tsc` tidak terpasang; formatting baseline kotor | Tambah typecheck Vue yang didukung dan rapikan baseline pada pekerjaan terpisah. |

Dibanding draf TASK-206 yang menyebut 469 file ESLint, command run ini mengembalikan **611 file**. Pemeriksaan manifest menunjukkan 469 file source tertrack (116 `.ts` + 353 `.vue`) dan 142 file TypeScript generated (83 `actions`, 58 `routes`, 1 `wayfinder`) pada container. Perbedaan +142 berasal dari perbedaan cakupan source vs output generated; angka run ESLint tetap 611.

## 2. Metodologi, tooling, dan batas cakupan

Tanggal pengukuran: **28 Oktober 2026**. ESLint dijalankan di container `d_form_app` pada checkout yang di-bind ke `/app`. Konfigurasi aktif adalah `eslint.config.js` pada commit `5bcf4e7` (`refactor(ui): hilangkan trik supresi dan non-null assertion, tegakkan lewat ESLint`); checkout worktree saat audit berada di `49ecfe3`.

```sh
podman exec -i -w /app d_form_app npx eslint "resources/js/**/*.{ts,vue}" --format json
podman exec -i -w /app d_form_app npx eslint --version
podman exec -i -w /app d_form_app node --version
```

Versi aktual container: **ESLint 10.13.0**, **Node v24.22.1**, **npm 11.12.1**, dan `@inertiajs/core` **3.9.1**. Sebaliknya, dependency yang ter-resolve oleh language server pada host adalah `@inertiajs/core` **2.3.18**, sementara `package-lock.json` mematok 3.9.1. Ini membuat diagnostic LSP atas `HttpExceptionResponse`/`httpException` tidak sah sebagai temuan source: deklarasi 3.9.1 memang mengekspor tipe dan event tersebut. Diagnostic `preserveScroll` tetap valid pada 3.9.1 karena `ReloadOptions` mengecualikan opsi itu. ESLint memuat konfigurasi flat ESLint, plugin Vue, konfigurasi TypeScript, serta rules proyek untuk unused vars, non-null assertion, komentar suppression, dan mutasi props.

LSP dijalankan dengan `lsp_diagnostics` per path, maksimal 25 path per panggilan. Snapshot diperbarui pada 28 Oktober 2026: Batch A1, A2, B, dan types D: snapshot 06:46 UTC (tidak dijamin dapat direproduksi setelahnya); Batch C serta routes/config D: snapshot 06:47 UTC (tidak dijamin dapat direproduksi setelahnya). File-file `still starting` yang tetap demikian pada snapshot tidak dihitung bersih. Pemeriksaan ulang terarah atas tujuh file yang sebelumnya berdiagnostic dilakukan sekitar 06:46 UTC; hasil ini bersifat snapshot dan dapat berubah ketika language server memuat dependency/generated files. Tidak ada interpretasi `still starting` atau `no language server` sebagai hasil bersih.

Batas penting:

- `npm run lint` hanya menjalankan glob `resources/js/**/*.{ts,vue}`. File `resources/js/app.js` tidak diperiksa oleh script itu.
- ESLint bukan type-checker. Tidak tersedia gate typecheck project-wide; `vue-tsc` tidak terpasang. `tsc` biasa belum dapat menggantikan `vue-tsc` untuk impor komponen `.vue`.
- LSP bukan mode workspace-wide pada alat yang tersedia. Sampling PHP, routes/config, dan Vue besar tidak membuktikan keadaan file lain.
- `AGENTS.md` tidak ditemukan di checkout worktree saat audit. Rekomendasi mengikuti `docs/rules/front-end.md`, `docs/rules/back-end.md`, konfigurasi ESLint aktif, serta arahan proyek yang tersedia di konteks kerja. Aturan proyek melarang mengubah `resources/js/components/ui/**` dan generated `resources/js/actions|routes|wayfinder/**` secara langsung.

## 3. Hasil ESLint

Command JSON mengembalikan 611 objek file dan seluruh penghitung diagnostic bernilai nol.

| Ekstensi | File | Error | Warning | Fatal | File dengan diagnostic |
| --- | ---: | ---: | ---: | ---: | ---: |
| `.ts` | 258 | 0 | 0 | 0 | 0 |
| `.vue` | 353 | 0 | 0 | 0 | 0 |
| **Total** | **611** | **0** | **0** | **0** | **0** |

Tidak ada rule ESLint yang muncul, sehingga tabel per-rule kosong (0 diagnostic untuk semua rule). Hasil ini memperbarui baseline draf TASK-206 yang sudah usang; lint yang bersih tidak membuktikan bahwa compiler TypeScript menerima semua tipe.

## 4. Hasil LSP dan cakupan

### 4.1 Rekap terhadap 611 file ESLint dan 469 file source worktree

| Status LSP | Jumlah dari file ESLint yang dicoba | Persentase dari 611 hasil ESLint |
| --- | ---: | ---: |
| **Jumlah file diperiksa (percobaan A1 + A2 + B + types D)** | **78 (25 + 17 + 25 + 11)** | **12,8% attempted (78/611)** |
| `no errors` | 47 | 7,7% |
| Memiliki error | 6 | 1,0% |
| Tidak tercakup (`still starting` setelah retry) | 25 | — |
| Tidak dipanggil / tidak tercakup | 533 | — |
| **Cakupan terkonfirmasi (`no errors` + error)** | **53** | **8,7% covered (53/611)** |
| **Belum tercakup seluruhnya** | **558** | **91,3%** |

Worktree yang diperiksa memiliki 469 file source TS/Vue tertrack; 142 output generated hanya ada pada checkout/container pengukuran ESLint. Dengan denominator source-only, 53 hasil LSP adalah **11,3% (53/469)** dan 416 file source belum menerima hasil. Kedua rasio menjawab denominator berbeda; 8,7% adalah rasio yang diminta terhadap seluruh output ESLint.

Persentase cakupan berarti file yang mendapat hasil diagnostik (`no errors` atau daftar error), dibagi 611 file hasil ESLint. Percobaan yang berulang tetapi tetap `still starting` tidak menambah cakupan. Hasil LSP per path tetap merupakan snapshot tooling host; diagnostic yang bergantung pada dependency harus dikonfirmasi terhadap versi lockfile.

### 4.2 Status tiap path yang diminta

**Batch A1 — 25 file hooks/lib (snapshot 28-10-2026 06:46 UTC):**

| Status | Path |
| --- | --- |
| `no errors` (21; termasuk follow-up) | `resources/js/hooks/useAuthTabs.ts`; `resources/js/hooks/useFormFillPage.ts`; `resources/js/hooks/useOtpCodeInput.ts`; `resources/js/hooks/useOtpCountdown.ts`; `resources/js/lib/authTabs.ts`; `resources/js/lib/brand.ts`; `resources/js/lib/breadcrumbs.ts`; `resources/js/lib/broadcastHub.ts`; `resources/js/lib/dateTime.ts`; `resources/js/lib/dummyData.ts`; `resources/js/lib/error-message.ts`; `resources/js/lib/eventBannerAspect.ts`; `resources/js/lib/eventCategories.ts`; `resources/js/lib/eventShowUi.ts`; `resources/js/lib/eventValidationToast.ts`; `resources/js/lib/formCheckboxAnswers.ts`; `resources/js/lib/formFieldMetadata.ts`; `resources/js/lib/formParagraphContent.ts`; `resources/js/lib/formRegistrationMetadata.ts`; `resources/js/lib/formSubmissionsUi.ts`; `resources/js/hooks/useEmailAvailability.ts` (batch awal melaporkan import Wayfinder, tetapi follow-up terarah pada 06:46 UTC memberi `no errors`; klasifikasi akhir transient/unconfirmed) |
| Error (4) | `resources/js/hooks/useEventSearch.ts`; `resources/js/hooks/useGlobalHttpStatusToast.ts`; `resources/js/hooks/useOtpVerification.ts`; `resources/js/lib/formFieldOptions.ts` |

**Batch A2 — 17 file lib tersisa (snapshot 28-10-2026 06:46 UTC):**

| Status | Path |
| --- | --- |
| `no errors` (15) | `resources/js/lib/format-countdown.ts`; `resources/js/lib/http-retry-after.ts`; `resources/js/lib/icons.ts`; `resources/js/lib/indonesianNumericInput.ts`; `resources/js/lib/isHttpUrl.ts`; `resources/js/lib/lotties.ts`; `resources/js/lib/pagination.ts`; `resources/js/lib/paginatorLinks.ts`; `resources/js/lib/qrScanUi.ts`; `resources/js/lib/recruitmentEmailResend.ts`; `resources/js/lib/recruitmentPeriodPhase.ts`; `resources/js/lib/registrantsUi.ts`; `resources/js/lib/routes.ts`; `resources/js/lib/shadcnDateFormat.ts`; `resources/js/lib/utils.ts` |
| Error (2) | `resources/js/lib/lazyRichTextEditors.ts`; `resources/js/lib/recruitmentApplicantCapabilities.ts` |

**Batch B — 25 file Vue terbesar/kompleks (snapshot 28-10-2026 06:46 UTC):** seluruhnya `still starting` pada panggilan pertama dan pengulangan, jadi **tidak tercakup**: `resources/js/components/modules/dashboard/recruitment/ApplicantDetailContent.vue`; `resources/js/pages/Docs.vue`; `resources/js/pages/Dashboard/Recruitment/MyInterviews/Show.vue`; `resources/js/pages/Dashboard/Recruitment/Periods/Show.vue`; `resources/js/components/modules/dashboard/events/EventDashboardForm.vue`; `resources/js/pages/Dashboard/Events/Forms/Show.vue`; `resources/js/components/modules/dashboard/recruitment/PeriodApplicantSection.vue`; `resources/js/pages/Dashboard/Recruitment/MyInterviews/Index.vue`; `resources/js/components/modules/builder/FormBuilderCanvasBuildView.vue`; `resources/js/components/modules/builder/FieldEditor.vue`; `resources/js/pages/EventDetail.vue`; `resources/js/components/modules/builder/FormPreviewDialog.vue`; `resources/js/pages/Dashboard/Profile.vue`; `resources/js/pages/Dashboard/Users/Show.vue`; `resources/js/pages/Dashboard/User/EventDetail.vue`; `resources/js/components/modules/dashboard/FormFillParticipantEmailsSection.vue`; `resources/js/components/modules/dashboard/broadcasts/BroadcastContentEditor.vue`; `resources/js/components/modules/builder/FieldRenderer.vue`; `resources/js/components/modules/dashboard/EventCalendar.vue`; `resources/js/pages/Dashboard/Recruitment/Index.vue`; `resources/js/pages/OpenRecruitment/Track/Edit.vue`; `resources/js/pages/OpenRecruitment/Track/Show.vue`; `resources/js/pages/Dashboard/User/TeamInvitation.vue`; `resources/js/components/modules/dashboard/recruitment/PeriodInterviewSection.vue`; `resources/js/pages/OpenRecruitment/Apply.vue`.

**Batch D — types, routes, config (types snapshot 28-10-2026 06:46 UTC; routes/config 06:47 UTC):** 11 file types berstatus `no errors`: `resources/js/types/auth.ts`; `resources/js/types/design-system.ts`; `resources/js/types/event.d.ts`; `resources/js/types/form-builder.ts`; `resources/js/types/form.ts`; `resources/js/types/global.d.ts`; `resources/js/types/icons.ts`; `resources/js/types/landing.ts`; `resources/js/types/lottie.ts`; `resources/js/types/model.d.ts`; `resources/js/types/seo.d.ts`. Enam belas path berikut `still starting` setelah pengulangan dan tidak tercakup: `routes/web.php`; `routes/web/auth.php`; `routes/web/index.php`; `routes/web/oprec.php`; `routes/web/admin/event.php`; `routes/web/admin/recruitment.php`; `routes/web/admin/users.php`; `routes/web/admin/broadcast.php`; `routes/web/admin/broadcasting.php`; `routes/console.php`; `routes/ai.php`; `config/app.php`; `config/auth.php`; `config/database.php`; `config/features.php`; `config/registration.php`.

**Batch C — 25 path PHP aplikasi (snapshot 28-10-2026 06:47 UTC):** semua berstatus `still starting` setelah pengulangan, maka tidak tercakup: `app/Http/Controllers/Auth/RegisterController.php`; `app/Http/Controllers/Auth/VerifyOtpController.php`; `app/Http/Controllers/Dashboard/Broadcasting/BroadcastController.php`; `app/Http/Controllers/Dashboard/Events/EventController.php`; `app/Http/Controllers/Dashboard/Recruitment/RecruitmentPeriodController.php`; `app/Http/Controllers/Dashboard/User/TeamInvitationController.php`; `app/Http/Requests/RegisterStoreRequest.php`; `app/Http/Requests/VerifyOtpRequest.php`; `app/Http/Requests/StoreEventRequest.php`; `app/Http/Requests/Recruitment/StoreRecruitmentPeriodRequest.php`; `app/Http/Requests/Recruitment/StoreApplicationRequest.php`; `app/Services/Auth/RegistrationOtpService.php`; `app/Services/Broadcast/BroadcastBodyText.php`; `app/Services/Event/PublicEventSearch.php`; `app/Services/Form/FormAccessGuard.php`; `app/Services/Recruitment/ApplicationSubmitter.php`; `app/Services/Recruitment/InterviewSessionService.php`; `app/Services/Registration/BundleRegistrationSubmitter.php`; `app/Models/User.php`; `app/Models/Event.php`; `app/Models/Form.php`; `app/Models/FormAnswer.php`; `app/Models/Recruitment/RecruitmentApplication.php`; `app/Models/Recruitment/RecruitmentPeriod.php`; `app/Models/EmailBroadcast.php`.

Alat juga mencetak diagnostic tambahan dari file lain, termasuk `resources/js/components/ui/**` dan `resources/js/components/core/**`, tetapi tidak menyajikan inventaris lengkapnya dalam respons (mis. “41 more files have errors”). Diagnostic spillover itu tidak dihitung dalam rekap path karena tidak dapat dipetakan sebagai hasil lengkap per file dari panggilan ini. Folder `ui/**` adalah framework-owned dan dilarang diedit langsung. Jangan perlakukan error import generated/UI sebagai alasan untuk mengubah file tersebut.

## 5. Diagnostic yang diverifikasi dan tindak lanjut

Tidak ada diagnostic ESLint. Pada pemeriksaan ulang terarah 28-10-2026 sekitar 06:46 UTC, LSP mengembalikan **11 pesan pada 6 file source**. Hitungan ini snapshot: hasil berulang tidak sepenuhnya stabil, khususnya import Wayfinder. Lokasi dan isi source diperiksa; klasifikasi dibedakan dari akar masalah dan tidak ditambahkan ke hitungan ESLint.

### Diagnostic event Inertia — false positive dari dependency LSP stale (4 diagnostic pada 2 file)

- `resources/js/hooks/useEventSearch.ts:4,116`: LSP menyatakan `@inertiajs/core` tidak mengekspor `HttpExceptionResponse` dan event `'httpException'` bukan anggota `GlobalEventsMap`. Source mendengarkan event tersebut, membaca `event.detail.response`, dan pakai status/headers untuk cooldown pencarian (`:18,33,116`).
- `resources/js/hooks/useGlobalHttpStatusToast.ts:2,58`: diagnostic yang sama untuk tipe respons dan key event; callback memakai `event.detail.response.status` untuk toast global.
- **Verifikasi:** declaration `@inertiajs/core` 3.9.1 di container mengekspor `HttpExceptionResponse` dan memuat event `httpException` di `GlobalEventsMap`; host LSP meresolusikan 2.3.18 yang tidak memiliki kontrak versi baru itu.
- **Klasifikasi/dampak:** false positive akibat dependency LSP stale, bukan temuan bug source. Perbedaan environment tetap mengurangi keandalan hasil LSP.
- **Rekomendasi:** selaraskan dependency yang dipakai language server dengan lockfile (3.9.1), lalu ulangi diagnostic sebelum mengubah kode. Jangan menambahkan cast/augmentasi tipe untuk menutupi mismatch versi tooling.

### Medium — tipe opsi reload tidak menerima `preserveScroll` (1 diagnostic terverifikasi)

- `resources/js/hooks/useOtpVerification.ts:143`: diagnostic `Object literal may only specify known properties, but 'preserveScroll' does not exist in type 'ReloadOptions<RequestPayload>'` pada `router.reload({ preserveScroll: true })`. Opsi `preserveScroll` di method `router.post` pada baris 192 dan 223 merupakan tipe opsi visit berbeda dan bukan sumber diagnostic ini.
- **Dampak:** pemanggilan reload tidak memenuhi tipe publik yang dipasang; jika opsi diabaikan, posisi scroll saat refresh props bisa berubah.
- **Rekomendasi:** ikuti opsi `ReloadOptions` versi Inertia terkunci. Jika memang perlu mempertahankan scroll, gunakan API visit yang didukung atau strategi scroll yang sesuai, dengan tes perilaku; jangan memaksa tipe melalui cast.

### Low — kontrak tipe input eksternal perlu dimodelkan eksplisit (1 diagnostic)

- `resources/js/lib/formFieldOptions.ts:68`: LSP menyatakan perbandingan `raw.type !== ''` tidak overlap dengan union `IFormField['type']`. Source memvalidasi payload eksternal sebelum normalisasi.
- **Dampak:** belum ada bukti bug runtime; diagnostic menunjukkan kontrak compile-time menyatakan nilai sudah domain-valid, sementara runtime guard mencoba menangani nilai malformed.
- **Rekomendasi:** terima payload mentah sebagai `unknown` atau DTO transport yang nullable/longgar, validasi dengan guard, lalu normalisasi menjadi `IFormField`. Ini mempertahankan validasi runtime tanpa membandingkan union domain dengan nilai yang mustahil. Jangan menghapus guard sebelum kontrak input eksternal dibuktikan.

### Transient/tidak terkonfirmasi — import Wayfinder (hingga 3 diagnostic pada snapshot batch; 2 masih muncul saat follow-up)

- `resources/js/hooks/useEmailAvailability.ts:4`: target `@/actions/App/Http/Controllers/Auth/EmailAvailabilityController` sempat tidak ditemukan di Batch A1, lalu pemeriksaan ulang terarah pada 06:46 UTC memberi `no errors`.
- `resources/js/hooks/useOtpVerification.ts:5-6`: target `VerifyOtpController` dan `CancelPendingRegistrationController` tidak ditemukan LSP.
- Dua target pada `useOtpVerification.ts` tetap dilaporkan, sedangkan target `EmailAvailabilityController` self-resolve pada pemeriksaan ulang. Ketiga target tidak tersedia di filesystem worktree yang dipakai LSP; output generated ada di checkout/container ESLint, bukan di worktree tersebut. Karena environment tidak setara, belum cukup bukti untuk menyebutnya bug kode atau generated artifact basi. Klasifikasi: **diagnostic resolusi tidak terkonfirmasi**.
- **Klasifikasi:** seluruh kelompok Wayfinder berstatus transient/unconfirmed; paling tidak satu diagnostic telah self-resolve dan dua lainnya bergantung pada output generated yang tidak ada di worktree.
- **Rekomendasi:** jalankan generator Wayfinder sesuai proses build proyek lalu verifikasi hasil terkontrol. Jangan edit file generated secara langsung; bila perlu perubahan, perbaiki sumber route/controller atau konfigurasi generator.

### Belum terkonfirmasi — impor SFC Vue tidak dikenali LSP (3 diagnostic pada 2 file)

- `resources/js/lib/lazyRichTextEditors.ts:8,13`: LSP tidak menemukan `TipTapEditor.vue` dan `BroadcastContentEditor.vue`.
- `resources/js/lib/recruitmentApplicantCapabilities.ts:1`: LSP tidak menemukan `ApplicantDetailContent.vue`.
- Ketiga berkas `.vue` target memang ada pada source. Diagnostic menyebut deklarasi tipe modul tidak ditemukan; konfigurasi `tsconfig.json` memuat `vite/client`, tetapi checkout tidak memiliki deklarasi wildcard `*.vue` dan tidak memasang `vue-tsc`. Karena itu hasil ini menunjukkan batas resolusi TypeScript/LSP untuk SFC, bukan bukti file import hilang atau bug runtime.
- **Rekomendasi:** audit dengan Vue-aware typecheck/language service dan deklarasi SFC yang sesuai konfigurasi proyek. Jangan menambah deklarasi generik atau cast sebelum memastikan tipe komponen tidak hilang dari pemeriksaan.

Untuk perubahan frontend berikutnya, gunakan pola Vue 3 `<script setup lang="ts">` dan Composition API yang sudah dipakai proyek; modelkan nilai nullable secara eksplisit dan lakukan guard/early return sebelum akses properti. Untuk PHP, batch LSP tidak memberi hasil sehingga laporan ini tidak menyatakan temuan backend; tindak lanjut harus memakai Form Request untuk validasi dan policy/otorisasi yang tepat, sambil mempertahankan urutan precondition controller bila respons 403/redirect harus mendahului validasi. Jangan mengedit `resources/js/components/ui/**` atau output generated secara langsung.

## 6. Bukan temuan bug: batas tooling dan baseline

- **ESLint bersih bukan typecheck.** Hasil 0 diagnostic tidak menguji kompatibilitas tipe antar-package dan tidak mencakup `resources/js/app.js`.
- **Typecheck Vue belum tersedia.** `vue-tsc` tidak terpasang, sehingga belum ada typecheck project-wide yang sah untuk SFC. Catat sebagai utang tooling; jangan menggolongkan ketiadaan tool sebagai bug aplikasi.
- **LSP tidak menyelesaikan batch tertentu.** 25 file Vue kompleks, 25 PHP, dan 16 routes/config tetap `still starting` sesudah retry. Status ini adalah tidak tercakup. Dari 611 file ESLint, 533 lainnya tidak dipanggil; dari 469 file source worktree, cakupan source-only adalah 11,3%. Dependency host LSP yang stale juga membatasi validitas diagnostic terkait API Inertia.
- **Formatting adalah baseline terpisah.** Pengukuran proyek sebelumnya mencatat sekitar 429 file Prettier dan 24 file Pint belum sesuai baseline. Itu isu konsistensi/formatting, bukan error ESLint/LSP dan tidak dibuktikan ulang dalam audit ini.
- **Generated/framework-owned.** Diagnostic spillover pada `resources/js/actions|routes|wayfinder/**` atau `resources/js/components/ui/**` tidak menjadi izin untuk mengedit keluaran generator atau library UI. Perbaiki sumber konfigurasi/generator atau wrapper yang dimiliki aplikasi setelah memastikan akar masalah.
- **Sampel PHP tidak dapat menyimpulkan keadaan backend.** Seluruh path PHP terpilih belum menerima hasil LSP; hal itu bukan tanda bersih dan bukan bukti adanya error PHP.

## 7. Reproduksi angka

Jalankan pengukuran ESLint di container checkout yang ingin diaudit:

```sh
podman exec -i -w /app d_form_app npx eslint "resources/js/**/*.{ts,vue}" --format json
podman exec -i -w /app d_form_app npx eslint --version
podman exec -i -w /app d_form_app node --version
```

JSON dapat dihitung per objek: `filePath` untuk ekstensi, lalu jumlahkan `errorCount`, `warningCount`, `fatalErrorCount`, jumlah objek dengan `messages.length > 0`, dan `messages[].ruleId` untuk rekap rule. Untuk mengulang LSP, panggil `lsp_diagnostics` dengan daftar path pada §4.2 dalam batch tidak lebih dari 25 path; bila respons menyebut `still starting`, ulangi sekali dan tetap catat sebagai tidak tercakup jika status tidak berubah. Cakupan terkonfirmasi pada snapshot 28-10-2026 06:46–06:48 UTC: `(47 + 6) / 611 × 100 = 8,7%`; jumlah percobaan pada 78 file kandidat ESLint: `78 / 611 × 100 = 12,8%`. Status tetap snapshot dan tidak dijamin dapat direproduksi setelah language server memuat dependency/generated files.

---

## 8. Tindak lanjut — 28 Oktober 2026 (implementasi)

Perbaikan dikerjakan pada commit `78f62d7` (tooling) dan `ce0a9df` (tipe). Semua angka di bawah diukur ulang di container `d_form_app` setelah perubahan.

### 8.1 Utang tooling: typecheck Vue sekarang tersedia

| Item | Sebelum | Sesudah |
| --- | --- | --- |
| `vue-tsc` | tidak terpasang | `vue-tsc` 5.9.3 (devDependency) |
| `@vue/language-server` | tidak terpasang, padahal `tsconfig.json` menunjuk plugin `node_modules/@vue/language-server` | terpasang 3.3.13 |
| Gate typecheck | tidak ada | `npm run typecheck` (`vue-tsc --noEmit`) |

Akar masalah cakupan LSP juga teridentifikasi: plugin bahasa di `tsconfig.json` menunjuk `@vue/language-server` yang tidak pernah dipasang, sehingga banyak file berstatus `still starting`. Diagnostic yang sebelumnya dianggap "false positive karena dependency host stale" tidak lagi relevan karena typecheck kini berjalan di container dengan dependency lockfile (`@inertiajs/core` 3.9.1).

### 8.2 Hasil `vue-tsc --noEmit` pertama

Run pertama menemukan **44 error pada 19 file** — semuanya tidak terlihat oleh ESLint. Semua sudah diperbaiki; run terakhir **0 error**.

| Kelompok | Contoh | Perbaikan |
| --- | --- | --- |
| Medium (audit) | `useOtpVerification.ts` — `preserveScroll` pada `router.reload` | opsi dihapus; `doReload()` Inertia 3 selalu `preserveScroll: true` |
| Medium (audit, ditemukan lagi) | `Periods/Show.vue:713` pola yang sama | opsi dihapus |
| Low (audit) | `formFieldOptions.ts` membandingkan union domain dengan `''` | payload dibaca lewat `IFormFieldTypePayload` (string bebas), cast `as` dihapus |
| Bug tipe nyata | `Dashboard/Events/Index.vue` memakai `IProps` yang tidak ada di scope modul | `usePage()` memakai `sharedPageProps` global |
| Bug runtime | `OpenRecruitment/Apply.vue` memakai `ctx` tanpa `reactive()`, sehingga `ComputedRef` dikirim ke prop `string` (banner bisa menjadi `[object Object]`) | `const ctx = reactive(useFormFillPage(...))`, sama seperti `Fill.vue` |
| Null-safety | `ApplicantDetailContent.vue` mengakses `props.application.document` yang bisa `null` | guard early-return |
| Error bag non-field | `errors.application`, `errors.feedback`, `errors.credentials`, `errors.tracking` tidak ada di `FormDataErrors` | helper `lib/sharedErrors.ts` + guard, tanpa cast |
| Tipe template | ref callback (`Element | null`), `v-model` `string | number`, index string, `class` array ke prop `string` | parameter/tipe disempitkan sesuai emit komponen |
| Tipe dependency | `chart.js` menolak `weight: '600'` (string), `reka-ui` `DateValue` punya private field yang hilang saat `ref()` | `weight: 600`, `shallowRef<DateValue>` |

### 8.3 Verifikasi setelah perbaikan

| Pemeriksaan | Hasil |
| --- | --- |
| `npm run typecheck` | **0 error** (sebelumnya 44) |
| `npm run lint` | 0 error |
| `npm run build` / `build:ssr` | sukses; entry tidak berubah, chunk terbesar 145,58 kB gzip |
| `php artisan test` | 826 lulus, 36 skipped, 0 gagal |
| Smoke browser (`localhost:3000`) | `/dashboard`, `/admin/events`, `/admin/events/create` (Tiptap bisa diketik), `/events`, `/recruitment/track` → 200, **0 pageerror** |

### 8.4 Yang belum dikerjakan

- Formatting baseline (sekitar 429 file Prettier dan 24 file Pint) tetap isu terpisah; tidak disentuh pada perubahan ini.
- Gate `typecheck` belum dipasang di CI; disarankan dijalankan bersamaan `lint` pada pipeline.
- Cakupan LSP tetap bergantung environment editor masing-masing; angka cakupan di §4 adalah snapshot, bukan jaminan.
