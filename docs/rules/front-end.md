---
author: 'DOSCOM'
project: 'DForm'
version: 2
---

# Aturan dalam Front-End

Dokumen ini berfungsi sebagai standarisasi pengembangan Front-End menggunakan **Vue 3 (Composition API)**, **Inertia.js**, dan **Tailwind CSS**. Setiap pengembang wajib mengikuti aturan ini demi konsistensi kode.

## 1. Basic Convention

1.  **camelCase** untuk variabel, function, class, dan nama file `.js` / `.ts`.
2.  **PascalCase** untuk semua nama file `.vue` (Komponen, Page, Layout).
3.  **kebab-case** untuk penamaan CSS class kustom (jika tidak bisa menggunakan Tailwind).
4.  Dilarang menggunakan **Options API**. Semua komponen wajib menggunakan **`<script setup>`**.

## 2. Struktur Folder & Arsitektur

| Path                               | Fungsi                                                                                                               |
| :--------------------------------- | :------------------------------------------------------------------------------------------------------------------- |
| **resources/css/app.css**          | Entrypoint utama CSS. Tempat registrasi Tailwind directives dan font global.                                         |
| **resources/css/\*.css**           | Module CSS kustom (misal: `typography.css`, `animations.css`) untuk pemisahan fokus.                                 |
| **resources/js/app.js**            | Entrypoint utama JavaScript & Inisialisasi Inertia.js.                                                               |
| **resources/js/bootstrap.js**      | Konfigurasi library pihak ketiga (Axios, Echo, dll).                                                                 |
| **resources/js/global.d.ts**       | Definisi Type/Interface TypeScript yang digunakan secara global di seluruh aplikasi.                                 |
| **resources/js/components/core**   | Komponen kustom yang bersifat _reusable_ secara global (Button kustom, Input kustom).                                |
| **resources/js/components/layout** | Komponen pendukung layout besar (Navbar, Sidebar, Footer, UserMenu).                                                 |
| **resources/js/components/module** | Komponen spesifik yang hanya digunakan oleh satu fitur/modul tertentu.                                               |
| **resources/js/components/ui**     | Komponen dari **Shadcn-Vue**. **Dilarang diubah secara langsung** (Gunakan Wrapper di `core` jika butuh modifikasi). |
| **resources/js/layouts**           | Template layout aplikasi (AuthenticatedLayout, GuestLayout, AdminLayout).                                            |
| **resources/js/pages**             | Komponen halaman utama yang dirender oleh `Inertia::render()`.                                                       |
| **resources/js/utils**             | Tempat menyimpan _helpers_, _composables_, dan logika bisnis murni (pure functions).                                 |
| **resources/views/app.blade.php**  | Root template HTML. Tempat menyuntikkan `@inertia` dan `@vite`.                                                      |

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
| **module/** | **Feature Specific:** Komponen yang hanya digunakan oleh modul/fitur tertentu agar tidak mengotori namespace global (misal: `EventCard.vue` di modul Event). |
| **ui/**     | **Shadcn-Vue Base:** Komponen asli dari library Shadcn. **Dilarang keras mengubah file di sini.** Jika butuh kustomisasi, buat wrapper di folder `core`.     |

### Aturan Teknis Penulisan Komponen:

1.  **Reaktivitas:** Wajib menggunakan **Composition API** (`<script setup>`). Gunakan `ref()` untuk data primitif (string, number, boolean) dan `reactive()` untuk objek/array yang kompleks.
2.  **Props:** Definisikan props secara eksplisit menggunakan generic TypeScript `defineProps<{ ... }>()` untuk _type-safety_ yang maksimal di IDE.
3.  **Emits:** Dokumentasikan setiap event yang dikirimkan ke parent component menggunakan `defineEmits(['change', 'submit'])` agar alur data antar komponen jelas.
4.  **Styling:** Prioritaskan **Tailwind Utility Classes**. Penggunaan `<style scoped>` hanya diperbolehkan jika harus menimpa (_override_) library pihak ketiga yang
5.  **Type safety:** Selalu gunakan `lang="ts"` di `<script setup lang="ts">` agar memudahkan type safety, dan terhindarkan dari tipe data _error_ ketika berjalan (_runtime_)

## 5. Layouts & Pages

1.  **Persistent Layout:** Gunakan pola `layout: AppLayout` di dalam Page agar state layout (seperti scroll sidebar) tidak ter-reset saat navigasi.
2.  **Data Fetching:** Data harus mengalir dari Laravel Controller via **Inertia Props**. Hindari melakukan `axios.get` di dalam `onMounted` kecuali untuk data yang sangat dinamis (non-SEO).
3.  **SEO:** Gunakan komponen `<Head>` dari `@inertiajs/vue3` untuk setiap Page guna mengatur `<title>` dan `<meta>` tags.

## 6. Utils & Composables

1.  **Logic Separation:** Pisahkan logika yang rumit ke dalam **Composables** (`resources/js/utils/composables`). Gunakan prefix `use`. Contoh: `useEventValidation.ts`.
2.  **Formatting:** Gunakan helper untuk hal-hal repetitif seperti `formatDate()` atau `formatCurrency()`.

## 7. TypeScript Standard

1.  **No Any:** Penggunaan `any` sangat dilarang. Gunakan `unknown` atau buatkan `interface` khusus jika tipe data belum pasti.
2.  **Naming:** Nama Interface/Type harus menggunakan PascalCase. Contoh: `interface UserData { ... }`.
3.  **Prop Safety:** Manfaatkan `PropType` jika mendefinisikan props secara manual di luar TypeScript generic.
