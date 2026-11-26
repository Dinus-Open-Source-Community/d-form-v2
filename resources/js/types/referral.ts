export type TReferralDiscountType = 'percentage' | 'fixed';

/** Kode referral satu form: dikelola di tab Pengaturan, dilaporkan di tab Jawaban. */
export interface IReferralCode {
    id: string;
    code: string;
    prefix: string;
    body: string;
    generation_mode: 'random' | 'manual';
    attribution_mode: 'user' | 'name';
    assigned_user: { id: string; name: string; email: string } | null;
    attribution_name: string | null;
    usage_limit: number | null;
    usage_count: number;
    remaining: number | null;
    starts_at: string | null;
    expires_at: string | null;
    is_active: boolean;
    /** Diskon opsional; null pada kedua kolom berarti kode tanpa diskon. */
    discount_type: TReferralDiscountType | null;
    discount_value: number | null;
    /** Identitas dan atribusi boleh diubah selama kode belum pernah dipakai. */
    can_edit_identity: boolean;
    can_delete: boolean;
}

/** Urutan backend untuk kolom Referral di tab Jawaban. */
export type TReferralSort = 'asc' | 'desc';

/** Payload `referral` pada halaman show form. */
export interface IReferralPayload {
    enabled: boolean;
    codes: IReferralCode[];
    /** Urutan aktif dari query `referral_sort`; default backend adalah menaik. */
    sort?: TReferralSort;
    usage_url: string;
}

/** Hasil pemakaian referral yang menempel pada satu baris jawaban. */
export interface ISubmissionReferral {
    code: string | null;
    attribution: string | null;
    unit: 'single' | 'group';
    redeemed_at: string | null;
}

/** Input pembuatan kode; `body` hanya dipakai saat `generation_mode = manual`. */
export interface IReferralCodeInput {
    attribution_mode: 'user' | 'name';
    assigned_user_id?: string | null;
    attribution_name?: string | null;
    prefix: string;
    generation_mode: 'random' | 'manual';
    body?: string | null;
    usage_limit?: number | null;
    starts_at?: string | null;
    expires_at?: string | null;
    is_active: boolean;
    discount_type?: TReferralDiscountType | null;
    discount_value?: number | null;
}

/**
 * State form kode referral di drawer. Semua nilai berupa string agar cocok
 * langsung dengan kontrol input; konversi ke payload dilakukan `buildReferralCodeInput`.
 */
export interface IReferralCodeForm {
    attribution_mode: 'user' | 'name';
    assigned_user_id: string;
    attribution_name: string;
    prefix: string;
    generation_mode: 'random' | 'manual';
    body: string;
    /**
     * `type="number"` pada Input membuat Vue meng-cast nilai ke number, jadi form
     * menerima gabungan string (nilai awal) dan number (hasil ketikan).
     */
    usage_limit: string | number;
    starts_at: string;
    expires_at: string;
    is_active: boolean;
    discount_type: TReferralDiscountType;
    discount_value: string | number;
}
