/** Terjemahkan label link paginasi bawaan Laravel (prev/next) ke Bahasa Indonesia. */
export function paginationLabel(value: string): string {
    return value
        .replace('&laquo;', 'Sebelumnya')
        .replace('&raquo;', 'Berikutnya')
        .replace('Previous', 'Sebelumnya')
        .replace('Next', 'Berikutnya');
}
