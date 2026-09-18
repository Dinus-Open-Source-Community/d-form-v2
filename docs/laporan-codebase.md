# Laporan Codebase — Deepening Opportunities

> **Sifat:** analisis arsitektur. Tidak ada file kode yang diubah, dihapus, atau dipindahkan.
> **Fokus:** frontend (diminta khusus), dengan backend sebagai pembanding.
> **Tanggal:** 16 September 2026
> **Deliverable:** daftar kandidat *deepening*, bukan rencana implementasi. Belum ada satu pun interface yang dirancang di sini.

---

## 0. Kosakata & Batasan

Laporan ini memakai kosakata arsitektur secara ketat:

| Istilah | Arti di laporan ini |
|---|---|
| **module** | unit yang bisa digantikan tanpa menyentuh pemakainya |
| **interface** | apa pun yang harus diketahui pemakai untuk memakai module itu |
| **depth** | seberapa banyak perilaku tersembunyi di balik interface yang sempit |
| **seam** | tempat di mana dua module bertemu dan bisa dipisahkan |
| **adapter** | module yang menyesuaikan dunia luar ke interface internal |
| **leverage** | seberapa banyak perubahan yang diserap satu interface |
| **locality** | seberapa dekat hal-hal yang berubah bersama |

Sengaja **tidak** memakai "component", "service", "API", atau "boundary" sebagai istilah arsitektur. Kalau nama file memuat kata itu (mis. `app/Services/`), itu kutipan path, bukan istilah analisis.

**Yang tidak diverifikasi:** perilaku runtime, kualitas test suite di luar jumlah, dan isi `docs/**`. Tidak ada test/build yang dijalankan.

**Ketidakhadiran yang penting:** tidak ada `CONTEXT.md` dan tidak ada `docs/adr/`. Artinya tidak ada glosarium domain bersama dan tidak ada catatan keputusan. Konsekuensinya langsung terasa di Kandidat 7.

---

## 1. Di Mana Friction Itu Terjadi (scoping)

Deepening membayar kalau bagian itu sering berubah. Dari 100 commit terakhir:

| # | File | Perubahan | Wilayah |
|---|---|---|---|
| 1 | `resources/js/pages/Dashboard/Scan/Global.vue` | 16 | Scan |
| 2 | `resources/js/lib/routes.ts` | 15 | navigasi |
| 3 | `resources/js/utils/composables/useGlobalQrScanPage.ts` | 12 | Scan |
| 4 | `components/modules/dashboard/DashboardSidebar.vue` | 9 | navigasi |
| 5 | `routes/web/admin/recruitment.php` | 8 | Recruitment |
| 6 | `pages/Dashboard/Recruitment/Periods/Show.vue` | 8 | Recruitment |
| 7 | `components/modules/dashboard/QrScanSidebar.vue` | 8 | Scan |
| 8 | `pages/Dashboard/Recruitment/Index.vue` | 7 | Recruitment |
| 9 | `components/modules/builder/FieldRenderer.vue` | 7 | Form builder |

Tiga klaster: **Scan** (16+12+8), **navigasi** (15+9), **Recruitment** (8+8+7). Form builder menempel di belakang.

Satu kontras yang menentukan seluruh laporan ini:

> **Modul backend Scan sudah dalam.** `app/Services/Scan/GlobalScanResolver.php` = 109 LOC dengan **satu** method publik dan satu tanggung jawab yang jelas. `app/Services/Scan/ScanStreamFeed.php` = 97 LOC, satu method `since()`. Keduanya punya interface sempit dan perilaku kaya.
>
> **Yang dangkal adalah frontend-nya.** Jadi friction ini bukan penyakit seluruh codebase — ini penyakit spesifik di sisi FE, dan itu membatasinya.

---

## 2. Kandidat Deepening

---

### Kandidat 1 — Identitas target scan adalah string, bukan identitas

**Badge: `STRONG`**

**Files:**
- `resources/js/utils/composables/useGlobalQrScanPage.ts` — `sessionMatchKey` (:158), `formatGlobalEventTitle` (:101), `normalizeMatchText` (:171), `sessionOptionLabel` (:146), `sessionDivisionName` (:134), `formatSessionDate` (:114)
- `app/Services/Scan/ScanStreamFeed.php` (:86)
- `resources/js/lib/qrScanUi.ts`

**Problem.**
Filter "acara mana" pada halaman Scan dicocokkan dengan cara **merekonstruksi string tampilan milik backend di frontend**. Backend menyusun judul oprec:

```php
// ScanStreamFeed.php:86
'eventTitle' => 'Oprec · '.($session?->division?->name ?? '').' · '.($session?->session_date ?? ''),
```

Lalu frontend membangun ulang bagian belakang string itu untuk dipakai sebagai kunci pencocokan:

```ts
// useGlobalQrScanPage.ts:158
function sessionMatchKey(session) {
    return `${divisionName} · ${rawDate}`
}
```

Dan karena string dari feed belum tentu sama persis (timestamp bisa ikut terbawa), ada fungsi ketiga yang memotong timestamp dengan regex supaya pencocokan berhasil:

```ts
// useGlobalQrScanPage.ts:101-112
return title.replace(/(\d{4}-\d{2}-\d{2})[T ]\d{2}:\d{2}(?::\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?/g, '$1')
```

Ditambah `normalizeMatchText` (lowercase + buang semua non-alfanumerik) karena format string tidak pernah dijanjikan stabil.

**Akar masalahnya sudah dilacak sampai ke barisnya — dan ini bukan soal "duplikasi string", ini soal serialisasi yang tidak konsisten.** Field `session_date` di-serialisasi dengan **dua cara berbeda di dua endpoint dari satu fitur yang sama**:

| Tempat | Ekspresi | Hasil |
|---|---|---|
| `InterviewSessionService.php:73` (untuk prop `targets`) | `$session->session_date?->toDateString()` | `"2026-05-01"` |
| `ScanStreamFeed.php:86` (untuk feed) | `$session?->session_date` | `"2026-05-01 00:00:00"` |
| `GlobalScanController.php:102` (untuk respons POST) | `$session?->session_date` | `"2026-05-01 00:00:00"` |

Model men-cast `'session_date' => 'date'` (`RecruitmentInterviewSession.php:33`). Interpolasi Carbon ke string memakai `__toString()` → ikut membawa ` 00:00:00`. Jadi regex di `formatGlobalEventTitle` (:111) yang memotong `[T ]\d{2}:\d{2}...` itu **bukan penanganan format judul — itu menambal bug serialisasi backend.**

Perhatikan juga bahwa kontrak string-nya **triplikat, bukan duplikat**: `ScanStreamFeed.php:86` dan `GlobalScanController.php:102` memuat ekspresi konkatenasi yang **identik karakter per karakter** — copy-paste antar dua kelas.

