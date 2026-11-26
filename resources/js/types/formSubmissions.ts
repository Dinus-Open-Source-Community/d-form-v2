import type { TFormAnswerReviewStatus } from '@/types/registrationStatus';

/** Filter keberadaan kode referral pada satu baris jawaban. */
export type TSubmissionReferralFilter = 'with' | 'without';

/** Filter aktif daftar jawaban; `null` berarti tanpa filter. */
export interface IFormSubmissionQuery {
    search: string | null;
    review_status: TFormAnswerReviewStatus | null;
    referral: TSubmissionReferralFilter | null;
}
