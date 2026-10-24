/** Item deret nomor: angka halaman atau celah elipsis. */
export type TPaginationItem = number | 'ellipsis'

/** Ambang ringkas total: 1000 jadi 1k, 4.6 juta jadi 4.6m. */
const COMPACT_STEPS: readonly { limit: number; suffix: string }[] = [
    { limit: 1_000_000_000, suffix: 'b' },
    { limit: 1_000_000, suffix: 'm' },
    { limit: 1_000, suffix: 'k' },
]

/** Jumlah nomor per blok; blok bergeser mengikuti halaman aktif. */
const PAGE_BLOCK = 10

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
 * Deret nomor satu blok (default 10) yang memuat halaman aktif, tanpa nomor
 * halaman terakhir. Elipsis hanya penanda blok lain, bukan tombol lompat.
 * Contoh: halaman 14 dari 125000 jadi `… 11 12 … 20 …`.
 */
export function pageWindow(
    currentPage: number,
    lastPage: number,
    block: number = PAGE_BLOCK,
): TPaginationItem[] {
    if (lastPage <= 0) return []
    if (lastPage <= block) return pageRange(1, lastPage)

    const current = Math.min(Math.max(currentPage, 1), lastPage)
    const start = Math.floor((current - 1) / block) * block + 1
    const end = Math.min(start + block - 1, lastPage)

    const items: TPaginationItem[] = []
    if (start > 1) items.push('ellipsis')
    items.push(...pageRange(start, end))
    if (end < lastPage) items.push('ellipsis')

    return items
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
