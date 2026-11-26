import { describe, expect, it } from 'vitest';
import {
    buildReferralCodeInput,
    createReferralCodeForm,
    formatReferralCodePreview,
    formatReferralDiscount,
    formatReferralPeriod,
    formatReferralUsage,
    referralCodeFormFromCode,
    sanitizeReferralDiscountInput,
    sumReferralUsage,
} from '@/lib/formReferral';
import type { IReferralCode } from '@/types/referral';

const BASE_CODE: IReferralCode = {
    id: 'code-1',
    code: 'UDIN-AB12',
    prefix: 'UDIN',
    body: 'AB12',
    generation_mode: 'manual',
    attribution_mode: 'name',
    assigned_user: null,
    attribution_name: 'Komunitas Udinus',
    usage_limit: 5,
    usage_count: 2,
    remaining: 3,
    starts_at: '2026-12-01T08:00',
    expires_at: '2026-12-31T23:59',
    is_active: true,
    discount_type: 'percentage',
    discount_value: 20,
    can_edit_identity: false,
    can_delete: false,
};

describe('formatReferralUsage', () => {
    it('menulis tanpa batas saat limit null', () => {
        const code: Pick<IReferralCode, 'usage_count' | 'usage_limit'> = { usage_count: 3, usage_limit: null };

        expect(formatReferralUsage(code)).toBe('3 · tanpa batas');
    });

    it('menulis terpakai/limit saat limit diisi', () => {
        const code: Pick<IReferralCode, 'usage_count' | 'usage_limit'> = { usage_count: 2, usage_limit: 5 };

        expect(formatReferralUsage(code)).toBe('2/5');
    });
});

describe('createReferralCodeForm', () => {
    it('mengembalikan form kosong dengan mode default yang aman', () => {
        expect(createReferralCodeForm()).toEqual({
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
        });
    });
});

describe('referralCodeFormFromCode', () => {
    it('menyalin nilai kode ke bentuk string form', () => {
        expect(referralCodeFormFromCode(BASE_CODE)).toEqual({
            attribution_mode: 'name',
            assigned_user_id: '',
            attribution_name: 'Komunitas Udinus',
            prefix: 'UDIN',
            generation_mode: 'manual',
            body: 'AB12',
            usage_limit: '5',
            starts_at: '2026-12-01T08:00',
            expires_at: '2026-12-31T23:59',
            is_active: true,
            discount_type: 'percentage',
            discount_value: '20',
        });
    });

    it('memakai id pengguna saat atribusi di-assign ke user', () => {
        const form = referralCodeFormFromCode({
            ...BASE_CODE,
            attribution_mode: 'user',
            assigned_user: { id: 'user-9', name: 'Mitra', email: 'mitra@example.test' },
            attribution_name: null,
            usage_limit: null,
        });

        expect(form.assigned_user_id).toBe('user-9');
        expect(form.usage_limit).toBe('');
    });
});

describe('buildReferralCodeInput', () => {
    it('mengirim null untuk field opsional yang dikosongkan', () => {
        const input = buildReferralCodeInput({
            ...createReferralCodeForm(),
            prefix: '  UDIN  ',
            attribution_name: '  Komunitas  ',
        });

        expect(input).toEqual({
            attribution_mode: 'name',
            assigned_user_id: null,
            attribution_name: 'Komunitas',
            prefix: 'UDIN',
            generation_mode: 'random',
            body: null,
            usage_limit: null,
            starts_at: null,
            expires_at: null,
            is_active: true,
            discount_type: null,
            discount_value: null,
        });
    });

    it('hanya mengirim field milik mode yang dipilih', () => {
        const input = buildReferralCodeInput({
            ...createReferralCodeForm(),
            attribution_mode: 'user',
            assigned_user_id: ' user-9 ',
            attribution_name: 'Diabaikan',
            generation_mode: 'manual',
            body: ' ab12 ',
            usage_limit: ' 25 ',
        });

        expect(input.assigned_user_id).toBe('user-9');
        expect(input.attribution_name).toBeNull();
        expect(input.body).toBe('ab12');
        expect(input.usage_limit).toBe(25);
    });

    it('menerima batas pemakaian berupa number dari input type=number', () => {
        const input = buildReferralCodeInput({ ...createReferralCodeForm(), prefix: 'DU', usage_limit: 12 });

        expect(input.usage_limit).toBe(12);
    });

    it('mengirim diskon saat nilai diisi dan null saat dikosongkan', () => {
        const percentage = buildReferralCodeInput({
            ...createReferralCodeForm(),
            discount_type: 'percentage',
            discount_value: '20',
        });
        const fixed = buildReferralCodeInput({
            ...createReferralCodeForm(),
            discount_type: 'fixed',
            discount_value: 25_000,
        });
        const cleared = buildReferralCodeInput({
            ...createReferralCodeForm(),
            discount_type: 'fixed',
            discount_value: '',
        });

        expect(percentage.discount_type).toBe('percentage');
        expect(percentage.discount_value).toBe(20);
        expect(fixed.discount_type).toBe('fixed');
        expect(fixed.discount_value).toBe(25_000);
        expect(cleared.discount_type).toBeNull();
        expect(cleared.discount_value).toBeNull();
    });
});

