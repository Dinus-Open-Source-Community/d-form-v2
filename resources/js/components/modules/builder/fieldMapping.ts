/**
 * Maps between the rich builder field types (17) and the 5 backend API types.
 * Backend API_TYPES: input, select, textarea, datePicker, fileUpload
 */

import type { BackendField, BuilderField, FieldOptionEntry } from '@/types/form-builder'
import { cleanStoredImageUrl } from '@/utils/composables/useBuilderImageUpload'

export type { BackendField, BuilderField, FieldOptionEntry }

/** Builder type display-only: tidak menerima jawaban, tidak masuk answer keys. */
export const DISPLAY_ONLY_BUILDER_TYPES: ReadonlySet<string> = new Set([
    'heading',
    'paragraph',
    'divider',
    'banner',
    'image',
])

export function isDisplayOnlyBuilderType(type: unknown): boolean {
    return typeof type === 'string' && DISPLAY_ONLY_BUILDER_TYPES.has(type)
}

export function optionLabel(entry: FieldOptionEntry): string {
    return String(entry.label ?? '').trim()
}

export function optionImageUrl(entry: FieldOptionEntry): string | undefined {
    return entry.imageUrl?.trim() || undefined
}

function serializeOptionChoices(options: readonly FieldOptionEntry[]): Record<string, unknown>[] {
    return options.map((o) => {
        const label = String(o.label ?? '').trim()
        return {
            id: o.id,
            type: o.type,
            label,
            imageUrl: cleanStoredImageUrl(o.imageUrl ?? '')
        }
    })
}

function parseOptionChoices(raw: unknown): FieldOptionEntry[] | null {
    if (!Array.isArray(raw)) return null
    const out: FieldOptionEntry[] = []
    for (const item of raw) {
        if (item && typeof item === 'object' && item !== null) {
            const row = item as Record<string, unknown>
            const id = String(row.id ?? crypto.randomUUID())
            const type = row.type === 'image' ? 'image' : 'text'
            const label = String(row.label ?? '').trim()
            const imageUrl = String(row.imageUrl ?? '').trim()
            out.push({ id, type, label, imageUrl })
        } else if (typeof item === 'string') {
            const label = item.trim()
            out.push({ id: crypto.randomUUID(), type: 'text', label })
        }
    }
    return out.length > 0 ? out : null
}

function preservedMeta(f: BuilderField): Record<string, unknown> {
    const raw =
        f.metadata && typeof f.metadata === 'object' && !Array.isArray(f.metadata)
            ? { ...f.metadata }
            : {}
    // API validation allows only string|null for metadata.options (radio/checkbox).
    // Stale array-shaped `options` from DB or legacy clients must not be re-submitted.
    delete raw.options
    return raw
}

function withMeta(f: BuilderField, specific: Record<string, unknown>): Record<string, unknown> {
    return { ...preservedMeta(f), ...specific }
}

/** Batas panjang teks untuk short/long — disimpan sebagai rules.min (0) + rules.max di API. */
function mergeTextRules(req: Record<string, unknown>, f: BuilderField): Record<string, unknown> {
    const merged = { ...req }
    const raw = f.metadata?.maxLength
    const n =
        typeof raw === 'number'
            ? raw
            : raw != null && String(raw).trim() !== ''
              ? parseInt(String(raw), 10)
              : NaN
    if (Number.isFinite(n) && n > 0) {
        merged.min = 0
        merged.max = Math.min(Math.floor(n), 100_000)
    }
    return merged
}

