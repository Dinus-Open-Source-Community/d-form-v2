import type { IEmptyStateContentInput, IStateContent, TEmptyStateKind, TErrorStateStatus } from '@/types/state';

const DEFAULT_ERROR_CONTENT: IStateContent = {
    title: 'Terjadi kesalahan',
    description: 'Terjadi kesalahan yang tidak terduga. Coba kembali atau muat ulang halaman nanti.',
};

const ERROR_CONTENT_BY_STATUS: Readonly<Record<TErrorStateStatus, IStateContent>> = {
    403: {
        title: 'Akses ditolak',
        description:
            'Anda tidak memiliki izin untuk mengakses sumber ini. Hubungi penyelenggara jika menurut Anda ini kesalahan.',
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description:
            'Kami tidak menemukan halaman yang Anda cari. Periksa URL atau gunakan tombol di bawah untuk kembali.',
    },
    409: {
        title: 'Data sudah berubah',
        description: 'Data ini sudah diperbarui atau dipakai proses lain. Muat ulang halaman lalu coba lagi.',
    },
    429: {
        title: 'Terlalu banyak permintaan',
        description: 'Permintaan Anda terlalu sering. Tunggu beberapa saat, lalu coba lagi.',
    },
    500: DEFAULT_ERROR_CONTENT,
    502: {
        title: 'Layanan tidak tersedia',
        description: 'Server sedang sibuk atau dalam perawatan. Silakan coba lagi nanti.',
    },
    503: {
        title: 'Layanan tidak tersedia',
        description: 'Server sedang sibuk atau dalam perawatan. Silakan coba lagi nanti.',
    },
};

const EMPTY_CONTENT_BY_KIND: Readonly<Record<Exclude<TEmptyStateKind, 'search'>, IStateContent>> = {
    empty: {
        title: 'Belum ada data',
        description: 'Data akan muncul di sini setelah tersedia.',
    },
    filter: {
        title: 'Tidak ada data yang cocok',
        description: 'Ubah atau hapus filter untuk melihat semua data.',
    },
    'not-found': {
        title: 'Data tidak ditemukan',
        description: 'Data yang Anda cari mungkin sudah dihapus atau belum tersedia.',
    },
};

function isMappedStatus(status: number): status is TErrorStateStatus {
    return (
        status === 403 ||
        status === 404 ||
        status === 409 ||
        status === 429 ||
        status === 500 ||
        status === 502 ||
        status === 503
    );
}

/** Copy halaman error untuk status HTTP backend; status di luar peta memakai copy default. */
export function resolveErrorStateContent(status: number): IStateContent {
    return isMappedStatus(status) ? ERROR_CONTENT_BY_STATUS[status] : DEFAULT_ERROR_CONTENT;
}

/** Copy empty state; `query` hanya dipakai varian pencarian dan sudah di-trim. */
export function resolveEmptyStateContent(input: IEmptyStateContentInput): IStateContent {
    if (input.kind !== 'search') return EMPTY_CONTENT_BY_KIND[input.kind];

    const query = input.query?.trim() ?? '';

    if (query === '') {
        return {
            title: 'Tidak ada hasil',
            description: 'Coba kata kunci lain atau periksa ejaan.',
        };
    }

    return {
        title: `Tidak ada hasil untuk "${query}"`,
        description: 'Coba kata kunci lain atau periksa ejaan.',
    };
}
