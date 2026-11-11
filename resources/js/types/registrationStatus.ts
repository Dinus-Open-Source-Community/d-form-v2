/** Review status of one form answer — mirrors `App\Enums\FormAnswerReviewStatus`. */
export type TFormAnswerReviewStatus = 'pending' | 'accepted' | 'rejected';

/** Confirmation status of an invited team/bundle member — mirrors `App\Enums\MemberConfirmationStatus`. */
export type TMemberConfirmationStatus = 'pending' | 'accepted' | 'rejected' | 'expired';

/** Aggregate review status of a bundle group. */
export type TBundleGroupReviewStatus = 'pending' | 'partial' | 'accepted' | 'rejected';

/** Participant status shown in the event registrants list. */
export type TRegistrantStatus = 'pending' | 'accepted' | 'rejected';

/** Registration role inside a team/bundle. */
export type TRegistrationRole = 'leader' | 'member';

/** Label + Tailwind classes for a status badge. */
export interface IStatusPresentation {
    label: string;
    class: string;
}
