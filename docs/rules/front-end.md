---
author: 'DOSCOM'
project: 'DForm'
version: 2
---

# Aturan dalam Front-End

Dokumen ini berfungsi sebagai standarisasi pengembangan Front-End menggunakan **Vue 3 (Composition API)**, **Inertia.js**, dan **Tailwind CSS**. Setiap pengembang wajib mengikuti aturan ini demi konsistensi kode.

## 1. Basic Convention

1.  **camelCase** untuk variabel, function, dan nama file `.js`/`.ts`; **PascalCase** untuk class dan nama file `.vue`.
2.  **PascalCase** untuk semua nama file `.vue` (Komponen, Page, Layout).
3.  **kebab-case** untuk penamaan CSS class kustom (jika tidak bisa menggunakan Tailwind).
4.  Dilarang menggunakan **Options API**. Semua komponen wajib menggunakan **`<script setup>`**.

## 2. Struktur Folder & Arsitektur

| Path                               | Fungsi                                                                                                               |
| :--------------------------------- | :------------------------------------------------------------------------------------------------------------------- |
| **resources/css/app.css**          | Entrypoint utama CSS. Tempat registrasi Tailwind directives dan font global.                                         |
| **resources/css/\*.css**           | Module CSS kustom (misal: `typography.css`, `animations.css`) untuk pemisahan fokus.                                 |
| **resources/js/app.ts**            | Target entrypoint utama TypeScript dan inisialisasi Inertia.js.                                                      |
| **resources/js/bootstrap.ts**      | Target konfigurasi library pihak ketiga (Axios, Echo, dll).                                                         |
| **resources/js/types/**            | Kontrak lintas file: interface, type alias, declaration, dan type-only enum/union bila diperlukan.                  |
| **resources/js/components/core**   | Komponen kustom yang bersifat _reusable_ secara global (Button kustom, Input kustom).                                |
| **resources/js/components/layout** | Komponen pendukung layout besar (Navbar, Sidebar, Footer, UserMenu).                                                 |
| **resources/js/components/modules** | Komponen spesifik yang hanya digunakan oleh satu fitur/modul tertentu.                                             |
| **resources/js/components/ui**     | Komponen dari **Shadcn-Vue**. **Dilarang diubah secara langsung** (Gunakan Wrapper di `core` jika butuh modifikasi). |
| **resources/js/layouts**           | Template layout aplikasi (AuthenticatedLayout, GuestLayout, AdminLayout).                                            |
| **resources/js/pages**             | Komponen halaman utama yang dirender oleh `Inertia::render()`.                                                       |
| **resources/js/hooks**             | Stateful composable dengan named export `useXxx`.                                                                   |
| **resources/js/lib**               | Pure function deterministik tanpa reactive state, DOM, storage, atau side effect.                                    |
| **resources/js/utils**             | Legacy-only; utility baru tidak boleh dibuat di sini.                                                               |
| **resources/js/constants**         | Runtime constant immutable tanpa behavior.                                                                         |
| **resources/js/adapters**          | Seam untuk third-party library, browser API, dan technical side effect.                                           |
| **resources/js/plugins**            | Registrasi plugin Vue global yang benar-benar digunakan aplikasi.                                                  |
| **resources/js/directives**         | Custom directive Vue untuk perilaku element-level yang reusable.                                                   |
| **resources/views/app.blade.php**  | Root template HTML. Tempat menyuntikkan `@inertia` dan `@vite`.                                                      |

Struktur target lengkap, dependency direction, penamaan, ownership, dan
larangan per folder berada di
[`front-end-structure.md`](./front-end-structure.md).

`app.js` dan `bootstrap.js` yang masih ada adalah legacy entrypoint. Jangan
membuat dua entrypoint aktif bersamaan; migrasi ke TypeScript dilakukan per
tiket dan harus diverifikasi dengan build.

## 3. Cara Penamaan File

| Jenis             | Format     | Contoh                                    |
| :---------------- | :--------- | :---------------------------------------- |
| **Style Sheet**   | kebab-case | `dark-theme.css`                          |
| **JS / TS File**  | camelCase  | `useCurrencyFormatter.ts`                 |
| **Vue Component** | PascalCase | `PrimaryButton.vue`, `UserDetailCard.vue` |
| **Folder Page**   | PascalCase | `resources/js/pages/Event/Index.vue`      |

## 4. Components

Seluruh komponen Vue disimpan di dalam `resources/js/components` dan dibagi menjadi empat kategori utama berdasarkan cakupan fungsinya (_scope_):

| Folder      | Deskripsi & Aturan                                                                                                                                           |
| :---------- | :----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **core/**   | **Global Standalone:** Komponen kustom dasar yang bersifat umum dan bisa digunakan di mana saja (misal: `SubmitButton.vue`, `StatusBadge.vue`).              |
| **layout/** | **Structural:** Komponen pendukung yang **hanya** digunakan di dalam folder `resources/js/layouts` (misal: `Navbar.vue`, `AuthSidebar.vue`).                 |
| **modules/** | **Feature Specific:** Komponen yang hanya digunakan oleh modul/fitur tertentu agar tidak mengotori namespace global (misal: `EventCard.vue` di modul Event). |
| **ui/**     | **Shadcn-Vue Base:** Komponen asli dari library Shadcn. **Dilarang keras mengubah file di sini.** Jika butuh kustomisasi, buat wrapper di folder `core`.     |

### Aturan Teknis Penulisan Komponen:

1.  **Reaktivitas:** Wajib menggunakan **Composition API** (`<script setup>`). Pilih `ref()` atau `reactive()` berdasarkan kebutuhan reaktivitas dan ergonomi API; jangan memaksakan satu primitive untuk semua bentuk state.
2.  **Props:** Definisikan props secara eksplisit menggunakan generic TypeScript `defineProps<{ ... }>()` untuk _type-safety_ yang maksimal di IDE.
3.  **Emits:** Event yang memiliki payload wajib memakai typed emits, misalnya `defineEmits<{ change: [value: string]; submit: [] }>()`.
4.  **Styling:** Prioritaskan **Tailwind Utility Classes**. Gunakan `<style scoped>` untuk kebutuhan lokal yang tidak layak diekspresikan dengan utility class atau untuk override library pihak ketiga.
5.  **Type safety:** Semua `.vue` wajib memakai `<script setup lang="ts">`.

## 5. Layouts & Pages

1.  **Persistent Layout:** Gunakan persistent layout yang sesuai domain halaman agar state layout tidak ter-reset saat navigasi. Nama layout tidak diwajibkan `AppLayout`.
2.  **Data Fetching:** Data harus mengalir dari Laravel Controller via **Inertia Props**. Hindari melakukan `axios.get` di dalam `onMounted` kecuali untuk data yang sangat dinamis (non-SEO).
3.  **SEO:** Gunakan komponen `<Head>` dari `@inertiajs/vue3` untuk setiap Page guna mengatur `<title>` dan `<meta>` tags.

## 6. Utils & Composables

1.  **Logic Separation:** Pisahkan stateful logic ke **hooks** dengan prefix `use`. Pure function deterministic berada di **lib**.
2.  **Formatting:** Gunakan pure function di `lib` untuk hal-hal repetitif seperti `formatDate()` atau `formatCurrency()`.
3.  **Legacy:** `utils` hanya dipakai untuk kode legacy sampai ada scope migrasi yang jelas.

## 7. TypeScript Standard

1.  **No Any:** `any` dilarang.
2.  **Unknown:** `unknown` hanya boleh pada boundary third-party yang benar-benar tidak diketahui dan wajib segera dipersempit dengan type guard atau runtime validation. Response internal yang kontraknya diketahui wajib memakai tipe eksplisit.
3.  **Non-null assertion:** Dilarang menggunakan `value!`. Nullable value harus ditangani dengan guard, fallback, atau early return.
4.  **Type assertion:** `as` dilarang jika hanya menutupi error tipe. Diperbolehkan setelah runtime validation, pada generated/library boundary, atau sebagai `as const` untuk literal inference.
5.  **Naming:** Interface/type lintas file memakai prefix sesuai aturan proyek dan berada di `types/`.
6.  **Prop Safety:** Manfaatkan `PropType` jika mendefinisikan props secara manual di luar TypeScript generic.

## 8. Struktur folder dan larangan

Aturan berikut wajib berlaku untuk kode baru:

1.  `pages/` hanya menjadi komposer route; dilarang menyimpan business logic,
    query, transformasi berat, atau reusable component.
2.  `layouts/` hanya menangani shell dan persistent UI state; dilarang memuat
    logic domain atau fetch fitur.
3.  `components/core/` hanya presentational dan reusable lintas domain.
4.  `components/modules/` hanya untuk component khusus satu domain.
5.  Stateful composable hanya berada di `hooks/`; pure function hanya berada
    di `lib/`; kontrak tipe hanya berada di `types/`.
6.  `types/` dilarang berisi runtime object, runtime enum, factory, atau side
    effect.
7.  Dilarang membuat folder global `helpers/`, `common/`, `misc/`, `services/`,
    atau `data/` sebagai dumping ground.
8.  `utils/` hanya legacy; utility baru wajib dibuat di `lib/` atau `hooks/`
    sesuai sifatnya.
9.  `adapters/` hanya menangani third-party/browser seam; tidak boleh berisi
    business rule atau wrapper pass-through tanpa abstraction.
10. `plugins/` dan `directives/` bersifat conditional; jangan membuatnya untuk
    satu konfigurasi lokal atau satu perilaku yang lebih tepat berada di
    `bootstrap`, component, atau hook.
11. `components/ui/`, `routes/`, `actions/`, dan `wayfinder/` tidak boleh
    diedit manual jika file tersebut generated atau berasal dari library.
12. Dilarang membuat barrel `index.ts` di setiap folder; barrel hanya untuk
    seam publik yang stabil.
13. Dilarang membuat folder global `services/`, `repositories/`, `helpers/`,
    `common/`, `misc/`, `api/`, `mappers/`, `transformers/`, `managers/`, atau
    `controllers/` sebagai dumping ground.

Detail struktur dan dependency direction wajib mengikuti
[`front-end-structure.md`](./front-end-structure.md).

## 9. Accessibility dan browser support

1.  Semua kontrol interaktif wajib dapat digunakan dengan keyboard dan memiliki accessible name.
2.  Fokus keyboard tidak boleh dihilangkan tanpa pengganti yang setara.
3.  Error form harus terhubung ke field terkait dan dapat dibaca assistive technology.
4.  Target browser dan perilaku responsive harus diverifikasi untuk fitur yang mengubah layout atau input.
