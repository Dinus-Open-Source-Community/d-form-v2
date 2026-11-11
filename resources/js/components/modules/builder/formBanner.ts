import type { IBuilderField } from '@/components/modules/builder/fieldMapping';
import type { TFormFieldMetadataBag } from '@/types/form';
import { cleanStoredImageUrl } from '@/lib/builderImageSanitizer';
import { readMetaText, readMetadataBag } from '@/lib/formFieldMetadata';
import { isRecord } from '@/lib/isRecord';
import { createUuid } from '@/lib/uuid';

export interface IFormBannerState {
    id: string | null;
    bannerUrl: string;
    bannerFileName: string;
    caption: string;
}

function recordMetadata(field: IFormField): TFormFieldMetadataBag {
    return isRecord(field.metadata) ? field.metadata : {};
}

function builderApiType(field: IFormField): string {
    const bt = recordMetadata(field)['builderType'];
    return typeof bt === 'string' ? bt : field.type;
}

export function defaultFormBannerState(): IFormBannerState {
    return {
        id: null,
        bannerUrl: '',
        bannerFileName: '',
        caption: '',
    };
}

export function extractFormBannerFromBuilderFields(rows: IBuilderField[]): {
    banner: IFormBannerState;
    canvasFields: IBuilderField[];
} {
    const idxFlag = rows.findIndex((f) => f.type === 'banner' && Boolean(f.metadata?.formBanner));
    const idxLegacy = idxFlag === -1 ? rows.findIndex((f) => f.type === 'banner') : -1;
    const idx = idxFlag >= 0 ? idxFlag : idxLegacy;

    if (idx === -1) {
        return { banner: defaultFormBannerState(), canvasFields: [...rows] };
    }

    const bf = rows[idx];
    const meta = readMetadataBag(bf.metadata);
    const banner: IFormBannerState = {
        id: bf.id,
        bannerUrl: readMetaText(meta, 'bannerUrl'),
        bannerFileName: readMetaText(meta, 'bannerFileName'),
        caption: readMetaText(meta, 'content'),
    };
    const canvasFields = rows.filter((_, i) => i !== idx);
    return { banner, canvasFields };
}

const FORM_BANNER_NAME = 'form_banner';

export function buildFormBannerBuilderField(state: IFormBannerState): IBuilderField | null {
    const trimmedUrl = state.bannerUrl.trim();
    const trimmedCaption = state.caption.trim();
    const previousId = typeof state.id === 'string' && state.id !== '' ? state.id : null;
    const hadPrevious = previousId !== null;
    const hasPayload = trimmedUrl !== '' || trimmedCaption !== '';

    if (!hasPayload && !hadPrevious) {
        return null;
    }

    if (!hasPayload && hadPrevious) {
        return null;
    }

    const id = previousId ?? createUuid();

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
            // URL preview lokal (`blob:`/`data:`) tidak boleh masuk payload backend;
            // `hasPayload` tetap memakai nilai mentah agar keputusan membuat field tidak berubah.
            bannerUrl: cleanStoredImageUrl(trimmedUrl),
            bannerFileName: state.bannerFileName.trim(),
            content: trimmedCaption,
            formBanner: true,
        },
    };
}

export function prependFormBannerToBackendPayload(
    canvasFields: IBuilderField[],
    banner: IFormBannerState
): IBuilderField[] {
    const synth = buildFormBannerBuilderField(banner);
    if (!synth) {
        return canvasFields;
    }
    return [synth, ...canvasFields];
}

export function pickFormBannerField(fields: readonly IFormField[]): IFormField | null {
    const flagged = [...fields].filter((f) => builderApiType(f) === 'banner' && recordMetadata(f).formBanner === true);
    if (flagged.length > 0) {
        return flagged.sort((a, b) => a.order - b.order)[0] ?? null;
    }
    const legacy = [...fields].filter((f) => builderApiType(f) === 'banner');
    return legacy.sort((a, b) => a.order - b.order)[0] ?? null;
}

export function filterBodyFields(fields: readonly IFormField[]): IFormField[] {
    return fields.filter((f) => builderApiType(f) !== 'banner');
}
