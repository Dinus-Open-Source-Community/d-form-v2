import { routes } from '@/lib/routes';

export interface IBuildUserIndexUrlParams {
    /** Kata kunci pencarian; nilai kosong atau hanya spasi dibuang. */
    search?: string;
    /** Role terpilih; `null` atau string kosong berarti semua role. */
    role?: string | null;
    /** Halaman tujuan; halaman pertama tidak perlu ditulis di URL. */
    page?: number;
    /** Jumlah baris per halaman; hanya ditulis bila pemanggil menentukannya. */
    perPage?: number;
    /** Deep link panel edit. */
    edit?: string | null;
    /** Deep link panel create; dikirim sebagai `create=1`. */
    create?: boolean;
}

function appendIfPositive(query: URLSearchParams, key: string, value: number | undefined): void {
    if (value === undefined || !Number.isInteger(value) || value <= 0) return;

    query.set(key, String(value));
}

/**
 * Bangun URL direktori pengguna dari filter aktif.
 *
 * Urutan key tetap `search, role, page, per_page, create, edit` supaya URL bisa
 * dibandingkan dan diuji; nilai kosong tidak pernah ikut terkirim.
 */
export function buildUserIndexUrl(params: IBuildUserIndexUrlParams): string {
    const search = params.search?.trim() ?? '';
    const role = params.role?.trim() ?? '';
    const edit = params.edit?.trim() ?? '';
    const query = new URLSearchParams();

    if (search !== '') query.set('search', search);
    if (role !== '') query.set('role', role);
    if (params.page !== undefined && params.page > 1) appendIfPositive(query, 'page', params.page);
    appendIfPositive(query, 'per_page', params.perPage);
    if (params.create === true) query.set('create', '1');
    if (edit !== '') query.set('edit', edit);

    const searchParams = query.toString();
    const indexUrl = routes.admin.users.index;

    return searchParams === '' ? indexUrl : `${indexUrl}?${searchParams}`;
}
