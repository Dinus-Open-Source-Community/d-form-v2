/** Harga per submission dari `metadata.price`; 0 berarti field bukan blok harga. */
export function formFieldPrice(metadata: Record<string, unknown> | undefined): number {
    const raw = metadata?.price;
    const value = typeof raw === 'number' ? raw : typeof raw === 'string' ? Number(raw) : Number.NaN;

    return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
}
