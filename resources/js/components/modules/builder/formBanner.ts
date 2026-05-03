import type { BuilderField } from '@/components/modules/builder/fieldMapping'

export interface FormBannerState {
    id: string | null
    bannerUrl: string
    bannerFileName: string
    caption: string
}

function recordMetadata(field: IFormField): Record<string, unknown> {
    return field.metadata && typeof field.metadata === 'object' ? field.metadata : {}
}

function builderApiType(field: IFormField): string {
    const m = recordMetadata(field)
    const bt = m.builderType
    return typeof bt === 'string' ? bt : field.type
}

export function defaultFormBannerState(): FormBannerState {
    return {
        id: null,
        bannerUrl: '',
        bannerFileName: '',
        caption: '',
    }
}

/** Public URL / data URL / relative storage path → safe display URL for <img>. */
export function normalizeBannerSrc(raw: string): string {
    const u = raw.trim()
    if (!u) return ''
    if (u.startsWith('data:')) return u
    if (/^https?:\/\//i.test(u)) return u
    if (u.startsWith('/')) return u
    return `/storage/${u.replace(/^\/+/, '')}`
}

export function extractFormBannerFromBuilderFields(rows: BuilderField[]): {
    banner: FormBannerState
    canvasFields: BuilderField[]
} {
    const idxFlag = rows.findIndex(
        (f) =>
            f.type === 'banner' &&
            Boolean((f.metadata as Record<string, unknown> | undefined)?.formBanner),
    )
    const idxLegacy = idxFlag === -1 ? rows.findIndex((f) => f.type === 'banner') : -1
    const idx = idxFlag >= 0 ? idxFlag : idxLegacy

    if (idx === -1) {
        return { banner: defaultFormBannerState(), canvasFields: [...rows] }
    }

    const bf = rows[idx]
    const meta = bf.metadata ?? {}
    const banner: FormBannerState = {
        id: bf.id,
        bannerUrl: typeof meta.bannerUrl === 'string' ? meta.bannerUrl : '',
        bannerFileName: typeof meta.bannerFileName === 'string' ? meta.bannerFileName : '',
        caption: typeof meta.content === 'string' ? meta.content : '',
    }
    const canvasFields = rows.filter((_, i) => i !== idx)
    return { banner, canvasFields }
}

const FORM_BANNER_NAME = 'form_banner'

export function buildFormBannerBuilderField(state: FormBannerState): BuilderField | null {
    const trimmedUrl = state.bannerUrl.trim()
    const trimmedCaption = state.caption.trim()
    const hadPrevious = typeof state.id === 'string' && state.id !== ''
    const hasPayload = trimmedUrl !== '' || trimmedCaption !== ''

    if (!hasPayload && !hadPrevious) {
        return null
    }

    if (!hasPayload && hadPrevious) {
        return null
    }

    const id = hadPrevious ? state.id! : crypto.randomUUID()

    return {
        id,
        type: 'banner',
        label: 'Form banner',
        description: '',
        name: FORM_BANNER_NAME,
        placeholder: '',
        required: false,
        options: [],
        metadata: {
            accepts: 'gif, png, jpg, jpeg',
            bannerUrl: trimmedUrl,
            bannerFileName: state.bannerFileName.trim(),
            content: trimmedCaption,
            formBanner: true,
        },
    }
}

export function prependFormBannerToBackendPayload(
    canvasFields: BuilderField[],
    banner: FormBannerState,
): BuilderField[] {
    const synth = buildFormBannerBuilderField(banner)
    if (!synth) {
        return canvasFields
    }
    return [synth, ...canvasFields]
}

export function pickFormBannerField(fields: readonly IFormField[]): IFormField | null {
    const flagged = [...fields].filter((f) => builderApiType(f) === 'banner' && recordMetadata(f).formBanner === true)
    if (flagged.length > 0) {
        return flagged.sort((a, b) => a.order - b.order)[0] ?? null
    }
    const legacy = [...fields].filter((f) => builderApiType(f) === 'banner')
    return legacy.sort((a, b) => a.order - b.order)[0] ?? null
}

export function filterBodyFields(fields: readonly IFormField[]): IFormField[] {
    return fields.filter((f) => builderApiType(f) !== 'banner')
}