export function toBackendField(f: BuilderField, order: number): BackendField {
    const base = {
        id: f.id,
        label: f.label,
        description: f.description || null,
        name: f.name || `field_${f.id.replace(/-/g, '').slice(0, 12)}`,
        order,
        is_append: f.is_append === true,
    }
    const req: Record<string, unknown> = f.required ? { required: true } : {}

    switch (f.type) {
        case 'short_text':
            return {
                ...base,
                type: 'input',
                metadata: withMeta(f, {
                    type: 'text',
                    placeholder: f.placeholder || '',
                    rules: mergeTextRules(req, f),
                    builderType: 'short_text',
                }),
            }
        case 'email':      return { ...base, type: 'input', metadata: withMeta(f, { type: 'email', placeholder: f.placeholder || '', rules: req, builderType: 'email' }) }
        case 'phone':      return { ...base, type: 'input', metadata: withMeta(f, { type: 'tel', placeholder: f.placeholder || '', rules: req, builderType: 'phone' }) }
        case 'number':     return { ...base, type: 'input', metadata: withMeta(f, { type: 'number', placeholder: f.placeholder || '', rules: req, builderType: 'number' }) }
        case 'time':       return { ...base, type: 'input', metadata: withMeta(f, { type: 'text', placeholder: 'HH:MM', rules: req, builderType: 'time' }) }
        case 'rating':     return { ...base, type: 'input', metadata: withMeta(f, { type: 'number', placeholder: '', rules: { ...req, min: 1, max: (f.metadata?.maxStars as number) ?? 5 }, builderType: 'rating', maxStars: (f.metadata?.maxStars as number) ?? 5 }) }
        case 'heading':    return { ...base, type: 'input', metadata: withMeta(f, { type: 'text', placeholder: '', rules: {}, builderType: 'heading', content: (f.metadata?.content as string) || 'Section Heading' }) }
        case 'image':      return { ...base, type: 'input', metadata: withMeta(f, { type: 'text', placeholder: '', rules: {}, builderType: 'image', imageUrl: cleanStoredImageUrl(f.metadata?.imageUrl), caption: (f.metadata?.caption as string) || '', alt: (f.metadata?.alt as string) || '' }) }
        case 'divider':    return { ...base, type: 'input', metadata: withMeta(f, { type: 'text', placeholder: '', rules: {}, builderType: 'divider' }) }

        case 'long_text':
            return {
                ...base,
                type: 'textarea',
                metadata: withMeta(f, {
                    placeholder: f.placeholder || '',
                    rules: mergeTextRules(req, f),
                    builderType: 'long_text',
                }),
            }
        case 'paragraph':  return { ...base, type: 'textarea', metadata: withMeta(f, { placeholder: '', rules: {}, builderType: 'paragraph', content: (f.metadata?.content as string) || '' }) }

        case 'dropdown': {
            const choices = serializeOptionChoices(
                (f.options || []).map((opt) => ({
                    ...opt,
                    type: 'text',
                    imageUrl: '',
                    label: String(opt.label ?? '').trim(),
                })),
            )
            const inCsv = choices.map((c) => c.label).join(',')
            return {
                ...base,
                type: 'select',
                metadata: withMeta(f, {
                    is_multiple: false,
                    rules: { ...req, in: inCsv },
                    builderType: 'dropdown',
                    optionChoices: choices,
                }),
            }
        }
        case 'checkbox': {
            const choices = serializeOptionChoices(f.options || [])
            const inCsv = choices.map((c) => c.label).join(',')
            return {
                ...base,
                type: 'checkbox',
                metadata: withMeta(f, {
                    is_multiple: true,
                    rules: { ...req, in: inCsv },
                    builderType: 'checkbox',
                    optionChoices: choices,
                }),
            }
        }
        case 'radio': {
            const choices = serializeOptionChoices(f.options || [])
            const inCsv = choices.map((c) => c.label).join(',')
            return {
                ...base,
                type: 'radio',
                metadata: withMeta(f, {
                    is_multiple: false,
                    rules: { ...req, in: inCsv },
                    builderType: 'radio',
                    optionChoices: choices,
                }),
            }
        }

        case 'date':         return { ...base, type: 'datePicker', metadata: withMeta(f, { rules: req, builderType: 'date' }) }
        case 'image_upload': {
            const mimes = ((f.metadata?.accepts as string) || '').trim().replace(/\s+/g, '') || 'png,jpg,jpeg'
            return { ...base, type: 'fileUpload', metadata: withMeta(f, { rules: { ...req, mimes, max_size: 5120 }, builderType: 'image_upload' }) }
        }
        case 'file_upload': {
            const mimes = ((f.metadata?.accepts as string) || '').trim().replace(/\s+/g, '') || 'pdf,doc,xls'
            return { ...base, type: 'fileUpload', metadata: withMeta(f, { rules: { ...req, mimes, max_size: 10240 }, builderType: 'file_upload' }) }
        }
        case 'banner':
            return {
                ...base,
                type: 'fileUpload',
                metadata: withMeta(f, {
                    rules: {},
                    builderType: 'banner',
                    accepts: 'gif, png, jpg, jpeg',
                    bannerUrl: (f.metadata?.bannerUrl as string) || '',
                    bannerFileName: (f.metadata?.bannerFileName as string) || '',
                    content: (f.metadata?.content as string) || '',
                    formBanner: Boolean(f.metadata?.formBanner),
                }),
            }

        default: return { ...base, type: 'input', metadata: withMeta(f, { type: 'text', placeholder: '', rules: req }) }
    }
}

