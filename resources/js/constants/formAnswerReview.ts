/**
 * Batas batch bulk review, sama dengan `FormAnswerReviewService::MAX_BULK_IDS`.
 * Duplikasi lintas layer ini disengaja agar UI menolak batch > 100 sebelum server membalas 422.
 */
export const FORM_ANSWER_BULK_REVIEW_MAX_SELECTION = 100;
