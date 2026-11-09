/** Status review yang dipakai endpoint review submission form. */
export type TFormReviewStatus = 'pending' | 'accepted' | 'rejected';

/** Status yang bisa dikembalikan oleh respons sukses review (pending tidak pernah 2xx). */
export type TFormReviewDecision = Exclude<TFormReviewStatus, 'pending'>;

/**
 * Body sukses `PATCH .../submissions/{formAnswer}/review`
 * (`FormAnswerReviewController`). Controller selalu mengisi `reviewed_at`,
 * `reviewed_by`, dan (untuk `accepted`) `registration_code`.
 */
export interface IFormReviewSuccessResponse {
    id: string;
    review_status: TFormReviewDecision;
    reviewed_at: string;
    reviewed_by: string;
    registration_code: string | null;
    cascaded_rejections: number;
}

/**
 * Body error review: `409` membawa `current_status`, `422`/`403`/`404` hanya
 * `message`.
 */
export interface IFormReviewErrorBody {
    message?: string;
    current_status?: TFormReviewStatus | null;
}
