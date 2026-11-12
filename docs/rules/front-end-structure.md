---
author: 'DOSCOM'
project: 'DForm'
version: 1
---

# Struktur Folder Frontend

Dokumen ini menetapkan struktur target frontend Vue 3 + Inertia + TypeScript
agar ownership, dependency direction, testability, dan code splitting tetap
jelas ketika jumlah modul bertambah.

## 1. Struktur target

```text
resources/
├── css/
│   ├── app.css
│   └── modules/
│
└── js/
    ├── app.ts
    ├── bootstrap.ts
    │
    ├── pages/
    │   ├── Auth/
    │   ├── Dashboard/
    │   ├── Events/
    │   └── Recruitment/
    │
    ├── layouts/
    │   ├── DashboardLayout.vue
    │   ├── FormFillLayout.vue
    │   ├── AuthLayout.vue
    │   └── LandingLayout.vue
    │
    ├── components/
    │   ├── ui/
    │   ├── core/
    │   ├── layout/
    │   └── modules/
    │       ├── event/
    │       ├── form/
    │       ├── builder/
    │       ├── scan/
    │       └── recruitment/
    │
    ├── hooks/
    │   ├── event/
    │   ├── form/
    │   ├── builder/
    │   ├── scan/
    │   ├── recruitment/
    │   ├── shared/
    │   └── __tests__/
    │
    ├── lib/
    │   ├── event/
    │   ├── form/
    │   ├── recruitment/
    │   ├── shared/
    │   └── __tests__/
    │
    ├── types/
    │   ├── event/
    │   ├── form/
    │   ├── recruitment/
    │   └── shared/
    │
    ├── constants/
    │   ├── eventRegistration.ts
    │   └── formBuilder.ts
    │
    ├── adapters/
    │   ├── http/
    │   ├── browser/
    │   ├── scanner/
    │   └── third-party/
    │
    ├── plugins/       # conditional: plugin Vue global yang benar-benar dipakai
    ├── directives/    # conditional: custom directive Vue yang benar-benar dipakai
    │
    ├── routes/
    ├── actions/
    ├── wayfinder/
    └── utils/legacy/
```

`app.ts` dan `bootstrap.ts` adalah target entrypoint TypeScript. File entry
legacy seperti `app.js` dan `bootstrap.js` tidak dipindahkan massal; migrasi
dilakukan per tiket dengan validasi build.

## 2. Fungsi setiap folder

| Folder | Tanggung jawab | Larangan |
| --- | --- | --- |
| `pages/` | Komposisi route: menerima props Inertia, memilih layout, memanggil hook, dan meneruskan props/event. | Business logic, query, transformasi berat, reusable component. |
| `layouts/` | Shell halaman, navigation, sidebar, toast, dan persistent UI state. | Logic domain, fetch data milik fitur, mutation bisnis. |
| `components/ui/` | Komponen generated Shadcn-Vue/Reka UI. | Edit langsung atau menjadikannya tempat custom feature component. |
| `components/core/` | Presentational component reusable lintas domain. | Fetch data, business rule, transformasi berat, state domain. |
| `components/layout/` | Komponen pendukung yang hanya dipakai oleh layout. | Komponen feature yang dipakai di luar layout. |
| `components/modules/` | Presentational component khusus satu domain atau feature. | Komponen global lintas feature dan logic yang seharusnya berada di hook/lib. |
| `hooks/` | Stateful composable, watcher, lifecycle, Inertia form, axios action, dan side effect frontend. | Pure utility tanpa state, page import, dan god hook lintas banyak domain. |
| `lib/` | Pure function deterministic untuk formatting, mapping, validation murni, dan serialization. | `ref`, `watch`, lifecycle, DOM, storage, network, atau side effect. |
| `types/` | Interface, type alias, declaration, dan type-only enum/union. | Runtime object, runtime enum, factory, `ref`, dan side effect. |
| `constants/` | Runtime constant immutable lintas domain tanpa behavior. | Mutable global state, request, query, hook, dan business workflow. |
| `adapters/` | Seam untuk third-party library, browser API, dan external technical side effect. | Business rule, page, layout, dan wrapper pass-through tanpa abstraction. |
| `plugins/` | Registrasi plugin Vue global yang benar-benar digunakan aplikasi. | Feature logic, fetch data, atau eager loading vendor berat yang hanya dipakai satu page. |
| `directives/` | Custom Vue directive untuk perilaku element-level yang reusable. | Request, business rule, atau pengganti accessibility native. |
| `routes/`, `actions/`, `wayfinder/` | Artefak generated framework. | Edit manual. |
| `utils/legacy/` | Kode legacy yang belum dimigrasikan. | File baru dan utility baru. |

