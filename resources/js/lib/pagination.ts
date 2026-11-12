import type { IPaginationPageChange } from '@/types/pagination';

/** Item deret nomor: angka halaman atau celah elipsis. */
export type TPaginationItem = number | 'ellipsis';

/** Ambang ringkas total: 1000 jadi 1k, 4.6 juta jadi 4.6m. */
const COMPACT_STEPS: readonly { limit: number; suffix: string }[] = [
    { limit: 1_000_000_000, suffix: 'b' },
    { limit: 1_000_000, suffix: 'm' },
    { limit: 1_000, suffix: 'k' },
];

/** Jumlah nomor per blok; blok bergeser mengikuti halaman aktif. */
const PAGE_BLOCK = 10;

/** Pagination standar MUI: satu nomor di tepi blok, satu sibling di tiap sisi aktif. */
const BOUNDARY_COUNT = 1;
const SIBLING_COUNT = 1;

/** Blok maksimal yang tampil utuh tanpa elipsis. */
const FULL_BLOCK_MAX = 7;

/**
 * Ringkas angka besar untuk teks rentang: 4_600 jadi 4.6k, 1_200_000 jadi 1.2m.
 * Nilai yang pembulatannya menyentuh batas langkah berikutnya dipromosikan
 * (999_999 → 1m, 999_999_999 → 1b) agar tidak pernah tampil "1000k".
 */
export function formatCompactNumber(value: number): string {
    const stepIndex = COMPACT_STEPS.findIndex((candidate: { limit: number }): boolean => value >= candidate.limit);
    if (stepIndex < 0) return String(value);

    const step = COMPACT_STEPS[stepIndex];
    if (step === undefined) return String(value);

    const scaled = roundToOneDecimal(value / step.limit);
    if (scaled >= 1_000 && stepIndex > 0) {
        const largerStep = COMPACT_STEPS[stepIndex - 1];
        if (largerStep !== undefined) {
            return formatScaled(roundToOneDecimal(value / largerStep.limit), largerStep.suffix);
        }
    }

    return formatScaled(scaled, step.suffix);
}

/** Bulatkan ke satu desimal tanpa membawa sisa floating point ke label. */
function roundToOneDecimal(value: number): number {
    return Math.round(value * 10) / 10;
}

function formatScaled(scaled: number, suffix: string): string {
    return Number.isInteger(scaled) ? `${scaled}${suffix}` : `${scaled.toFixed(1)}${suffix}`;
}

/**
 * Deret nomor satu blok (default 10) yang memuat halaman aktif. Batas deret
 * adalah ujung blok kelipatan 10, bukan halaman terakhir, lalu dipotong dengan
 * algoritma pagination standar MUI (boundary 1, sibling 1).
 * Contoh: halaman 14 dari 125000 jadi `1 … 13 14 15 … 20`.
 */
export function pageWindow(currentPage: number, lastPage: number, block: number = PAGE_BLOCK): TPaginationItem[] {
    if (lastPage <= 0) return [];

    const current = Math.min(Math.max(currentPage, 1), lastPage);
    const blockStart = Math.floor((current - 1) / block) * block + 1;
    const blockEnd = Math.min(blockStart + block - 1, lastPage);

    if (blockEnd <= FULL_BLOCK_MAX) return pageRange(1, blockEnd);

    return blockItems(current, blockEnd);
}

