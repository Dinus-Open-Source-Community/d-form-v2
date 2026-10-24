/**
 * Kontrak paginator Inertia (kontrak 8a FINAL): { data[], current_page, per_page,
 * total, last_page, links[] }. Nomor halaman dibangun AppPagination dari query
 * aktif; `links[]` hanya dipakai untuk navigasi cursor (prev/next).
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
