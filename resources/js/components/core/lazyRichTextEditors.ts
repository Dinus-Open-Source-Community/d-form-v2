import { defineAsyncComponent, type Component } from 'vue'

/**
 * Editor Tiptap dimuat lazy agar vendor Tiptap/ProseMirror (±130 kB gzip) tidak
 * ikut chunk halaman yang belum menampilkan editor.
 */
export const LazyTipTapEditor: Component = defineAsyncComponent(
    () => import('@/components/modules/dashboard/events/TipTapEditor.vue'),
)

/** Editor broadcast (Tiptap + upload gambar) dimuat lazy dengan alasan yang sama. */
export const LazyBroadcastContentEditor: Component = defineAsyncComponent(
    () => import('@/components/modules/dashboard/broadcasts/BroadcastContentEditor.vue'),
)