## 3. Dependency direction

Dependency harus mengalir satu arah:

```text
pages
 ├── layouts
 ├── components/modules
 └── hooks

components/modules
 ├── components/core
 ├── components/ui
 ├── hooks
 ├── lib
 └── types

hooks
 ├── lib
 ├── types
 ├── constants
 ├── adapters
 └── routes/actions

adapters
 ├── types
 └── constants

plugins/directives
 └── adapters atau constants yang relevan

lib
 ├── types
 └── constants

types
 └── tidak bergantung pada folder runtime
```

Aturan dependency:

- `types/` tidak boleh mengimpor component, hook, page, atau module runtime.
- `lib/` tidak boleh mengimpor `hooks/`, component, page, DOM, storage, atau
  network.
- `hooks/` tidak boleh mengimpor `pages/` atau layout.
- `pages/` dan component tidak boleh mengakses third-party/browser API secara
  langsung jika sudah ada adapter yang sesuai.
- `adapters/` boleh mengimpor third-party atau browser API, tetapi tidak boleh
  mengimpor page, layout, component, atau business rule domain.
- `plugins/` hanya untuk registrasi global dan tidak boleh menjadi tempat baru
  untuk business logic atau fetch fitur.
- `directives/` hanya untuk perilaku element-level; tidak boleh melakukan
  request, redirect, toast, atau authorization.
- `components/core/` tidak boleh mengimpor business rule feature.
- `pages/` tidak boleh diimpor oleh folder lain.
- Module feature tidak boleh membuat circular dependency dengan module lain.
- Gunakan alias `@/`; jangan membuat jalur relatif panjang untuk melompati
  ownership folder.

## 4. Aturan module feature

Komponen khusus domain ditempatkan di `components/modules/<domain>/`.
Stateful logic tetap ditempatkan di `hooks/<domain>/`, pure function di
`lib/<domain>/`, dan kontrak tipe di `types/<domain>/`.

Contoh feature event:

```text
components/modules/event/
├── EventCard.vue
├── EventRegistrationStatus.vue
└── EventParticipantTable.vue

hooks/event/
└── useEventRegistrationFlow.ts

lib/event/
└── formatEventRegistrationStatus.ts

types/event/
├── IEventSummary.ts
└── TEventRegistrationStatus.ts
```

Feature tidak boleh membuat salinan helper atau tipe yang sudah dimiliki
domain lain. Jika kontrak benar-benar lintas domain, tempatkan di `types/shared`
atau `lib/shared` dengan nama yang tetap spesifik.

## 5. Penamaan file dan symbol

### Vue component, layout, dan page

Gunakan PascalCase:

```text
EventCard.vue
EventRegistrationStatus.vue
FormBuilderCanvas.vue
RecruitmentEvaluationPanel.vue
Events/Index.vue
Events/Forms/Fill.vue
```

Nama harus menjelaskan domain dan tujuan. Hindari `Card2.vue`, `Page.vue`,
`Main.vue`, `Data.vue`, `CommonCard.vue`, atau nama generik lain.

### Hook

Gunakan `use` + domain + tujuan:

```text
useEventRegistrationFlow.ts
useFormBuilderWorkspace.ts
useAutosaveSync.ts
useGlobalQrScanPage.ts
```