```
SEKARANG — satu field, tiga serialisasi, satu kontrak tak terdeklarasi
═══════════════════════════════════════════════════════════════════════

  InterviewSessionService:73        ScanStreamFeed:86        GlobalScanController:102
  toDateString()                    (Carbon __toString)      (Carbon __toString)
  "2026-05-01"                      "2026-05-01 00:00:00"    "2026-05-01 00:00:00"
        │                                   │                        │
        │ (prop targets)                    │ (feed)                 │ (respons POST)
        ▼                                   ▼                        ▼
  sessionMatchKey()  ──────► tidak cocok ──────  formatGlobalEventTitle()
                              │                        │
                    normalizeMatchText()      regex potong timestamp
                              │                        │
                              └──── diselamatkan ──────┘
```

**Dan identitasnya sudah ada di tangan pada baris yang sama.** `ScanStreamFeed.php:73` sudah mengambil `recruitment_interview_session_id` dari attendance untuk memuat sesi — nilai itu dibuang, tidak diterbitkan. Di sisi event, `$answer->form->event->id` sudah dimuat di `:54`. Jadi menambahkan identitas per baris **tidak menambah query apa pun**; ia hanya berhenti membuang data.

```
SEKARANG — kontrak berbentuk string, digandakan di dua sisi seam
═══════════════════════════════════════════════════════════════════════

  ScanStreamFeed.php:86                 useGlobalQrScanPage.ts:158
  ┌────────────────────────┐           ┌──────────────────────────┐
  │ 'Oprec · Divisi · ts'  │           │ `${divisionName} · ${d}` │
  └───────────┬────────────┘           └────────────┬─────────────┘
              │                                     │
              │      keduanya harus sepakat         │
              └──────────────► ?????????  ◄─────────┘
                                  │
                     diselamatkan 3 fungsi lagi:
                     · formatGlobalEventTitle (potong timestamp)
                     · normalizeMatchText     (lowercase + strip)
                     · sessionMatchKey        (susun ulang)
                                  │
                     ubah format judul di backend
                     → filter diam-diam rusak
```

**Solution.**
Feed row membawa **identitas** target (`targetId` + `targetKind`), dan opsi filter membawa identitas yang sama. Pencocokan jadi kesetaraan identitas buram, bukan perbandingan teks. Judul tetap dikirim — tapi hanya untuk **ditampilkan**, tidak pernah untuk dicocokkan.

**Benefits (locality & leverage).**
- **locality:** identitas dan tampilan berpisah. Mengubah format judul oprec tidak lagi menyentuh logika filter di frontend.
- **leverage:** satu field pada feed row menghapus empat fungsi privat dan dua aturan pencocokan khusus per-kind (`haystack === wanted` untuk event, `haystack.includes(wanted)` untuk oprec — :295).
- **test surface:** hari ini filter hanya bisa diuji lewat 896 LOC module yang butuh DOM + kamera. Setelah dipisah, "apakah scan X masuk ke target Y" jadi keputusan murni dengan input dan output, dan bisa diuji tanpa browser.
- **deletion test:** ya — menghapus keempat fungsi itu **memusatkan** kompleksitas menjadi satu field identitas, bukan memindahkannya. Ini sinyal terkuat di seluruh laporan.

```
SETELAH — identitas, bukan teks
═══════════════════════════════════════════════════════════════════════

  feed row  { targetId: 'sess_abc', targetKind: 'oprec',
              title: 'Divisi Acara · 1 Mei 2026' }   ← display saja
                      │
                      ▼
  target option { id: 'sess_abc', kind: 'oprec' }
                      │
                 entry.targetId === option.id
                      │
        4 fungsi parsing hilang, bukan pindah
```

---

### Kandidat 2 — `useGlobalQrScanPage` memiliki enam module di dalam satu interface

**Badge: `STRONG`**

**Files:** `resources/js/utils/composables/useGlobalQrScanPage.ts` (896 LOC), dikonsumsi oleh `pages/Dashboard/Scan/Global.vue`

**Problem.**
Interface-nya mengembalikan **32 anggota**. Di dalamnya ada enam tanggung jawab yang tidak berhubungan:

| Tanggung jawab | LOC (perkiraan) | Bukti |
|---|---|---|
| Siklus hidup kamera | ~200 | `loadCameras` :716, `startCameraScanner` :743, `stopCameraScanner` :794, `switchCamera` :814, `triggerShutter` :671, `resumeScannerAfterShutter` :657 |
| Pengiriman scan + taksonomi error | ~170 | `submitScanPayload` :468 dengan cabang 409/422/429/jaringan (:540-633) |
| Polling feed | ~180 | `pollFeed` :419, `startFeedPolling` :445, `applyFeed` :404, `ingestFeedRow` :370, `seenFeedIds`/`feedCursor` :221-222 |
| Derivasi opsi target | ~180 | `targetOptions` :236, `sessionOptionLabel` :146, `sessionMatchKey` :158, `eventOptionLabel` :165, `formatSessionDate` :114 |
| Proyeksi KPI/hero | ~120 | `targetEntries` :272, `todayEntries` :301, tiga penghitung :302-304, `summary` :306, `heroResult` :332 |
| State tampilan + identitas meja | tersebar | `logExpanded` :214, `logQuery` :215, `selectedTarget` :213, `resolveDeskId` :73 (`sessionStorage`) |

Module ini juga **menjangkau keluar interface-nya**:

```ts
// :646-655 — tahu struktur DOM internal html5-qrcode
const container = document.getElementById(scannerContainerId)
container.querySelectorAll<HTMLDivElement>(':scope > div').forEach((notice) => {
    notice.style.display = 'none'
})
```

Plus: memiliki `AudioContext` + `navigator.vibrate` (lewat `playScanBeep`, `lib/qrScanUi.ts:159`), memiliki `sessionStorage` (:73-91), dan memiliki 12 side-effect `toast.*`.

Dan ada interface yang mati: dari 32 anggota, **4 tidak dipakai oleh satu-satunya pemakainya** — `summary`, `processScan`, `submitScanPayload`, `selectTarget`.

```
SEKARANG — satu interface 32 anggota untuk 6 tanggung jawab
═══════════════════════════════════════════════════════════════════════

                    ┌──────────────────────────────────┐
                    │  useGlobalQrScanPage (896 LOC)   │
   Global.vue ──────┤  ─────────────────────────────── │
   (satu pemakai)   │  kamera        (HTML5Qrcode)     │
                    │  DOM reach-in  (getElementById)  │
                    │  HTTP submit   (+ 409/422/429)   │
                    │  feed polling  (interval+cursor) │
                    │  derivasi target (6 fungsi)      │
                    │  KPI/hero proyeksi (7 computed)  │
                    │  toast ×12 · sessionStorage      │
                    └──────────────────────────────────┘
                                  │
                      32 anggota keluar, 4 di antaranya
                        tidak dipakai siapa pun
```

**Solution.**
Tiga module dengan interface sempit: satu **adapter kamera** (menyembunyikan `Html5Qrcode` beserta DOM-nya), satu **penerima scan** (mengubah respons HTTP menjadi hasil domain, termasuk taksonomi error), satu **feed** (polling + cursor + dedup). `Global.vue` menyusun ketiganya. Kandidat 1 akan mengecilkan bagian derivasi target secara terpisah.