describe('formatReferralDiscount', () => {
    it('menulis persen dan rupiah, kosong saat tanpa diskon', () => {
        expect(formatReferralDiscount({ discount_type: 'percentage', discount_value: 20 })).toBe('Diskon 20%');
        expect(formatReferralDiscount({ discount_type: 'fixed', discount_value: 25_000 })).toBe('Diskon Rp 25.000');
        expect(formatReferralDiscount({ discount_type: null, discount_value: null })).toBe('');
    });
});

describe('sanitizeReferralDiscountInput', () => {
    it('menyisakan digit saja', () => {
        expect(sanitizeReferralDiscountInput('percentage', '2a0')).toBe('20');
        expect(sanitizeReferralDiscountInput('fixed', 'Rp 25.000')).toBe('25000');
    });

    it('membatasi persen di 100 tanpa membatasi rupiah', () => {
        expect(sanitizeReferralDiscountInput('percentage', '150')).toBe('100');
        expect(sanitizeReferralDiscountInput('percentage', 250)).toBe('100');
        expect(sanitizeReferralDiscountInput('fixed', '150')).toBe('150');
    });

    it('memperlakukan nol, negatif, dan kosong sebagai tanpa diskon', () => {
        expect(sanitizeReferralDiscountInput('percentage', '0')).toBe('');
        expect(sanitizeReferralDiscountInput('fixed', '-5')).toBe('');
        expect(sanitizeReferralDiscountInput('percentage', '')).toBe('');
    });
});

describe('formatReferralPeriod', () => {
    it('menulis rentang saat dua tanggal terisi', () => {
        expect(formatReferralPeriod({ starts_at: '2026-12-01T08:00', expires_at: '2026-12-31T23:59' })).toBe(
            '1 Des 2026 hingga 31 Des 2026'
        );
    });

    it('menulis hanya batas yang tersedia', () => {
        expect(formatReferralPeriod({ starts_at: '2026-12-01T08:00', expires_at: null })).toBe('Mulai 1 Des 2026');
        expect(formatReferralPeriod({ starts_at: null, expires_at: '2026-12-31T23:59' })).toBe('Berakhir 31 Des 2026');
    });

    it('menulis tanpa batas waktu saat dua tanggal kosong', () => {
        expect(formatReferralPeriod({ starts_at: null, expires_at: null })).toBe('Tanpa batas waktu');
    });
});

describe('formatReferralCodePreview', () => {
    it('menyembunyikan isi acak di balik titik', () => {
        expect(formatReferralCodePreview({ prefix: 'udin', generation_mode: 'random', body: '' })).toBe('UDIN-••••');
    });

    it('menampilkan isi manual yang sedang diketik', () => {
        expect(formatReferralCodePreview({ prefix: 'UDIN', generation_mode: 'manual', body: 'ab12' })).toBe(
            'UDIN-AB12'
        );
        expect(formatReferralCodePreview({ prefix: 'UDIN', generation_mode: 'manual', body: '' })).toBe('UDIN-');
    });

    it('kosong selama prefix belum diisi', () => {
        expect(formatReferralCodePreview({ prefix: '  ', generation_mode: 'manual', body: 'AB' })).toBe('');
    });
});

describe('sumReferralUsage', () => {
    it('menjumlahkan pemakaian seluruh kode', () => {
        expect(sumReferralUsage([{ usage_count: 2 }, { usage_count: 3 }])).toBe(5);
        expect(sumReferralUsage([])).toBe(0);
    });
});
