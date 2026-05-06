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
    | 'already_submitted'

export interface FormFillPageEvent {
    id: string
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

/** Payload for creating a form from the dashboard builder */
export interface CreateDashboardFormPayload {
    title: string
    description: string
    closed_at: string
    visible_for: string[]
    banner_url: string
    banner_caption: string
    fields: BackendField[]
}