**Benefits.**
- **locality:** aturan dedup feed (Kandidat: `seenFeedIds`, `localEntryIdentities`, `scanIdentity` :184) berhenti bercampur dengan state kamera.
- **leverage:** mengganti `html5-qrcode` hari ini berarti menyunting module berisi logika KPI, filter, dan HTTP. Setelah dipisah, hanya adapter kamera yang berubah — itu **adapter** dalam arti sebenarnya, dan per definisi: satu adapter = seam hipotetis, dua = seam nyata (di sini akan ada dua: kamera dan HTTP).
- **test surface:** penerimaan scan bisa diuji dengan respons HTTP sebagai input, tanpa kamera dan tanpa DOM.
- **deletion test:** menghapus module ini tidak memusatkan apa pun — kompleksitasnya akan meledak ke `Global.vue`. Jadi masalahnya bukan "dangkal" melainkan **bocor**: interface sempit tapi kebocoran banyak. Perbaikannya adalah memecah, bukan menghapus.

---

### Kandidat 3 — Pengetahuan tipe field tersebar di 13 file dan tiga rantai render

**Badge: `STRONG`**

**Files** (jumlah = kemunculan literal tipe field; `divider` insidental dikecualikan):
- `components/modules/builder/fieldMapping.ts` (**13**, 277 LOC)
- `components/modules/builder/FieldRenderer.vue` (421 LOC, **11** literal + **13 cabang render** `v-if`/`v-else-if` :208-417)
- `components/modules/builder/FieldEditor.vue` (**9**)
- `components/modules/builder/FormPreviewDialog.vue` (**5**)
- `components/modules/builder/formBuilderPalette.ts` (**4**)
- `utils/composables/useFormBuilderDemoPage.ts` (**4**)
- `components/modules/dashboard/FormFillFieldSlotRows.vue` (**4**)
- `utils/composables/useFormFillPage.ts` (**3**)
- `types/event.d.ts` (**2**)
- `pages/Dashboard/User/TeamInvitation.vue` (**2**)
- `components/modules/dashboard/FormFieldAnswerDisplay.vue` (**2**)
- `components/modules/dashboard/FormFillFieldsList.vue` (263 LOC, **1**)
- `pages/Docs.vue` (**1**)

**Problem.**
Ada **tiga rantai render independen** untuk himpunan tipe field yang sama — `FieldRenderer.vue`, `FormPreviewDialog.vue`, dan `TeamInvitation.vue`:

```
$ rg -l 'v-else-if="field.type ===' resources/js
resources/js/pages/Dashboard/User/TeamInvitation.vue
resources/js/components/modules/builder/FormPreviewDialog.vue
resources/js/components/modules/builder/FieldRenderer.vue
```

Dan `FieldRenderer.vue` punya peta tipe sendiri:

```ts
// :53-70 — 16 entri: ikon + label + tone, khusus untuk satu mode render
const TYPE_CONFIG = { short_text: {...}, long_text: {...}, … }
```

Sementara `fieldMapping.ts` (277 LOC) sudah menyimpan sebagian pengetahuan tipe yang sama, dan `formBuilderPalette.ts` menyimpan sebagian lagi. Perhatikan juga bahwa `FieldRenderer.vue:87-93` mendefinisikan ulang peta placeholder per tipe, dan `:102-106` mengarang tiga opsi palsu ketika field belum punya opsi.

Akibatnya "satu tipe field" bukan satu konsep di codebase ini — ia adalah **enam tempat yang harus sepakat**.

**Dan ketidaksepakatannya sudah terjadi sekarang.** Himpunan tipe dideklarasikan **empat kali dengan tiga kosakata berbeda**, ditambah satu encoding kelima di kolom JSON:

| Deklarasi | Isi | Jumlah |
|---|---|---|
| DB `form_fields.input_type` | `input`/`selectInput`/`textarea`/`datePicker`/`fileUpload`/`radio`/`checkbox`/`banner` | 8 |
| `app/Support/FormFieldTypeMapping.php:13` `API_TYPES` | sama, tapi `select` (bukan `selectInput`) | 8 |
| `resources/js/types/form-builder.ts:24` `BackendFieldType` | **`banner` tidak ada** | 7 |
| `resources/js/components/modules/builder/fieldMapping.ts:3` (komentar) | klaim *"5 backend API types"* | salah |
| `metadata.builderType` (kolom JSON) | 17 tipe kaya | 17 |

Konsekuensi yang bisa diperiksa:

- **`banner` adalah tipe yang sah di backend** (`FormFieldTypeMapping::API_TYPES:13` memuatnya, dan `FormFieldValidationRequest` memvalidasinya) **tapi tidak ada di union TypeScript frontend** dan **tidak ada di `formBuilderPalette.ts`** (nol kemunculan). Artinya: builder tidak bisa membuatnya, type system frontend tidak tahu ia ada, tapi `fieldMapping.ts:183` punya `case 'banner'` dan `FormFillFieldsList.vue:146` merendernya sebagai `<div class="hidden" />`.
- **`guessType()` (:202-216) tidak bisa membalikkan pemetaan.** Tanpa `metadata.builderType`, `image_upload` dan `file_upload` sama-sama jadi `file_upload` (:214); `time`, `rating`, `heading`, dan `divider` semuanya jatuh ke `short_text`; `paragraph` tidak bisa dibedakan dari `long_text`. Untuk baris lama yang `builderType`-nya hilang, tipe field **terdegradasi diam-diam**.
- **`rules.in` adalah CSV dari label** (`:133`, `:147`, `:161`). Satu label yang mengandung koma mematahkan daftar pilihan.
- **Opsi bergambar dibuang untuk dropdown**: `:126-131` memaksa setiap opsi jadi `type: 'text'` dan `imageUrl: ''`; `fromBackendField` mengulanginya di `:238-243`.

**Solution.**
Satu **registry tipe field**: satu module yang, untuk setiap tipe, menyatakan ikon/label/tone, bentuk editornya, bentuk preview-nya, bentuk isiannya, dan bentuk tampilan jawabannya. Ketiga rantai render menjadi pemakai data, bukan salinan pengetahuan.

**Benefits.**
- **leverage:** menambah satu tipe field hari ini berarti menyunting ~6 file dan berharap ketiganya tidak drift. Setelah registry ada, satu entri.
- **locality:** `TYPE_CONFIG`, peta placeholder, dan opsi default palsu (`:102-106`) berhenti hidup terpisah dari definisi tipenya.
- **test surface:** "setiap tipe field punya ikon, label, dan renderer untuk keempat mode" menjadi pernyataan yang bisa gagal — hari ini tidak ada yang bisa memeriksanya, karena tidak ada satu pun tempat yang memuat seluruh himpunan.
- **deletion test:** menghapus `TYPE_CONFIG` dan peta-peta kembarannya memusatkan kompleksitas ke satu registry. Ya.

