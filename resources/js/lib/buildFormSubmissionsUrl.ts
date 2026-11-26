import type { IFormSubmissionQuery } from '@/types/formSubmissions';

export interface IBuildFormSubmissionsUrlParams {
    /** Path halaman show form; filter tidak pernah pindah halaman. */
    pathname: string;
    filters: IFormSubmissionQuery;
    /** Jumlah baris per halaman pilihan pengguna; dipertahankan saat filter berubah. */
    perPage?: number;
}

/**
 * URL tab Jawaban dari filter aktif. `page` sengaja tidak pernah ditulis karena
 * hasil filter berubah, sedangkan `per_page` pilihan pengguna dipertahankan.
 *
 * Urutan key tetap `tab, search, review_status, referral, per_page` supaya URL
 * bisa dibandingkan dan diuji; nilai kosong tidak ikut terkirim.
 */
export function buildFormSubmissionsUrl(params: IBuildFormSubmissionsUrlParams): string {
    const search = params.filters.search?.trim() ?? '';
    const query = new URLSearchParams();

    query.set('tab', 'jawaban');
    if (search !== '') query.set('search', search);
    if (params.filters.review_status !== null) query.set('review_status', params.filters.review_status);
    if (params.filters.referral !== null) query.set('referral', params.filters.referral);
    if (params.perPage !== undefined) query.set('per_page', String(params.perPage));

    return `${params.pathname}?${query.toString()}`;
}
