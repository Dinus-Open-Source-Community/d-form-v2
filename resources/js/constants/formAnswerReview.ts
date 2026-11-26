import type { TSubmissionReferralFilter } from '@/types/formSubmissions';
import type { TFormAnswerReviewStatus } from '@/types/registrationStatus';

/**
 * Batas batch bulk review, sama dengan `FormAnswerReviewService::MAX_BULK_IDS`.
 * Duplikasi lintas layer ini disengaja agar UI menolak batch > 100 sebelum server membalas 422.
 */
export const FORM_ANSWER_BULK_REVIEW_MAX_SELECTION = 100;

/**
 * Batas kolom tabel Jawaban (permintaan PM: maksimal 10).
 * Lima kolom tetap: pilih, pengirim, status review, dikirim, aksi.
 * Referral adalah kolom tetap keenam yang hanya muncul saat fitur referral aktif.
 * Sisa slot diisi kolom field form sesuai urutan builder.
 */
export const FORM_ANSWER_TABLE_MAX_COLUMNS = 10;
export const FORM_ANSWER_TABLE_FIXED_COLUMNS = 5;

/** Chip filter status review; `null` berarti semua status. Label pendek untuk baris chip. */
export const FORM_ANSWER_REVIEW_STATUS_FILTER_OPTIONS: ReadonlyArray<{
    value: TFormAnswerReviewStatus | null;
    label: string;
}> = [
    { value: null, label: 'Semua' },
    { value: 'pending', label: 'Menunggu' },
    { value: 'accepted', label: 'Diterima' },
    { value: 'rejected', label: 'Ditolak' },
];

/** Chip filter referral; keduanya toggle, tanpa opsi "semua" agar tidak dobel dengan chip status. */
export const FORM_ANSWER_REFERRAL_FILTER_OPTIONS: ReadonlyArray<{
    value: TSubmissionReferralFilter;
    label: string;
}> = [
    { value: 'with', label: 'Dengan kode' },
    { value: 'without', label: 'Tanpa kode' },
];
