import { formatDateTime } from '@/lib/dateTime';
import { stripNonDigits } from '@/lib/fieldMaxLength';
import { formatRupiahPrice } from '@/lib/formatRupiahPrice';
import type { IReferralCode, IReferralCodeForm, IReferralCodeInput, TReferralDiscountType } from '@/types/referral';

/** Ringkasan kuota: `Tanpa batas`, atau `terpakai/limit`. */
export function formatReferralUsage(code: Pick<IReferralCode, 'usage_count' | 'usage_limit'>): string {
    return code.usage_limit === null ? `${code.usage_count} · tanpa batas` : `${code.usage_count}/${code.usage_limit}`;
}

/** Label diskon untuk daftar kode; string kosong berarti tanpa diskon. */
export function formatReferralDiscount(code: Pick<IReferralCode, 'discount_type' | 'discount_value'>): string {
    if (code.discount_type === null || code.discount_value === null) return '';

    return code.discount_type === 'percentage'
        ? `Diskon ${code.discount_value}%`
        : `Diskon ${formatRupiahPrice(code.discount_value)}`;
}

/**
 * Input diskon: digit positif saja, tanpa desimal. Persen dibatasi 100 di setiap
 * ketikan; nilai 0 atau kosong berarti kode tanpa diskon.
 */
export function sanitizeReferralDiscountInput(type: TReferralDiscountType, raw: string | number): string {
    const text = String(raw ?? '');

    // Nilai negatif ditolak, bukan dibalik menjadi positif oleh penyaringan digit.
    if (text.includes('-')) return '';

    const digits = stripNonDigits(text);

    if (digits === '') return '';

    const value = Number.parseInt(digits, 10);

    if (!Number.isFinite(value) || value < 1) return '';

    return String(type === 'percentage' ? Math.min(value, 100) : value);
}

/** Form kosong untuk kode baru; juga dipakai untuk mengosongkan drawer setelah simpan. */
export function createReferralCodeForm(): IReferralCodeForm {
    return {
        attribution_mode: 'name',
        assigned_user_id: '',
        attribution_name: '',
        prefix: '',
        generation_mode: 'random',
        body: '',
        usage_limit: '',
        starts_at: '',
        expires_at: '',
        is_active: true,
        discount_type: 'percentage',
        discount_value: '',
    };
}

/** Isi form dari kode yang sedang diedit. */
export function referralCodeFormFromCode(code: IReferralCode): IReferralCodeForm {
    return {
        attribution_mode: code.attribution_mode,
        assigned_user_id: code.assigned_user?.id ?? '',
        attribution_name: code.attribution_name ?? '',
        prefix: code.prefix,
        generation_mode: code.generation_mode,
        body: code.body,
        usage_limit: code.usage_limit === null ? '' : String(code.usage_limit),
        starts_at: code.starts_at ?? '',
        expires_at: code.expires_at ?? '',
        is_active: code.is_active,
        discount_type: code.discount_type ?? 'percentage',
        discount_value: code.discount_value === null ? '' : String(code.discount_value),
    };
}

/** Payload create/update; field kosong dikirim `null` agar bisa dihapus saat update. */
export function buildReferralCodeInput(form: IReferralCodeForm): IReferralCodeInput {
    const usageLimit = String(form.usage_limit).trim();
    const discountValue = String(form.discount_value).trim();
    const hasDiscount = discountValue !== '' && Number(discountValue) > 0;

    return {
        attribution_mode: form.attribution_mode,
        assigned_user_id: form.attribution_mode === 'user' ? form.assigned_user_id.trim() || null : null,
        attribution_name: form.attribution_mode === 'name' ? form.attribution_name.trim() || null : null,
        prefix: form.prefix.trim(),
        generation_mode: form.generation_mode,
        body: form.generation_mode === 'manual' ? form.body.trim() : null,
        usage_limit: usageLimit === '' ? null : Number(usageLimit),
        starts_at: form.starts_at.trim() || null,
        expires_at: form.expires_at.trim() || null,
        is_active: form.is_active,
        discount_type: hasDiscount ? form.discount_type : null,
        discount_value: hasDiscount ? Number(discountValue) : null,
    };
}

/** Rentang berlaku yang terbaca; tanpa tanggal berarti kode tidak dibatasi waktu. */
export function formatReferralPeriod(code: Pick<IReferralCode, 'starts_at' | 'expires_at'>): string {
    const startsAt = code.starts_at === null ? '' : formatDateTime({ value: code.starts_at, variant: 'date-medium' });
    const expiresAt =
        code.expires_at === null ? '' : formatDateTime({ value: code.expires_at, variant: 'date-medium' });

    if (startsAt !== '' && expiresAt !== '') return `${startsAt} hingga ${expiresAt}`;
    if (startsAt !== '') return `Mulai ${startsAt}`;
    if (expiresAt !== '') return `Berakhir ${expiresAt}`;

    return 'Tanpa batas waktu';
}

/** Pratinjau kode dari format yang sedang diisi; kosong selama prefix belum diisi. */
export function formatReferralCodePreview(
    form: Pick<IReferralCodeForm, 'prefix' | 'generation_mode' | 'body'>
): string {
    const prefix = form.prefix.trim().toUpperCase();
    if (prefix === '') return '';

    if (form.generation_mode === 'random') return `${prefix}-••••`;

    const body = form.body.trim().toUpperCase();

    return body === '' ? `${prefix}-` : `${prefix}-${body}`;
}

/** Total pemakaian seluruh kode untuk ringkasan di header kartu. */
export function sumReferralUsage(codes: readonly Pick<IReferralCode, 'usage_count'>[]): number {
    return codes.reduce((total: number, code): number => total + code.usage_count, 0);
}
