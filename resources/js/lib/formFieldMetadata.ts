import type { FormFieldMetadataBag, FormFieldRules } from '@/types/form'

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value)
}

export function readFieldMetadata(field: IFormField): FormFieldMetadataBag {
    const m = field.metadata
    return isPlainObject(m) ? m : {}
}

export function readFieldRules(field: IFormField): FormFieldRules {
    const raw = readFieldMetadata(field).rules
    return isPlainObject(raw) ? (raw as FormFieldRules) : {}
}
