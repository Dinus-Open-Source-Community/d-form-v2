/**
 * Shared form / form-fill types (Inertia page props and payloads).
 * Keep page components thin — import from here instead of inlining large `type` blocks.
 */

import type { IBackendField, IFormBuilderSection } from '@/types/form-builder';

/** Access gate for the public form fill route */
export type TFormAccessStatus =
    | 'allowed'
    | 'not_visible'
    | 'form_closed'
    | 'registration_not_open'
    | 'quota_full'
    | 'unsupported_registration_mode'
    | 'pending_team_confirmation'
    | 'invitation_closed'
    | 'already_submitted'
    | 'event_form_already_chosen'
    | 'prerequisite_not_met';

export type TFormPurpose = 'registration' | 'other';

export interface IFormSiblingOption {
    id: string;
    title: string;
}

export interface IFormFillPageEvent {
    id: string;
    slug: string;
    title: string;
}

export interface IFormFillPageForm {
    id: string;
    title: string;
    description: string | null;
    closed_at: string | null;
    banner_url: string | null;
    banner_caption: string | null;
}

/** Values stored in the fill `useForm` map (matches file inputs + multi-select) */
export type TFormFillAnswerValue = string | string[] | File | null;

export type TFormFillAnswerMap = Record<string, TFormFillAnswerValue>;

/** One row in checkbox / radio / dropdown image+label choices on the fill page */
export type TFormFillOptionRow = { type: 'text' | 'image'; label: string; imageSrc?: string };

/** One participation slot for a fill field: `null` = the leader/primary answer, `>= 0` = bundle participant. */
export interface IFormFieldParticipationSlot {
    slotIndex: number | null;
    title: string;
}

/** Access-block card copy for the fill page; `success` marks an informational (not error) block. */
export interface IFormFillBlockCopy {
    title: string;
    body: string;
    success?: boolean;
}

/** Field run rendered on the fill page: plain list or a bundle participant group. */
export type TFormFillSegment = { type: 'linear'; fields: IFormField[] } | { type: 'bundleGroup'; fields: IFormField[] };

/** Layout variant for one fill field card row. */
export type TFormFillSlotVariant = 'linear' | 'bundleParticipant';

export interface IFormFillSlotUiInput {
    variant: TFormFillSlotVariant;
    stackIndex: number;
    fieldHasDescription: boolean;
    slotHasTitle: boolean;
    slotIndex: number | null;
}

/** Resolved presentation flags/classes for one fill field card row. */
export interface IFormFillSlotUi {
    showSubtitle: boolean;
    showDescription: boolean;
    headerBorderClass: string;
    cardHeaderSpacingClass: string;
    cardContentSpacingClass: string;
    errorSpacingClass: string;
}

/** Validation rules nested under `metadata.rules` on API fields */
export type TFormFieldRules = {
    required?: boolean;
    in?: string;
    mimes?: string;
    max_size?: string | number;
    min?: number;
    max?: number;
} & Record<string, unknown>;

/** Loose JSON-like metadata bag on `IFormField.metadata` */
export type TFormFieldMetadataBag = Record<string, unknown>;

export interface IFormRegistrationMetadata {
    purpose: TFormPurpose;
    requires_form_id: string | null;
    registration_mode: 'single' | 'bundle' | 'team' | null;
    max_team_size: number | null;
    team_size: number | null;
    sections: IFormBuilderSection[];
}

/**
 * Create/update metadata payload: the same keys as `IFormRegistrationMetadata`, all always sent
 * so Inertia null-omission cannot wipe unrelated metadata.
 */
export type TFormMetadataPayload = Required<
    Pick<
        IFormRegistrationMetadata,
        'purpose' | 'requires_form_id' | 'registration_mode' | 'max_team_size' | 'team_size'
    > & {
        sections: IFormBuilderSection[];
    }
>;

/** Payload for creating a form from the dashboard builder */
export interface ICreateDashboardFormPayload {
    title: string;
    description: string;
    success_content: string;
    closed_at: string;
    visible_for: string[];
    banner_url: string;
    banner_caption: string;
    metadata: TFormMetadataPayload | null;
    fields: IBackendField[];
}
