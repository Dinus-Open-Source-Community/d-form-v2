/**
 * Lebar minimum baris builder (px) sebelum kolom pratinjau live ditampilkan.
 * Aritmetika: palet 260 + kanvas min 320 + kolom pratinjau 360 + border 1 = 941; dibulatkan ke 1000.
 */
export const FORM_BUILDER_LIVE_PREVIEW_MIN_WIDTH = 1000

/** True bila ruang baris builder cukup untuk palet, kanvas, dan kolom pratinjau sekaligus. */
export function shouldShowLivePreview(containerWidth: number): boolean {
    return containerWidth >= FORM_BUILDER_LIVE_PREVIEW_MIN_WIDTH
}