function guessType(apiType: string, m: Record<string, unknown>): string {
    if (apiType === 'input') {
        if (m.type === 'email') return 'email'
        if (m.type === 'tel') return 'phone'
        if (m.type === 'number') return 'number'
        if (typeof m.imageUrl === 'string' || typeof m.caption === 'string') return 'image'
        return 'short_text'
    }
    if (apiType === 'textarea') return 'long_text'
    if (apiType === 'select') return m.is_multiple ? 'checkbox' : 'dropdown'
    if (apiType === 'checkbox') return 'checkbox'
    if (apiType === 'radio') return 'radio'
    if (apiType === 'datePicker') return 'date'
    if (apiType === 'fileUpload') return 'file_upload'
    return 'short_text'
}

export function fromBackendField(bf: BackendField): BuilderField {
    const mFull: Record<string, unknown> =
        bf.metadata && typeof bf.metadata === 'object' ? (bf.metadata as Record<string, unknown>) : {}
    const m: Record<string, unknown> = { ...mFull }
    delete m.options
    const rules = (m.rules as Record<string, unknown>) || {}
    const bt = (m.builderType as string) || guessType(bf.type, m)
    const inStr = (rules.in as string) || ''
    const parsedChoices = parseOptionChoices(m.optionChoices)
    const optsRaw: FieldOptionEntry[] =
        parsedChoices ??
        (['dropdown', 'checkbox', 'radio'].includes(bt)
            ? inStr
                  .split(',')
                  .map((s) => s.trim())
                  .filter(Boolean)
                  .map((label) => ({ id: crypto.randomUUID(), type: 'text' as const, label }))
            : [])
    const opts: FieldOptionEntry[] =
        bt === 'dropdown'
            ? optsRaw.map((opt) => ({
                  ...opt,
                  type: 'text',
                  imageUrl: '',
                  label: String(opt.label ?? '').trim(),
              }))
            : optsRaw

    const maxFromRules = rules.max
    let maxLengthForMeta: number | undefined
    if (['short_text', 'long_text'].includes(bt) && maxFromRules != null && String(maxFromRules) !== '') {
        const n = Number(maxFromRules)
        if (Number.isFinite(n) && n > 0) {
            maxLengthForMeta = n
        }
    }

    return {
        id: bf.id,
        type: bt,
        label: bf.label || '',
        description: bf.description || '',
        name: bf.name || '',
        placeholder: (m.placeholder as string) || '',
        required: !!rules.required,
        options: opts,
        is_append: bf.is_append === true,
        metadata: {
            ...m,
            maxStars: (m.maxStars as number) || 5,
            accepts: ((rules.mimes as string) || '').replace(/,/g, ', '),
            formBanner: m.formBanner === true,
            ...(maxLengthForMeta != null ? { maxLength: maxLengthForMeta } : {}),
            ...(bt === 'image'
                ? {
                      imageUrl: cleanStoredImageUrl(m.imageUrl),
                      caption: typeof m.caption === 'string' ? m.caption : '',
                      alt: typeof m.alt === 'string' ? m.alt : '',
                  }
                : {}),
        },
    }
}

export function toBackendFields(builderFields: BuilderField[]): BackendField[] {
    return builderFields.map((f, i) => toBackendField(f, i + 1))
}