/** Potong satu blok nomor memakai aturan MUI: boundary, sibling, lalu elipsis pada celah. */
function blockItems(current: number, count: number): TPaginationItem[] {
    const startPages = pageRange(1, Math.min(BOUNDARY_COUNT, count));
    const endPages = pageRange(Math.max(count - BOUNDARY_COUNT + 1, BOUNDARY_COUNT + 1), count);

    const siblingsStart = Math.max(
        Math.min(current - SIBLING_COUNT, count - BOUNDARY_COUNT - SIBLING_COUNT * 2 - 1),
        BOUNDARY_COUNT + 2
    );
    const siblingsEnd = Math.min(
        Math.max(current + SIBLING_COUNT, BOUNDARY_COUNT + SIBLING_COUNT * 2 + 2),
        endPages.length > 0 ? endPages[0] - 2 : count - 1
    );

    const items: TPaginationItem[] = [...startPages];

    if (siblingsStart > BOUNDARY_COUNT + 2) {
        items.push('ellipsis');
    } else if (BOUNDARY_COUNT + 1 < count - BOUNDARY_COUNT) {
        items.push(BOUNDARY_COUNT + 1);
    }

    items.push(...pageRange(siblingsStart, siblingsEnd));

    if (siblingsEnd < count - BOUNDARY_COUNT - 1) {
        items.push('ellipsis');
    } else if (count - BOUNDARY_COUNT > BOUNDARY_COUNT) {
        items.push(count - BOUNDARY_COUNT);
    }

    items.push(...endPages);

    return items;
}

/** Deret angka inklusif dari `start` sampai `end`. */
function pageRange(start: number, end: number): number[] {
    return Array.from({ length: end - start + 1 }, (_unused: unknown, index: number): number => start + index);
}

/** Input URL tujuan halaman: path + query aktif yang sudah dibaca adapter browser. */
export interface IPaginationTargetUrlInput {
    pathname: string;
    /** Query string lengkap termasuk `?`; string kosong bila tidak ada. */
    search: string;
    page: number;
    perPage: number;
    pageParam: string;
    perPageParam: string;
    /** `false` saat selector jumlah baris tidak tampil, agar query tetap sama seperti sebelumnya. */
    includePerPage: boolean;
}

/**
 * URL tujuan pindah halaman: seluruh query aktif dipertahankan (filter, tab,
 * pencarian), `page` diganti, `per_page` disertakan bila selector tampil, dan
 * `cursor` dibuang karena mode nomor halaman tidak memakainya.
 */
export function buildPaginationTargetUrl(input: IPaginationTargetUrlInput): string {
    const params = new URLSearchParams(input.search);
    params.set(input.pageParam, String(input.page));
    if (input.includePerPage) params.set(input.perPageParam, String(input.perPage));
    params.delete('cursor');
    return `${input.pathname}?${params.toString()}`;
}

/** Normalisasi pilihan jumlah baris dari selector: bilangan bulat positif, selain itu `null`. */
export function parsePerPageValue(raw: string): number | null {
    const parsed = Number(raw);
    if (!Number.isInteger(parsed) || parsed <= 0) return null;
    return parsed;
}

/** Input keputusan emit `page-change` oleh komponen pagination presentational. */
export interface IResolvePageRequestInput {
    requestedPage: number;
    currentPage: number;
    lastPage: number | null;
    perPage: number;
    isCursorMode: boolean;
    /** `showPerPageSelector` dari komponen; diteruskan apa adanya ke payload emit. */
    includePerPage: boolean;
}

/**
 * Keputusan navigasi nomor halaman. `null` berarti komponen tidak boleh emit:
 * mode cursor, bukan bilangan bulat, halaman sama, atau di luar rentang.
 */
export function resolvePageRequest(input: IResolvePageRequestInput): IPaginationPageChange | null {
    if (input.isCursorMode) return null;
    if (!Number.isInteger(input.requestedPage)) return null;
    if (input.requestedPage === input.currentPage) return null;
    if (input.requestedPage < 1) return null;
    if (input.lastPage !== null && input.requestedPage > input.lastPage) return null;
    return { page: input.requestedPage, perPage: input.perPage, includePerPage: input.includePerPage };
}

const PREVIOUS_LABEL_PATTERN = /sebelumnya|previous/i;
const NEXT_LABEL_PATTERN = /berikutnya|next/i;

/** Ambil URL prev/next dari links paginator Laravel; label ID maupun default Inggris dikenali. */
export function findCursorUrls(links: readonly { url: string | null; label: string }[]): {
    previous: string | null;
    next: string | null;
} {
    return {
        previous:
            links.find((link: { label: string }): boolean => PREVIOUS_LABEL_PATTERN.test(link.label))?.url ?? null,
        next: links.find((link: { label: string }): boolean => NEXT_LABEL_PATTERN.test(link.label))?.url ?? null,
    };
}
