/**
 * Shared form / form-fill types (Inertia page props and payloads).
 * Keep page components thin — import from here instead of inlining large `type` blocks.
 */

import type { BackendField } from '@/components/modules/builder/fieldMapping'

/** Access gate for the public form fill route */
export type FormAccessStatus =
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
    | 'prerequisite_not_met'

export type FormPurpose = 'registration' | 'other'

export interface FormSiblingOption {
    id: string
    title: string
}

export interface FormFillPageEvent {
    id: string
    slug: string
    title: string
}

export interface FormFillPageForm {
    id: string
    title: string
    description: string | null
    closed_at: string | null
    banner_url: string | null
    banner_caption: string | null
}

/** Values stored in the fill `useForm` map (matches file inputs + multi-select) */
export type FormFillAnswerValue = string | string[] | File | null

export type FormFillAnswerMap = Record<string, FormFillAnswerValue>

/** One row in checkbox / radio / dropdown image+label choices on the fill page */
export type FormFillOptionRow = { type: 'text' | 'image'; label: string; imageSrc?: string }

/** Validation rules nested under `metadata.rules` on API fields */
export type FormFieldRules = {
    required?: boolean
    in?: string
    mimes?: string
    max_size?: string | number
    min?: number
    max?: number
} & Record<string, unknown>

/** Loose JSON-like metadata bag on `IFormField.metadata` */
export type FormFieldMetadataBag = Record<string, unknown>

export interface FormRegistrationMetadata {
    purpose: FormPurpose
    requires_form_id: string | null
    registration_mode: 'single' | 'bundle' | 'team' | null
    max_team_size: number | null
    team_size: number | null
}

/**
 * Create/update metadata payload: the same keys as `FormRegistrationMetadata`, all always sent
 * so Inertia null-omission cannot wipe unrelated metadata.
 */
export type TFormMetadataPayload = Required<
    Pick<
        FormRegistrationMetadata,
        'purpose' | 'requires_form_id' | 'registration_mode' | 'max_team_size' | 'team_size'
    >
>

/** Payload for creating a form from the dashboard builder */
export interface CreateDashboardFormPayload {
    title: string
    description: string
    success_content: string
    closed_at: string
    visible_for: string[]
    banner_url: string
    banner_caption: string
    metadata: TFormMetadataPayload | null
    fields: BackendField[]
}