```
SEKARANG                              SETELAH
════════════════════════════          ════════════════════════════
FieldRenderer   ─┐                    ┌──────────────────┐
FieldEditor     ─┤                    │  field-type      │
fieldMapping    ─┤  6 salinan         │  registry        │
FormPreviewDlg  ─┼─ pengetahuan ──►   └────────┬─────────┘
formBuilderPal  ─┤   tipe field                │ data
FormFillField…  ─┤                    ┌────────┼────────┐
AnswerDisplay   ─┘                    ▼        ▼        ▼
                                   builder  preview   fill
                                   (3 modul jadi pemakai, bukan salinan)
```

---

### Kandidat 4 — Navigasi: registry manual + dua rantai `if` yang salah satunya berisi cabang kembar

**Badge: `STRONG`** (untuk rantai `if`), `WORTH EXPLORING` (untuk registry-nya)

**Files:** `resources/js/lib/routes.ts` (253 LOC, 68 pemakai, 15 perubahan), `components/modules/dashboard/DashboardSidebar.vue` (314 LOC, 9 perubahan)

**Problem.**
Pengetahuan "item navigasi mana yang aktif" tinggal di `lib/routes.ts` sebagai rantai 12 cabang:

```ts
// lib/routes.ts:190-231 — isSidebarNavActive: 12 cabang, pola sama
```

Di dalamnya ada **cabang yang identik dua kali**:

```ts
// :208-210
if (href.startsWith(routes.admin.recruitment.myInterviews.index)) {
    return path.startsWith(routes.admin.recruitment.myInterviews.index);
}
// :220-222  ← sama persis
if (href.startsWith(routes.admin.recruitment.myInterviews.index)) {
    return path.startsWith(routes.admin.recruitment.myInterviews.index);
}
```

Dan `resolveNavbarFallbackBackHref` (:233-253) adalah rantai 5 cabang dengan bentuk yang sama persis.

Kembarannya ada di `DashboardSidebar.vue`, yang memutuskan **visibilitas** item lewat lima computed terpisah (`mainNavItems` :56, `managementItems` :60, `interviewerNavItems` :92, `recruitmentOpsItems` :98, `recruitmentSettingsItems` :115) — dan di dalam `managementItems` ada dua cabang yang mendorong item identik:

```ts
// :77-81
if (!canManageEvents.value && !canAccessRecruitment.value) {
    items.push({ label: 'Acara diikuti', … }, { label: 'Jelajah acara', … })
// :82-87  ← kondisi berbeda, isi sama
} else if (!canManageEvents.value && canAccessRecruitment.value) {
    items.push({ label: 'Acara diikuti', … }, { label: 'Jelajah acara', … })
}
```

Jadi untuk menambah satu item navigasi, kamu menyentuh **dua file** dan **satu rantai `if`**, dan untuk membuatnya aktif dengan benar kamu menambah **cabang ke-13**.

**Solution.**
Setiap item navigasi menyatakan sendiri aturan keaktifannya (dan syarat izinnya), lalu **satu** aturan generik menyelesaikan keaktifan. Kedua rantai `if` digantikan oleh data.

**Benefits.**
- **locality:** izin, visibilitas, dan keaktifan satu item berhenti tersebar di dua module.
- **leverage:** `isSidebarNavActive` saat ini adalah interface `(href, currentUrl) => boolean` yang menyembunyikan 12 keputusan berbeda. Satu aturan generik menyerap semua item mendatang.
- **test surface:** rantai `if` dengan 12 cabang tidak punya permukaan uji yang masuk akal selain 12 kasus yang ditulis manual. Sebagai data, satu tabel kasus cukup.
- **deletion test:** menghapus kedua rantai **memusatkan** kompleksitas ke struktur data item. Ya.
- Catatan: ini juga menutup temuan laporan sebelumnya — `lib/routes.ts` adalah registry **manual** yang bersaing dengan `resources/js/routes/**` hasil generate (54 file / 7.308 LOC) yang **0 pemakai**. Pilihan sistem route perlu diputuskan sebelum atau bersamaan dengan deepening ini, karena keduanya menyentuh interface yang sama.

---

### Kandidat 5 — `lib/error-message.ts` adalah tabel terjemahan yang menyamar sebagai module

**Badge: `WORTH EXPLORING`**

**Files:** `resources/js/lib/error-message.ts` (419 LOC), `lang/id.json` (87 baris), `lang/en.json` (77 baris)

**Problem.**
Interface-nya terlihat rapi, tapi 95 baris pertama file itu adalah **kamus Inggris→Indonesia yang di-hardcode di frontend**:

```ts
// :40-134 — KNOWN_BACKEND_MESSAGES, ~90 pasangan
'This bundle form requires a team size of at least 2 in form settings.':
    'Form bundle ini membutuhkan ukuran tim minimal 2 di pengaturan form.',
…
```

Padahal backend sudah punya tempat untuk ini — `lang/id.json` ada. Setiap pesan backend yang berubah bentuknya akan lolos ke layar pengguna dalam bahasa Inggris, dan tidak ada seam yang menghubungkan kedua sisi.

Tiga cacat konkret di dalamnya:

1. **Entri mati** — memetakan string ke dirinya sendiri (:130-131):
   ```ts
   'Gagal menyimpan. Periksa field yang ditandai lalu coba lagi.':
       'Gagal menyimpan. Periksa field yang ditandai lalu coba lagi.',
   ```
2. **Pemanggilan yang salah argumen** (:246):
   ```ts
   return injectFieldLabel(message, label, label)
   ```
   `injectFieldLabel(message, key, label)` membangun varian regex **dari `key`**. Di sini `label` dikirim sebagai `key`, sehingga fungsi itu mencoba mengganti label manusia dengan dirinya sendiri. Efeknya tidak terlihat karena hasilnya kebetulan sama — tapi ini bug yang menunggu input yang tidak biasa.
3. **Tiga nama untuk dua perilaku** — `showValidationErrorToast` (:291) dan `handleInertiaFormErrors` (:320-325, alias murni) dan `showEventValidationToast` (:406, `@deprecated`).

Ditambah dua aturan Laravel yang identik hasilnya (`LARAVEL_ID_RULES` :159-162 menghasilkan string yang sama untuk dua pola berbeda).

**Solution.**
Satu seam untuk resolusi pesan→label, dimiliki oleh tempat yang memiliki pesannya (backend `lang/`). Module ini tinggal merender dan menyajikan. Interface-nya menyempit ke satu jalur masuk error.

**Benefits.**
- **locality:** kosakata pesan berhenti hidup di frontend.
- **leverage:** sembilan titik masuk penyajian error (`humanizeErrorMessage`, `parseApiErrorMessage`, `showErrorToast`, `showHttpErrorToast`, `showFlashToast`, `showValidationErrorToast`, `handleInertiaFormErrors`, `showEventValidationToast`, `getFieldError`) menjadi satu jalur + satu pemetaan.
- **test surface:** pemetaan pesan bisa diuji sebagai data; hari ini ia hanya bisa diuji dengan memanggil fungsi yang menembak toast.
- **deletion test:** menghapus kamus ini tidak memindahkan kompleksitas — ia menghapus duplikasi. Namun: kalau backend tidak bisa menjadi pemiliknya, kompleksitasnya berpindah, bukan hilang. Karena itu badge-nya `Worth exploring`, bukan `Strong`. Keputusan ini dipengaruhi Kandidat 7.

