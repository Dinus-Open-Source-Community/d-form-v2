/** Available answer presentations shared by desktop and mobile breakpoints. */
export type TFormAnswersView = 'table' | 'card';

/** Review status options used by the client-side answer list filter. */
export type TFormAnswersReviewFilter = 'all' | 'pending' | 'accepted' | 'rejected';

/** Column metadata used by answer list presentations. */
export interface IFormAnswersColumn {
    key: string;
    label: string;
    isFile: boolean;
}

/** Accessible label and value for an answer presentation option. */
export interface IFormAnswersViewOption {
    value: TFormAnswersView;
    label: string;
}
