/** Ambang ringkas total: 1000 jadi 1k, 4.6 juta jadi 4.6m. */
const COMPACT_STEPS: readonly { limit: number; suffix: string }[] = [
    { limit: 1_000_000_000, suffix: 'b' },
    { limit: 1_000_000, suffix: 'm' },
    { limit: 1_000, suffix: 'k' },
]

/** Jumlah tombol nomor per blok; deret bergeser per 10 halaman tanpa elipsis. */
const PAGE_WINDOW = 10

/**
 * Ringkas angka besar untuk teks rentang: 4_600 jadi 4.6k, 1_200_000 jadi 1.2m.
 * Dipakai pagination agar total jutaan baris tetap satu baris di layar kecil.
 */
export function formatCompactNumber(value: number): string {
    const step = COMPACT_STEPS.find((candidate: { limit: number }): boolean => value >= candidate.limit)
    if (step === undefined) return String(value)

    const scaled = Math.round((value / step.limit) * 10) / 10
    return Number.isInteger(scaled) ? `${scaled}${step.suffix}` : `${scaled.toFixed(1)}${step.suffix}`
}

/**
 * Deret nomor halaman yang tampil: blok 10 halaman yang memuat halaman aktif.
 * Contoh: halaman 12 jadi 11..20, halaman 125000 jadi 124991..125000.
 */
export function buildPageItems(currentPage: number, lastPage: number): number[] {
    if (lastPage <= 0) return []

    const current = Math.min(Math.max(currentPage, 1), lastPage)
    const start = Math.floor((current - 1) / PAGE_WINDOW) * PAGE_WINDOW + 1
    const end = Math.min(start + PAGE_WINDOW - 1, lastPage)

    return pageRange(start, end)
}

/** Deret angka inklusif dari `start` sampai `end`. */
function pageRange(start: number, end: number): number[] {
    return Array.from({ length: end - start + 1 }, (_unused: unknown, index: number): number => start + index)
}

const PREVIOUS_LABEL_PATTERN = /sebelumnya|previous/i
const NEXT_LABEL_PATTERN = /berikutnya|next/i

/** Ambil URL prev/next dari links paginator Laravel; label ID maupun default Inggris dikenali. */
export function findCursorUrls(
    links: readonly { url: string | null; label: string }[],
): { previous: string | null; next: string | null } {
    return {
        previous: links.find((link: { label: string }): boolean => PREVIOUS_LABEL_PATTERN.test(link.label))?.url ?? null,
        next: links.find((link: { label: string }): boolean => NEXT_LABEL_PATTERN.test(link.label))?.url ?? null,
    }
}