---

### Kandidat 6 — Filter/pagination/URL-sync di klaster Recruitment, menulis ulang module yang sudah ada tapi tak dipakai

**Badge: `WORTH EXPLORING`**

**Files:** `components/modules/dashboard/recruitment/PeriodApplicantSection.vue` (621 LOC, 7 perubahan), `components/ui/pagination/` (9 file, **0 pemakai eksternal**)

**Problem.**
Module ini memikul sekaligus:
- state filter (search/divisi/tahap/antrean/semester/per-halaman) :78-83
- sinkronisasi dua arah URL ↔ state, dengan flag mutable untuk membungkam watcher:

  ```ts
  let suppressFilterApply = false        // :131
  if (suppressFilterApply) return        // :204
  watch([search, divisionId, stage, semester, queue], () => applyFilters())   // :221
  watch(() => props.query, readQueryFromProps, { deep: true })                // :222
  readQueryFromProps()                   // dipanggil saat setup, :201
  ```
- matematika pagination, ditulis tangan, termasuk logika elipsis:

  ```ts
  const visiblePages = computed<(number | string)[]>(…)   // :168-186, ~19 LOC
  const rangeStart / rangeEnd                             // :151-166
  ```
- tabel + dua alur aksi (loloskan/tolak) + dialog
- taksonomi alasan penolakan sebagai data di module presentasi:

  ```ts
  const REJECT_REASONS: RejectReasonOption[] = [ … 7 entri … ]   // :238-246
  ```

Yang membuat ini kandidat deepening, bukan sekadar "file panjang": **modul pagination sudah ada dan tidak dipakai.**

```
$ rg -lF "components/ui/pagination" resources/js --glob '!resources/js/components/ui/**'
(pagination → 0 pemakai)

Padahal ada 9 file:
  components/ui/pagination/{Pagination,PaginationContent,PaginationEllipsis,
                            PaginationFirst,PaginationItem,PaginationLast,
                            PaginationNext,PaginationPrevious}.vue
```

Jadi seam sudah pernah dibuat dan ditinggalkan; `visiblePages` adalah reimplementasinya di dalam module lain.

**Dan `FormSubmissionsPagination.vue` sudah membuktikan polanya bisa dipakai ulang** — 59 LOC, dipakai `Submissions.vue`, `useFormSubmissionsPage.ts`, dan `formSubmissionsUi.ts`. Jadi ada **tiga pagination** di codebase ini: satu modul vendored yang nol pemakai, satu modul bespoke yang dipakai satu fitur, dan satu implementasi tangan di dalam `PeriodApplicantSection`. Yang ketiga menyalin yang kedua tanpa memakainya. Ditambah `suppressFilterApply` — flag mutable tingkat module yang menyembunyikan urutan efek — membuat perilaku filter tidak bisa diuji lewat interface-nya.

**Solution.**
Pisahkan satu module "daftar berfilter" yang memiliki state filter, sinkronisasi URL, dan pagination — lalu pakai ulang module pagination yang sudah ada. `PeriodApplicantSection` menjadi pemakai data, bukan pemilik protokol URL.

**Benefits.**
- **locality:** aturan URL ↔ state berhenti hidup bersama tabel dan dialog aksi. `suppressFilterApply` hilang bersama kebutuhan akan watcher ganda.
- **leverage:** klaster ini muncul di `Periods/Show.vue` (8 perubahan) dan `Recruitment/Index.vue` (7 perubahan) dengan bentuk serupa. Satu module melayani ketiganya.
- **test surface:** pagination + sinkronisasi URL bisa diuji sebagai keputusan murni; hari ini hanya bisa diuji lewat `router.get` Inertia.
- **Catatan:** `REJECT_REASONS` berisi kosakata domain (alasan penolakan) di module presentasi. Perlu dicek apakah backend punya enum untuk ini — kalau ya, itu seam yang bocor.

---

### Kandidat 7 — Keputusan arsitektur yang tidak ikut bepergian

**Badge: `WORTH EXPLORING`**

**Files:** `app/Services/Scan/ScanStreamFeed.php` (:13-23), `.gitignore` (:55), `docs/superpowers/specs/2026-09-15-scan-oprec-hardening-spec.md` (tidak ada di repo)

**Problem.**
`ScanStreamFeed` membawa kontrak eksplisit di doc-block-nya:

```php
/**
 * ACCEPTED RISK (M2, 2026-09-15): feed sengaja mengembalikan kedua domain
 * kepada siapa pun yang memiliki `events.list` ATAU
 * `recruitment.attendance.scan`, tanpa memfilter per domain. …
 * Jangan "perbaiki" tanpa menyetujui ulang keputusan ini.
 * Spec: docs/superpowers/specs/2026-09-15-scan-oprec-hardening-spec.md
 */
```

Ini interface yang **sengaja dibuat lebih permisif daripada yang terlihat benar** — dan alasannya terdokumentasi dengan baik. Masalahnya: spec yang dirujuk **tidak ada di repo**, karena `.gitignore:55` mengabaikan `docs/superpowers/`.

Jadi instruksi "jangan perbaiki" itu mengikat, tapi buktinya tidak ikut. Ini bukan konflik ADR (tidak ada ADR di repo ini) — ini **ketiadaan** ADR. Dan `CONTEXT.md` juga tidak ada, sehingga istilah domain seperti "accepted risk", "bundle", "team form", "queue", "reject reason" tidak punya definisi bersama.

**Solution.**
Naikkan keputusan ini ke catatan keputusan yang ikut ter-commit, dan buat glosarium domain. Ini prasyarat untuk Kandidat 1, 5, dan 6: semuanya menyentuh istilah dan kontrak yang saat ini hanya hidup di kepala atau di file yang di-ignore.

**Benefits.**
- **leverage:** satu catatan keputusan mencegah setiap penelusur berikutnya mengusulkan "perbaikan" yang sama dan sama-sama ditolak.
- **locality:** kontrak `ScanStreamFeed` berhenti bergantung pada file yang hanya ada di satu mesin.
- Ini juga satu-satunya kandidat yang **tidak** tentang memecah module — ia tentang membuat keputusan bisa ditemukan. Kalau ini dikerjakan lebih dulu, tiga kandidat lain jadi lebih murah.

**Catatan sesuai aturan:** ini belum konflik dengan ADR mana pun (ADR tidak ada), jadi tidak ada peringatan konflik yang perlu dipasang. Kalau kamu menolak kandidat lain karena alasan yang akan dibutuhkan penelusur berikutnya untuk tidak mengusulkannya lagi, di situ ADR perlu dibuat.

---

## 3. Yang Sudah Dalam (jangan diutak-atik)

Supaya laporan ini tidak menyesatkan arah: beberapa module sudah punya interface sempit dan perilaku kaya.

