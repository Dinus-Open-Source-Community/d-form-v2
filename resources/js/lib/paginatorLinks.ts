/**
 * Kontrak paginator Inertia (kontrak 8a FINAL): { data[], current_page, per_page,
 * total, last_page, links[] }. Pager selalu dibaca dari `links` — jangan rakit
 * URL ?page= manual karena `links[].url` sudah membawa query string (tab + page).
 */
export interface IPaginatorLink {
    url: string | null
    label: string
    active: boolean
}

export interface IPaginatorMeta {
    current_page: number
    last_page: number
    total: number
    per_page?: number
    from?: number | null
    to?: number | null
    links: IPaginatorLink[]
}

/** Label mentah Laravel ("&laquo; Previous", angka, "…") -> teks tombol ID. */
export function paginatorLinkLabel(value: string): string {
    const cleaned: string = value
        .replace('&laquo;', '')
        .replace('&raquo;', '')
        .replace('Previous', 'Sebelumnya')
        .replace('Next', 'Berikutnya')
        .replace('&hellip;', '…')
        .trim()
    return cleaned === '' ? '…' : cleaned
}

/** Label aksesibilitas: angka -> "Ke halaman N", sisanya pakai teks tombol. */
export function paginatorLinkAriaLabel(value: string): string {
    const cleaned: string = paginatorLinkLabel(value)
    return /^\d+$/.test(cleaned) ? `Ke halaman ${cleaned}` : cleaned
}
