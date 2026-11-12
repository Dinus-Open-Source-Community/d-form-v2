/**
 * Permintaan pindah halaman dari komponen pagination.
 *
 * Komponen hanya mengirim niat (halaman + jumlah baris aktif); pemilik halaman
 * yang membangun URL dan menjalankan navigasi lewat `usePaginationNavigation`.
 */
export interface IPaginationPageChange {
    page: number;
    perPage: number;
    /**
     * `true` hanya bila komponen benar-benar menampilkan selector jumlah baris.
     * Nilainya berasal dari props komponen, bukan konfigurasi pemanggil, supaya
     * `per_page` tidak bisa hilang diam-diam saat selector dipasang.
     */
    includePerPage: boolean;
}