| Module | LOC | Interface | Kenapa sudah dalam |
|---|---|---|---|
| `app/Services/Scan/GlobalScanResolver.php` | 109 | 1 method `resolve(string): array` | Menyembunyikan 5 skema identitas QR (payload recruitment, payload event, UUID, kode `OPREC-…`, `registration_code`) di balik satu pintu |
| `app/Services/Scan/ScanStreamFeed.php` | 97 | 1 method `since(?string, int): array` | Menggabungkan dua domain + cursor + pengurutan, memaparkan daftar datar |
| `resources/js/lib/formFieldMetadata.ts` | 36 | 3 fungsi | Mengurung jebakan `Boolean("false") === true` di satu tempat — contoh tepat adapter tipis yang layak |
| `resources/js/pages/Dashboard/Scan/Global.vue` | 233 | — | Tipis dan deklaratif. Deepening Kandidat 2 **mempertahankan** bentuk ini, bukan mengubahnya |

`GlobalScanResolver` layak jadi acuan gaya: satu pintu masuk, banyak pengetahuan.

---

## 4. Top Recommendation

**Kandidat 1 — identitas target scan sebagai identitas, bukan string.**

Kalau hanya satu yang dikerjakan, ini yang paling menguntungkan, dengan alasan yang bisa diperiksa:

1. **Berada di klaster terpanas.** Scan menyumbang 16 + 12 + 8 perubahan pada 100 commit terakhir — dua file teratas dan file ketiga.
2. **Penghapusan, bukan pemindahan.** Ini satu-satunya kandidat yang lolos deletion test secara tegas: menghapus `sessionMatchKey`, `formatGlobalEventTitle`, `normalizeMatchText`, dan `sessionDivisionName` **menghapus** kebutuhan, tidak memindahkannya.
3. **Membuka Kandidat 2.** Begitu derivasi target mengecil, `useGlobalQrScanPage` tinggal punya lima tanggung jawab, dan pemecahannya jadi pekerjaan mekanis.
4. **Resikonya terkendali dan terlihat.** Perubahan kontraknya ada di `ScanStreamFeed.php` dan pemakainya. Kalau `targetId` tidak terkirim dengan benar, filter gagal secara nyata di layar — bukan gagal diam-diam.
5. **Test-nya sudah ada.** `tests/Feature/Scan/GlobalScanTest.php` dan `RecruitmentAttendanceQueueTest.php` menyediakan jaring untuk perubahan ini, sesuatu yang tidak dimiliki Kandidat 3 dan 6.

**Urutan yang aku sarankan kalau dikerjakan lebih dari satu:** Kandidat 7 dulu (murah, membuka yang lain) → Kandidat 1 (nilai tertinggi) → Kandidat 2 (memakan hasil Kandidat 1) → Kandidat 4 (butuh keputusan sistem route) → Kandidat 3 dan 6 (paling besar, butuh registry dan module baru).

**Satu hal yang harus diputuskan sebelum Kandidat 3 dan 6:** apakah `components/ui/**` yang sekarang punya 8 folder tanpa pemakai eksternal (termasuk `pagination`) dianggap sebagai **module milik proyek** atau **vendored pihak ketiga yang boleh diabaikan**. Kandidat 6 bergantung pada jawaban itu; kalau `components/ui` dianggap vendored, maka "pakai ulang pagination" bukan solusi yang sah dan Kandidat 6 perlu dirumuskan ulang.

---

## 5. Lampiran — Ringkasan Kandidat

| # | Kandidat | Wilayah | Interface sekarang | Sinyal | Badge |
|---|---|---|---|---|---|
| 1 | Identitas target scan = string | Scan | 4 fungsi parsing + 2 aturan per-kind | Kontrak digandakan di 2 bahasa | `STRONG` |
| 2 | `useGlobalQrScanPage` 6 module | Scan | 32 anggota, 4 mati | Bocor ke DOM, storage, audio | `STRONG` |
| 3 | Tipe field di 13 file | Form builder | 3 rantai render | 6 tempat harus sepakat | `STRONG` |
| 4 | Navigasi: 2 rantai `if` | navigasi | `(href, path) => boolean` | Cabang kembar; izin & keaktifan terpisah | `STRONG` |
| 5 | Kamus terjemahan di FE | lintas | 9 titik masuk error | ~90 pasangan + 3 cacat | `WORTH EXPLORING` |
| 6 | Filter/pagination/URL | Recruitment | flag mutable tersembunyi | Menulis ulang module yang 0 pemakai | `WORTH EXPLORING` |
| 7 | Keputusan tidak ikut commit | lintas | — | Spec dirujuk, di-gitignore | `WORTH EXPLORING` |

Tidak ada kandidat `SPECULATIVE` di laporan ini — semuanya punya bukti langsung dari kode. Yang paling lemah landasannya adalah Kandidat 5, karena solusinya bergantung pada apakah backend bisa menjadi pemilik kosakata pesan.

---

## 6. Keputusan (hasil grilling ronde 1)

Delapan keputusan, direkam di sini supaya tidak hidup di kepala.

| # | Keputusan | Konsekuensi |
|---|---|---|
| D1 | Urutan eksekusi **sekuensial**: 7 → 1 → 2 → 4 → 3 → 6. Setiap tahap berhenti setelah verifikasi hijau. **Deliverable tahap ini: laporan, tanpa perubahan kode.** | Tidak ada perubahan file kode pada tahap ini |
| D2 | `components/ui/**` = **module milik proyek**, dan folder yang nol pemakai **boleh dihapus** | Kandidat 3 dan 6 boleh memakai/menghapus isi `components/ui` |
| D3 | Kandidat 1: emit **`targetId` + `targetKind`**, **dan** backend menyatukan serialisasi `session_date` | Menghapus 4 fungsi parsing + akar bug serialisasi |
| D4 | Kandidat 2: pecah jadi **3 module** (adapter kamera / penerima scan / feed), **dan** perbaiki bug cursor | Cursor perlu tiebreaker (`ts` + `id`) |
| D5 | Kandidat 3: **`metadata.builderType` tetap** jadi kunci registry; **`banner` dimasukkan** (bukan dihapus) | Tanpa migrasi DB; union TS + palette + registry diperbaiki |
| D6 | Kandidat 4: **migrasi ke `@/routes/**` generated**; `lib/routes.ts` menyisakan helper murni | 7.308 LOC generated berhenti jadi build waste; rantai `if` 12 cabang hilang |
| D7 | Kandidat 5: **backend jadi pemilik kosakata pesan**, bertahap lewat `__()` | Tabel FE menyusut seiring migrasi |
| D8 | Kandidat 7: **ADR + `CONTEXT.md` + keluarkan `docs/superpowers/` dari `.gitignore`**, dikerjakan paling awal | Dibatasi oleh D1: isinya ada di Bagian 8 laporan ini, belum dibuat sebagai file |

**Ronde 2 yang belum diminta:** Kandidat 6 (bentuk module daftar-berfilter, dan pagination mana yang jadi kanonik) — bergantung pada D2, yang kini sudah terjawab, jadi ronde itu bisa dibuka kapan saja.

---

## 7. Spesifikasi Eksekusi — Kandidat 1

Ini spesifikasi kerja, bukan kode. Tidak ada file yang diubah.

### 7.1 Prinsip yang mengikat

