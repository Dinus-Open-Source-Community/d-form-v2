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
    registration_mode: 'single' | 'bundle' | 'team' | null
    max_team_size: number | null
    team_size: number | null
}

/** Payload for creating a form from the dashboard builder */
export interface CreateDashboardFormPayload {
    title: string
    description: string
    closed_at: string
    visible_for: string[]
    banner_url: string
    banner_caption: string
    metadata: FormRegistrationMetadata | Record<string, unknown>
    fields: BackendField[]
}

export function emptyFormRegistrationMetadata(): FormRegistrationMetadata {
    return {
        registration_mode: null,
        max_team_size: null,
        team_size: null,
    }
}

export function parseFormRegistrationMetadata(raw: unknown): FormRegistrationMetadata {
    const m =
        raw && typeof raw === 'object' && !Array.isArray(raw) ? (raw as Record<string, unknown>) : {}
    const mode = m['registration_mode']
    const rm =
        mode === 'single' || mode === 'bundle' || mode === 'team' ? mode : null
    const maxTs = m['max_team_size']
    const teamS = m['team_size']
    return {
        registration_mode: rm,
        max_team_size: maxTs != null && maxTs !== '' ? Number(maxTs) : null,
        team_size: teamS != null && teamS !== '' ? Number(teamS) : null,
    }
}

/** Server accepts `metadata` null or object; omit empty objects if preferred. */
export function toFormMetadataPayload(m: FormRegistrationMetadata): Record<string, unknown> | null {
    const out: Record<string, unknown> = {}
    if (m.registration_mode != null) {
        out.registration_mode = m.registration_mode
    }
    if (m.max_team_size != null && !Number.isNaN(m.max_team_size)) {
        out.max_team_size = m.max_team_size
    }
    if (m.team_size != null && !Number.isNaN(m.team_size)) {
        out.team_size = m.team_size
    }
    return Object.keys(out).length ? out : null
}
