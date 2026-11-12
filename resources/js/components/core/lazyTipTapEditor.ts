import { defineAsyncComponent, type Component } from 'vue';

/**
 * Editor Tiptap dimuat lazy agar vendor Tiptap/ProseMirror (±130 kB gzip) tidak
 * ikut chunk halaman yang belum menampilkan editor. Dipakai builder (pesan
 * setelah submit) dan form event (deskripsi), jadi factory tinggal di `core/`
 * bersama `TipTapEditor.vue` yang dibungkusnya.
 */
export const LazyTipTapEditor: Component = defineAsyncComponent(() => import('@/components/core/TipTapEditor.vue'));