**Identitas tidak boleh berupa teks tampilan.** Setiap kali sebuah keputusan bergantung pada string yang dibaca manusia, string itu akan berubah dan keputusannya ikut rusak diam-diam. Itulah yang terjadi di sini, dan itulah yang dihentikan.

### 7.2 Pekerjaan A — terbitkan identitas, berhenti membuangnya

| Lokasi | Sekarang | Menjadi |
|---|---|---|
| `ScanStreamFeed.php:82-90` (baris recruitment) | baris hanya punya `id` (`rec:<attendance_id>`), `eventTitle`, dst | tambah `targetId` = `recruitment_interview_session_id`, `targetKind` = `'oprec'` |
| `ScanStreamFeed.php:76-81` | `$sessions->get($attendance->recruitment_interview_session_id)` — id itu **sudah dipegang** | nilai yang sama diterbitkan, bukan dibuang |
| `ScanStreamFeed.php:50-58` (baris event) | `eventTitle` = judul event | tambah `targetId` = `$answer->form->event->id`, `targetKind` = `'event'` |
| `GlobalScanController.php:100-107` (respons POST) | `eventTitle` saja | tambah `targetId` + `targetKind` yang sama |
| `lib/qrScanUi.ts:73-81` `GlobalScanFeedRow` | tanpa identitas | tambah `targetId: string`, `targetKind: 'event' \| 'oprec'` |
| `useGlobalQrScanPage.ts:388-398` `ingestFeedRow` | menyusun `ScanEntry` dari string | teruskan `targetId`/`targetKind` |
| `lib/qrScanUi.ts:15-25` `ScanEntry` | tanpa identitas | tambah `targetId: string` |
| `lib/qrScanUi.ts:27-36` `ScanResult` | tanpa identitas | tambah `targetId: string` |

Catatan penting: **tidak ada query baru.** Sisi recruitment sudah memuat sesi di `:71-75`; sisi event sudah eager-load `form.event` di `:41`. Yang berubah hanya apa yang diterbitkan.

### 7.3 Pekerjaan B — pencocokan jadi kesetaraan identitas

| Lokasi | Aksi |
|---|---|
| `useGlobalQrScanPage.ts:272-297` `targetEntries` | ganti seluruh logika `normalizeMatchText` + cabang `haystack === wanted` / `haystack.includes(wanted)` dengan `entry.targetId === option.id` |
| `useGlobalQrScanPage.ts:158-163` `sessionMatchKey` | **hapus** |
| `useGlobalQrScanPage.ts:134-144` `sessionDivisionName` | dipertahankan **hanya** untuk label tampilan (:147-151), tidak lagi untuk pencocokan |
| `useGlobalQrScanPage.ts:114-126` `formatSessionDate` | dipertahankan untuk label tampilan; pencocokan tidak lagi bergantung padanya |
| `useGlobalQrScanPage.ts:171-173` `normalizeMatchText` | **hapus** |
| `useGlobalQrScanPage.ts:184-191` `scanIdentity` | dedup (:359-361, :381) berbasis string → ganti jadi kunci identitas (`targetId` + identifier) |
| `useGlobalQrScanPage.ts:33` `matchKey` pada `GlobalScanTargetOption` | **hapus** dari tipe dan dari kedua tempat pembuatannya (:244, :250) |

Yang **tidak** dihapus: `sessionOptionLabel` (:146) dan `eventOptionLabel` (:165) — keduanya menyusun label filter untuk mata manusia, dan itu memang tugasnya.

### 7.4 Pekerjaan C — hentikan serialisasi ganda `session_date`

**Satu field, dua serialisasi, tiga tempat.** Pilih satu produksi, bukan tiga.

| Lokasi | Sekarang | Menjadi |
|---|---|---|
| `InterviewSessionService.php:73` | `->toDateString()` → `"2026-05-01"` | satu bentuk kanonik |
| `ScanStreamFeed.php:86` | interpolasi Carbon → `"2026-05-01 00:00:00"` | bentuk kanonik yang sama |
| `GlobalScanController.php:102` | interpolasi Carbon → `"2026-05-01 00:00:00"` | bentuk kanonik yang sama |

**Rekomendasi kuat:** jangan hanya menyamakan format — **pindahkan penyusunan judul ke satu tempat**. Judul `"Oprec · Divisi · tanggal"` adalah satu konsep domain ("bagaimana sesi ini disebut di meja scan"), dan menyusunnya dua kali di dua controller adalah cacat sebenarnya. Satu produsen, dua pemakai.

Sesudah Pekerjaan C, `formatGlobalEventTitle` (:101-112) kehilangan alasan keberadaannya — regex pemotong timestamp tidak lagi punya timestamp untuk dipotong. Fungsi itu bisa dihapus, bukan disederhanakan.

### 7.5 Berkas yang tersentuh

| Berkas | Jenis perubahan |
|---|---|
| `app/Services/Scan/ScanStreamFeed.php` | terbitkan identitas; pakai bentuk tanggal kanonik |
| `app/Http/Controllers/Dashboard/Scan/GlobalScanController.php` | terbitkan identitas; pakai penyusun judul bersama |
| `app/Services/Recruitment/InterviewSessionService.php` | samakan serialisasi tanggal |
| `resources/js/lib/qrScanUi.ts` | tipe `ScanEntry`, `ScanResult`, `GlobalScanFeedRow` |
| `resources/js/utils/composables/useGlobalQrScanPage.ts` | buang 3 fungsi, ganti pencocokan, ganti dedup |
| *(satu tempat baru)* | penyusun judul sesi — milik backend, karena backend yang memiliki `division` dan `session_date` |

Tidak tersentuh: `QrScanSidebar.vue`, `QrScanScannerCard.vue`, `Global.vue` (kecuali jika `matchKey` ikut dihapus dari tipe yang mereka impor — tidak, keduanya tidak memakainya).

### 7.6 Verifikasi yang membuktikan perbaikan ini berhasil

**Test yang sudah ada dan harus tetap hijau:** `tests/Feature/Scan/GlobalScanTest.php`, `tests/Feature/Recruitment/RecruitmentAttendanceQueueTest.php`.

**Test baru yang wajib ada** (ini yang akan mencegah bug yang sama terulang):

1. **Test kontrak identitas** — untuk satu sesi yang sama, `targets.sessions[].id` dari `GlobalScanController::show` **sama dengan** `targetId` pada baris feed `ScanStreamFeed::since` untuk attendance sesi itu. Ini test yang, kalau ada lebih dulu, akan menangkap bug ini sebelum dikirim.
2. **Test kesamaan serialisasi tanggal** — string judul sesi di respons POST dan di baris feed **identik**.
3. **Test filter** — memilih target X mengembalikan tepat scan yang `targetId`-nya X, dan tidak bergantung pada judul.

**Kriteria selesai:**
- Tidak ada regex pemotong timestamp di frontend.
- Tidak ada fungsi yang mencocokkan entri scan dengan target berdasarkan teks.
- Mengubah format tampilan judul sesi **tidak** mematahkan filter — dibuktikan test, bukan diklaim.

### 7.7 Risiko

