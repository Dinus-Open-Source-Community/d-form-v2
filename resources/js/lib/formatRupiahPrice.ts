const RUPIAH_NUMBER = new Intl.NumberFormat('id-ID');

/** Rupiah tanpa desimal; nilai tidak valid atau negatif menjadi `Rp 0`. */
export function formatRupiahPrice(amount: number): string {
    if (!Number.isFinite(amount) || amount < 0) {
        return 'Rp 0';
    }

    return `Rp ${RUPIAH_NUMBER.format(amount)}`;
}
