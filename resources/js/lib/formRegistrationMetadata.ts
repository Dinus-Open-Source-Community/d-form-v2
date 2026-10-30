import type { FormPurpose, FormRegistrationMetadata, TFormMetadataPayload } from '@/types/form'
import type { IFormBuilderSection } from '@/types/form-builder'

/** Stable fallback for legacy forms that predate persisted section metadata. */
export const DEFAULT_FORM_SECTION_ID = 'section-1'

/** Creates the minimum valid section list for a form builder. */
export function defaultFormBuilderSections(): IFormBuilderSection[] {
    return [{ id: DEFAULT_FORM_SECTION_ID, title: 'Bagian 1' }]
}

function parseBuilderSections(raw: unknown): IFormBuilderSection[] {
    if (!Array.isArray(raw)) return defaultFormBuilderSections()
    const sections = raw.flatMap((value, index): IFormBuilderSection[] => {
        if (!value || typeof value !== 'object' || Array.isArray(value)) return []
        const record = value as Record<string, unknown>
        const id = typeof record.id === 'string' && record.id.trim() !== '' ? record.id : `section-${index + 1}`
        const title = typeof record.title === 'string' && record.title.trim() !== '' ? record.title : `Bagian ${index + 1}`
        return [{ id, title }]
    })
    return sections.length > 0 ? sections : defaultFormBuilderSections()
}

/** Default registration metadata for a newly created dashboard form. */
export function emptyFormRegistrationMetadata(): FormRegistrationMetadata {
    return {
        purpose: 'registration',
        requires_form_id: null,
        registration_mode: null,
        max_team_size: null,
        team_size: null,
        sections: defaultFormBuilderSections(),
    }
}

/** Normalizes any raw metadata bag into `FormRegistrationMetadata`; used when loading an existing form. */
export function parseFormRegistrationMetadata(raw: unknown): FormRegistrationMetadata {
    const m =
        raw && typeof raw === 'object' && !Array.isArray(raw) ? (raw as Record<string, unknown>) : {}
    const purposeRaw = m['purpose']
    const purpose: FormPurpose = purposeRaw === 'other' ? 'other' : 'registration'
    const requiresRaw = m['requires_form_id']
    const requires_form_id =
        typeof requiresRaw === 'string' && requiresRaw !== '' ? requiresRaw : null
    const mode = m['registration_mode']
    const rm =
        mode === 'single' || mode === 'bundle' || mode === 'team' ? mode : null
    const maxTs = m['max_team_size']
    const teamS = m['team_size']
    return {
        purpose,
        requires_form_id,
        registration_mode: purpose === 'other' ? (rm === 'team' || rm === 'bundle' ? null : rm) : rm,
        max_team_size: maxTs != null && maxTs !== '' ? Number(maxTs) : null,
        team_size: teamS != null && teamS !== '' ? Number(teamS) : null,
        sections: parseBuilderSections(m['sections']),
    }
}

/**
 * Builds the create/update metadata payload, always including every registration key so
 * Inertia null omission does not wipe unrelated metadata.
 */
export function toFormMetadataPayload(m: FormRegistrationMetadata): TFormMetadataPayload {
    const purpose = m.purpose === 'other' ? 'other' : 'registration'
    const isOther = purpose === 'other'
    return {
        purpose,
        requires_form_id:
            typeof m.requires_form_id === 'string' && m.requires_form_id !== ''
                ? m.requires_form_id
                : null,
        registration_mode: isOther
            ? m.registration_mode === 'single'
                ? 'single'
                : null
            : (m.registration_mode ?? null),
        max_team_size: isOther
            ? null
            : m.max_team_size != null && !Number.isNaN(Number(m.max_team_size))
              ? Number(m.max_team_size)
              : null,
        team_size: isOther
            ? null
            : m.team_size != null && !Number.isNaN(Number(m.team_size))
              ? Number(m.team_size)
              : null,
        sections: parseBuilderSections(m.sections),
    }
}