| Risiko | Penilaian |
|---|---|
| Sisi event perlu `event_id` | Rendah — `EventAttendance` punya `event_id`, dan `form.event` sudah di-eager-load (`ScanStreamFeed.php:41`) |
| Satu aplikasi hadir di beberapa sesi | Rendah — id sesi ada di baris attendance, jadi identitasnya per-kehadiran, bukan per-aplikasi |
| Pemakai feed lain | Rendah — hanya halaman Scan global yang mengonsumsi feed |
| `ScanEntry`/`ScanResult` dipakai di luar Scan | Perlu dicek saat eksekusi; keduanya didefinisikan di `lib/qrScanUi.ts` dan pemakainya terbatas di klaster Scan |

---

## 8. Siap-Pakai — ADR & Glosarium

Sesuai D8, isinya disiapkan di sini. **Belum dibuat sebagai file**, karena D1 membatasi tahap ini pada laporan. Saat pertama kali boleh mengubah file, tiga hal di bawah ini tinggal dipindahkan ke `docs/adr/` dan `CONTEXT.md`, dan `docs/superpowers/` dikeluarkan dari `.gitignore`.

### 8.1 Glosarium untuk `CONTEXT.md`

| Istilah | Definisi | Dipakai di |
|---|---|---|
| **target** | Acara atau sesi interview yang menjadi tujuan sebuah scan | Kandidat 1, 6 |
| **target identity** | `targetId` + `targetKind` — identitas buram sebuah target, bukan judulnya | Kandidat 1 |
| **target kind** | `event` atau `oprec` — dua domain berbeda yang berbagi satu meja scan | Kandidat 1 |
| **scan entry** | Satu baris riwayat scan yang tampil di meja scan, hasil kamera maupun feed | Kandidat 1, 2 |
| **scan desk** | Satu meja operator; diidentifikasi `scan-desk-id` di `sessionStorage`, dikirim sebagai `desk` | Kandidat 2 |
| **accepted risk (scan feed)** | Keputusan sadar bahwa feed mengekspos kedua domain kepada pemegang salah satu izin | Kandidat 7 |
| **builder field type** | 17 tipe kaya yang dilihat admin di builder (`short_text`, `image_upload`, `banner`, …) | Kandidat 3 |
| **API field type** | 8 tipe yang divalidasi backend (`input`, `select`, `textarea`, `datePicker`, `fileUpload`, `radio`, `checkbox`, `banner`) | Kandidat 3 |
| **builderType** | Kunci tipe kaya yang disimpan di `metadata` JSON untuk memulihkan builder field type dari API field type | Kandidat 3 |
| **bundle registration** | Satu pendaftaran yang memuat beberapa entri peserta sekaligus | Kandidat 6 |
| **team form** | Form yang mengundang anggota lewat `team_member_emails` | Kandidat 5, 6 |
| **applicant stage** | Tahap seleksi OpRec: `submitted`, `screening`, `interview`, `final` | Kandidat 6 |
| **queue entry** | Antrean peserta pada satu sesi interview, punya `queue_number` | Kandidat 1, 2 |
| **reject reason** | Kosakata alasan penolakan applicant (7 nilai) — saat ini hidup di module presentasi | Kandidat 6 |

### 8.2 ADR-0001 — Identitas target scan adalah identitas, bukan judul

**Status:** diterima (D3)
**Konteks:** meja scan global mencocokkan sebuah scan dengan targetnya. Implementasi awal menyusun judul tampilan `"Oprec · Divisi · tanggal"` di dua tempat di backend dan merekonstruksinya di frontend, lalu memotong timestamp dengan regex karena `session_date` diserialisasi berbeda di endpoint yang berbeda.
**Keputusan:** feed dan respons scan menerbitkan `targetId` + `targetKind`. Pencocokan memakai kesetaraan identitas. Judul tetap dikirim dan hanya dipakai untuk tampilan. Penyusunan judul menjadi satu produsen.
**Konsekuensi:** empat fungsi parsing di frontend dihapus; tiga test baru menjadi penjaga kontrak; mengubah format judul tidak lagi berisiko mematahkan filter.

### 8.3 ADR-0002 — Satu meja scan melayani kedua domain tanpa filter izin

**Status:** diterima (sudah berlaku sejak 2026-09-15, kini tercatat formal)
**Konteks:** feed gabungan attendance event + recruitment. Keputusan produk: satu meja dapat melayani semua jenis QR.
**Keputusan:** feed mengembalikan kedua domain kepada pemegang `events.list` **atau** `recruitment.attendance.scan`, tanpa memfilter per domain.
**Konsekuensi:** pemegang salah satu izin dapat melihat ringkasan kehadiran domain lain. Ini risiko yang **diterima secara sadar**, bukan cacat. Jangan "diperbaiki" tanpa menyetujui ulang.
**Rujukan:** sebelumnya hanya tercatat di doc-block `ScanStreamFeed.php:13-23` dan di spec yang di-gitignore.

### 8.4 ADR-0003 — `builderType` di metadata adalah kunci tipe field

**Status:** diterima (D5)
**Konteks:** builder punya 17 tipe kaya, API/DB punya 8 tipe. Pemetaan balik tidak dapat diandalkan tanpa penanda: `guessType()` (`fieldMapping.ts:202-216`) yang menyamakan `image_upload` dengan `file_upload`, dan `time`/`rating`/`heading`/`divider` dengan `short_text`.
**Keputusan:** `metadata.builderType` adalah kunci resmi tipe kaya. Ia tetap di kolom JSON, tanpa kolom DB baru.
**Konsekuensi:** baris tanpa `builderType` terdegradasi diam-diam — ini **utang yang diterima**, dan setiap penghematan biaya migrasi dibayar dengan risiko tersebut. `banner` masuk ke union TypeScript, palette, dan registry. Registry tipe field adalah satu module; percabangan render tidak boleh tersebar lagi.

---

## 9. Backlog Keputusan

| Kandidat | Status | Prasyarat | Catatan |
|---|---|---|---|
| 7 — ADR + glosarium | Diterima, isi siap di Bagian 8 | — | Belum dibuat sebagai file (D1) |
| 1 — Identitas target | Diterima, spesifikasi di Bagian 7 | — | Prioritas tertinggi |
| 2 — Pemecahan `useGlobalQrScanPage` | Diterima (3 module + bug cursor) | Selesainya Kandidat 1 | Menunggu Kandidat 1 selesai agar tidak membongkar dua kali |
| 4 — Sistem route | Diterima (migrasi ke `@/routes/**`) | — | Butuh keputusan apakah `lib/routes.ts` dipangkas bertahap atau sekaligus |
| 3 — Registry tipe field | Diterima (`builderType` + `banner`) | — | Butuh daftar lengkap 17 tipe kaya yang tersedia sebagai rujukan |
| 6 — Module daftar-berfilter | **Belum digrilling** | D2 sudah terjawab | Ronde 2: pagination kanonik (`ui/pagination` vs `FormSubmissionsPagination.vue`) |
| 5 — Kepemilikan kosakata pesan | Diterima (backend pemilik, bertahap) | — | Butuh urutan migrasi pesan mana lebih dulu |