Hook harus memiliki satu ownership dan satu alasan berubah. Jangan membuat
`useData`, `useFile`, atau `useManager` yang memuat beberapa workflow.

### Pure function

Gunakan nama berdasarkan behavior:

```text
formatRupiahPrice.ts
sanitizeFormBuilderSnapshot.ts
isValidRegistrationCode.ts
buildScanRequestPayload.ts
```

### Type

Kontrak lintas file memakai prefix proyek:

```ts
interface IEventSummary {}
type TEventRegistrationStatus = 'open' | 'closed'
type TFormFieldType = 'text' | 'email'
type GPaginatedResponse<GItem> = {
    items: GItem[]
    nextCursor: string | null
}
```

### Test

- Unit test hook/lib boleh diletakkan di `__tests__/` yang berdekatan dengan
  ownership-nya.
- Nama test mengikuti module yang diuji, misalnya
  `useEventRegistrationFlow.test.ts`.
- E2E berada di folder test E2E proyek, bukan di `pages/` atau
  `components/`.

## 6. Barrel dan entrypoint

- Jangan membuat `index.ts` pada setiap folder.
- Barrel hanya boleh dibuat pada seam publik yang stabil, misalnya
  `hooks/index.ts`.
- Jangan mengekspor seluruh isi module secara otomatis jika hal tersebut
  memperbesar bundle atau menyembunyikan ownership import.
- Generated route/action/Wayfinder tetap menjadi sumber route yang ditentukan
  generator; jangan membuat sumber route ketiga.

## 7. Adapter, plugin, dan directive

### `adapters/`

Gunakan adapter untuk mengisolasi detail teknis dari library eksternal atau
browser API:

```text
adapters/
├── http/axiosErrorAdapter.ts
├── browser/sessionStorageAdapter.ts
├── scanner/html5QrCodeAdapter.ts
└── third-party/tiptapEditorAdapter.ts
```

Adapter wajib:

- memiliki interface kecil dan deep implementation;
- mengubah hasil third-party menjadi tipe internal;
- menjadi seam yang dapat di-mock jika memang ada variasi atau side effect;
- menyimpan detail library eksternal di satu tempat.

Adapter dilarang:

- hanya meneruskan satu method tanpa menyembunyikan kompleksitas;
- memuat rule domain;
- melakukan redirect, toast, atau mutation UI;
- menjadi folder `services/` baru dengan nama yang generik.

`unknown` hanya boleh muncul pada adapter third-party yang benar-benar tidak
diketahui dan harus dipersempit sebelum hasilnya keluar dari adapter.

### `plugins/`

`plugins/` bersifat conditional. Buat hanya ketika ada registrasi plugin Vue
global yang nyata. Jangan membuat folder ini untuk satu konfigurasi lokal yang
lebih tepat berada di `bootstrap.ts`.

### `directives/`

`directives/` bersifat conditional. Buat hanya untuk custom directive Vue yang
digunakan lebih dari satu module dan tidak dapat direpresentasikan sebagai
component atau hook.

## 8. Folder yang dilarang sebagai dumping ground

Jangan membuat folder global berikut:

```text
services/
repositories/
helpers/
common/
misc/
api/
mappers/
transformers/
managers/
controllers/
```

Jika sebuah module memiliki tanggung jawab khusus, tempatkan pada seam yang
sesuai: `hooks/`, `guards/`, `validation/`, `adapters/`, `lib/`, `types/`, atau
`components/modules/<domain>/`.

## 9. Aturan migrasi legacy

- File baru wajib mengikuti struktur target.
- `utils/legacy/` hanya boleh disentuh ketika tiket memang mencakup migrasi,
  bug fix, atau dependency yang terkait.
- Jangan memindahkan seluruh folder dalam satu perubahan tanpa plan,
  regression test, dan bukti bahwa seluruh import sudah diperbarui.
- Setelah sebuah domain selesai dimigrasikan, folder legacy yang kosong boleh
  dihapus melalui prosedur perubahan destruktif yang disetujui.
- Migrasi struktur tidak boleh mengubah behavior tanpa tiket terpisah.
