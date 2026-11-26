import { isRecord } from '@/adapters/http/httpError';
import { requestJson } from '@/adapters/http/httpClient';
import type { IFormPriceQuote } from '@/types/form';

/** Hasil cek satu kode referral di halaman isi form. */
export interface IReferralCodeCheckResult {
    valid: boolean;
    message: string | null;
    price: IFormPriceQuote | null;
}

/** Tolak quote di luar kontrak: semua angka wajib number dan jenis diskon dikenal. */
function parsePriceQuote(raw: unknown): IFormPriceQuote | null {
    if (!isRecord(raw)) return null;

    const { base, discount_type, discount_value, discount, final } = raw;

    if (typeof base !== 'number' || typeof discount !== 'number' || typeof final !== 'number') return null;

    const discountType = discount_type === 'percentage' || discount_type === 'fixed' ? discount_type : null;

    if (discount_type !== null && discountType === null) return null;

    const discountValue = typeof discount_value === 'number' ? discount_value : null;

    if (discount_value !== null && discountValue === null) return null;

    return {
        base,
        discount_type: discountType,
        discount_value: discountValue,
        discount,
        final,
    };
}

/** Tolak payload di luar kontrak: `valid` wajib boolean, `message` string atau null. */
function parseReferralCodeCheckResult(payload: unknown): IReferralCodeCheckResult | null {
    if (!isRecord(payload)) return null;
    if (typeof payload.valid !== 'boolean') return null;
    if (payload.message !== null && typeof payload.message !== 'string') return null;

    return {
        valid: payload.valid,
        message: payload.message,
        price: parsePriceQuote(payload.price),
    };
}

/** Cek kode referral; `signal` dipakai debounce pembatalan saat kode berubah. */
export async function checkReferralCode(
    url: string,
    code: string,
    signal?: AbortSignal
): Promise<IReferralCodeCheckResult> {
    return requestJson({ url, method: 'POST', body: { code }, signal }, parseReferralCodeCheckResult);
}
