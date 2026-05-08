/** Format bilangan bulat untuk tampilan id-ID (pemisah ribuan titik). */
export function formatIntegerId(n: number): string {
    if (!Number.isFinite(n) || n <= 0) return '';
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.trunc(n));
}

/**
 * Harga/idr: titik ribuan, koma desimal — selaras dengan EventService::normalizePriceInput di PHP
 * (hapus semua titik, koma jadi titik desimal).
 */
export function formatPriceId(n: number): string {
    if (!Number.isFinite(n) || n < 0) return '';
    const rounded = Math.round(n * 100) / 100;
    if (Number.isInteger(rounded)) {
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(rounded);
    }
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(rounded);
}

/** Kuota: hanya digit, dibatasi sesuai aturan form (max 65535). */
export function parseQuotaInput(display: string): number {
    const digits = display.replace(/\D/g, '');
    if (digits === '') return 0;
    const n = parseInt(digits, 10);
    if (!Number.isFinite(n)) return 0;
    return Math.min(Math.max(n, 0), 65535);
}

/** Mirror PHP: str_replace(['.', ','], ['', '.'], $string) lalu float */
export function parsePriceInput(display: string): number {
    const t = display.trim();
    if (t === '') return 0;
    const normalized = t.replace(/\./g, '').replace(',', '.');
    const n = parseFloat(normalized);
    return Number.isFinite(n) ? Math.max(n, 0) : 0;
}

/** Saat mengetik kuota: izinkan hanya digit, tampilkan apa adanya (tanpa pemisah) mengurangi loncat kursor. Digit-only. */
export function sanitizeQuotaTyping(raw: string): string {
    return raw.replace(/\D/g, '').slice(0, 6);
}
