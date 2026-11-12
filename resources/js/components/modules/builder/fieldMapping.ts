/**
 * Maps between the rich builder field types (17) and the 5 backend API types.
 * Backend API_TYPES: input, select, textarea, datePicker, fileUpload
 */

import type { IBackendField, IBuilderField, IFieldOptionEntry } from '@/types/form-builder';
import type { TFormFieldMetadataBag } from '@/types/form';
import { DEFAULT_FORM_SECTION_ID } from '@/lib/formRegistrationMetadata';
import { cleanStoredImageUrl, stripLocalImageUrlsDeep } from '@/lib/builderImageSanitizer';
import { readMetadataBag, readMetaNumber, readMetaText, readRuleSet } from '@/lib/formFieldMetadata';
import { formatAllowedMimeTypesAsExtensions } from '@/lib/formUploadFormats';
import { isRecord } from '@/lib/isRecord';
import { createUuid } from '@/lib/uuid';
import { DEFAULT_ALLOWED_MIME_TYPES, DEFAULT_UPLOAD_LIMITS } from '@/constants/formUpload';

export type { IBackendField, IBuilderField, IFieldOptionEntry };
export { DEFAULT_ALLOWED_MIME_TYPES, DEFAULT_UPLOAD_LIMITS, UPLOAD_FORMAT_OPTIONS } from '@/constants/formUpload';

const EXTENSION_MIME_TYPES: Record<string, string> = {
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    png: 'image/png',
    webp: 'image/webp',
    gif: 'image/gif',
    pdf: 'application/pdf',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    csv: 'text/csv',
    txt: 'text/plain',
};

type TUploadFieldType = keyof typeof DEFAULT_ALLOWED_MIME_TYPES;
type TUploadLimitKey = keyof typeof DEFAULT_UPLOAD_LIMITS;

/** Clamp upload limits to their field-level bounds; an empty value restores its default. */
export function normalizeUploadLimit(key: TUploadLimitKey, value: number | string | undefined): number {
    const parsed = value === '' || value === undefined ? DEFAULT_UPLOAD_LIMITS[key] : Number(value);
    if (!Number.isFinite(parsed)) return DEFAULT_UPLOAD_LIMITS[key];

    return Math.max(1, Math.min(DEFAULT_UPLOAD_LIMITS[key], Math.floor(parsed)));
}

/** Update multiple-file metadata and discard its limits when the field becomes single-file. */
export function setUploadMultipleMetadata(
    metadata: IBuilderField['metadata'],
    enabled: boolean
): IBuilderField['metadata'] {
    const next: IBuilderField['metadata'] = { ...metadata, multiple: enabled };

    if (enabled) {
        next.max_files = normalizeUploadLimit('max_files', readLimitValue(next.max_files));
        next.max_total_size_mb = normalizeUploadLimit(
            'max_total_size_mb',
            readLimitValue(next.max_total_size_mb) ?? convertKilobytesToMegabytes(next.max_total_size_kb)
        );
        return next;
    }

    delete next.max_files;
    delete next.max_total_size_mb;
    delete next.max_total_size_kb;

    return next;
}

/** Return the supported MIME allowlist, migrating legacy comma-separated extensions when needed. */
function allowedMimeTypes(metadata: TFormFieldMetadataBag, type: TUploadFieldType): string[] {
    const available = DEFAULT_ALLOWED_MIME_TYPES[type];
    const rawTyped = metadata.allowed_mime_types;
    if (Array.isArray(rawTyped)) {
        const typed = rawTyped.filter((value): value is string => typeof value === 'string');
        const supportedTyped = available.filter((mimeType) => typed.includes(mimeType));

        return supportedTyped.length > 0 ? supportedTyped : [...available];
    }

    const legacy = typeof metadata.accepts === 'string' ? metadata.accepts : '';
    const normalizedLegacy = legacy
        .split(',')
        .map((extension) => EXTENSION_MIME_TYPES[extension.trim().replace(/^\./, '').toLowerCase()])
        .filter((mimeType): mimeType is string => mimeType !== undefined);
    const supported = available.filter((mimeType) => normalizedLegacy.includes(mimeType));

    return supported.length > 0 ? supported : [...available];
}

function toBackendUploadMetadata(
    field: IBuilderField,
    type: TUploadFieldType,
    rules: Record<string, unknown>
): Record<string, unknown> {
    const allowed = allowedMimeTypes(field.metadata, type);
    const typedAllowlistPresent = Array.isArray(field.metadata.allowed_mime_types);
    const legacyMimes = readMetaText(field.metadata, 'accepts').trim().replace(/\s+/g, '');
    const mimes = typedAllowlistPresent
        ? formatAllowedMimeTypesAsExtensions(allowed)
        : legacyMimes || formatAllowedMimeTypesAsExtensions(allowed);
    const includeLimits = field.metadata.multiple === true;

    const metadata = withMeta(field, {
        rules: { ...rules, mimes, max_size: 5120 },
        builderType: type,
        multiple: field.metadata.multiple === true,
        allowed_mime_types: allowed,
        accepts: mimes,
        ...(includeLimits
            ? {
                  max_files: normalizeUploadLimit('max_files', readLimitValue(field.metadata.max_files)),
                  max_total_size_kb:
                      normalizeUploadLimit(
                          'max_total_size_mb',
                          readLimitValue(field.metadata.max_total_size_mb) ??
                              convertKilobytesToMegabytes(field.metadata.max_total_size_kb)
                      ) * 1024,
              }
            : {}),
    });

    delete metadata.max_total_size_mb;

    if (field.metadata.multiple !== true) {
        delete metadata.max_files;
        delete metadata.max_total_size_kb;
    }

    return metadata;
}

function readLimitValue(value: unknown): number | string | undefined {
    return typeof value === 'number' || typeof value === 'string' ? value : undefined;
}

function convertKilobytesToMegabytes(value: unknown): number | string | undefined {
    const kilobytes = readLimitValue(value);

    return kilobytes === undefined ? undefined : Number(kilobytes) / 1024;
}

export { formatAllowedMimeTypesAsExtensions } from '@/lib/formUploadFormats';

/** Toggle one format while keeping at least one supported format enabled. */
export function toggleAllowedMimeType(current: string[], mimeType: string, enabled: boolean): string[] {
    const next = enabled ? [...new Set([...current, mimeType])] : current.filter((value) => value !== mimeType);

    return next.length > 0 ? next : current;
}

/**
 * Daftar display-only dimiliki `@/lib/formFieldContract` (satu sumber lintas sisi).
 * Re-export dipertahankan karena `fieldMapping` adalah seam publik builder bagi
 * pemanggil lama (mis. `Dashboard/Events/Forms/Show.vue`).
 */
export { DISPLAY_ONLY_BUILDER_TYPES, isDisplayOnlyBuilderType } from '@/lib/formFieldContract';

export function optionLabel(entry: IFieldOptionEntry): string {
    return String(entry.label ?? '').trim();
}

export function optionImageUrl(entry: IFieldOptionEntry): string | undefined {
    return entry.imageUrl?.trim() || undefined;
}

function serializeOptionChoices(options: readonly IFieldOptionEntry[]): Record<string, unknown>[] {
    return options.map((o) => {
        const label = String(o.label ?? '').trim();
        return {
            id: o.id,
            type: o.type,
            label,
            imageUrl: cleanStoredImageUrl(o.imageUrl ?? ''),
        };
    });
}

function parseOptionChoices(raw: unknown): IFieldOptionEntry[] | null {
    if (!Array.isArray(raw)) return null;
    const out: IFieldOptionEntry[] = [];
    for (const item of raw) {
        if (isRecord(item)) {
            const id = typeof item.id === 'string' && item.id !== '' ? item.id : createUuid();
            const type = item.type === 'image' ? 'image' : 'text';
            const label = typeof item.label === 'string' ? item.label.trim() : '';
            const imageUrl = typeof item.imageUrl === 'string' ? item.imageUrl.trim() : '';
            out.push({ id, type, label, imageUrl });
        } else if (typeof item === 'string') {
            const label = item.trim();
            out.push({ id: createUuid(), type: 'text', label });
        }
    }
    return out.length > 0 ? out : null;
}

function preservedMeta(f: IBuilderField): TFormFieldMetadataBag {
    const raw: TFormFieldMetadataBag = isRecord(f.metadata) ? { ...f.metadata } : {};
    // API validation allows only string|null for metadata.options (radio/checkbox).
    // Stale array-shaped `options` from DB or legacy clients must not be re-submitted.
    delete raw.options;

    // Metadata lama bisa membawa preview lokal (`blob:`/`data:`). Strip di semua nilai
    // agar payload backend tidak pernah menerima URL sementara, termasuk key yang tidak
    // dipetakan eksplisit di bawah.
    const cleaned: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(raw)) {
        cleaned[key] = stripLocalImageUrlsDeep(value);
    }

    return cleaned;
}

function withMeta(f: IBuilderField, specific: Record<string, unknown>): Record<string, unknown> {
    return { ...preservedMeta(f), ...specific };
}

/** Batas panjang teks untuk short/long — disimpan sebagai rules.min (0) + rules.max di API. */
function mergeTextRules(req: Record<string, unknown>, f: IBuilderField): Record<string, unknown> {
    const merged = { ...req };
    const raw = f.metadata?.maxLength;
    const n =
        typeof raw === 'number' ? raw : raw != null && String(raw).trim() !== '' ? parseInt(String(raw), 10) : NaN;
    if (Number.isFinite(n) && n > 0) {
        merged.min = 0;
        merged.max = Math.min(Math.floor(n), 100_000);
    }
    return merged;
}

/** Teks metadata dengan fallback saat kosong/whitespace; dipakai heading section. */
function readContentOrFallback(metadata: TFormFieldMetadataBag, fallback: string): string {
    const content = readMetaText(metadata, 'content');
    return content.trim() === '' ? fallback : content;
}

export function toBackendField(f: IBuilderField, order: number): IBackendField {
    const base = {
        id: f.id,
        label: f.label,
        description: f.description || null,
        name: f.name || `field_${f.id.replace(/-/g, '').slice(0, 12)}`,
        order,
        is_append: f.is_append === true,
    };
    const req: Record<string, unknown> = f.required ? { required: true } : {};

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
            };
        case 'email':
            return {
                ...base,
                type: 'input',
                metadata: withMeta(f, {
                    type: 'email',
                    placeholder: f.placeholder || '',
                    rules: req,
                    builderType: 'email',
                }),
            };
        case 'phone':
            return {
                ...base,
                type: 'input',
                metadata: withMeta(f, {
                    type: 'tel',
                    placeholder: f.placeholder || '',
                    rules: req,
                    builderType: 'phone',
                }),
            };
        case 'number':
            return {
                ...base,
                type: 'input',
                metadata: withMeta(f, {
                    type: 'number',
                    placeholder: f.placeholder || '',
                    rules: req,
                    builderType: 'number',
                }),
            };
        case 'time':
            return {
                ...base,
                type: 'input',
                metadata: withMeta(f, { type: 'text', placeholder: 'HH:MM', rules: req, builderType: 'time' }),
            };
        case 'rating':
            return {
                ...base,
                type: 'input',
                metadata: withMeta(f, {
                    type: 'number',
                    placeholder: '',
                    rules: { ...req, min: 1, max: readMetaNumber(f.metadata, 'maxStars', 5) },
                    builderType: 'rating',
                    maxStars: readMetaNumber(f.metadata, 'maxStars', 5),
                }),
            };
        case 'heading':
            return {
                ...base,
                type: 'input',
                metadata: withMeta(f, {
                    type: 'text',
                    placeholder: '',
                    rules: {},
                    builderType: 'heading',
                    content: readContentOrFallback(f.metadata, 'Section Heading'),
                }),
            };
        case 'image':
            return {
                ...base,
                type: 'input',
                metadata: withMeta(f, {
                    type: 'text',
                    placeholder: '',
                    rules: {},
                    builderType: 'image',
                    imageUrl: cleanStoredImageUrl(f.metadata?.imageUrl),
                    caption: readMetaText(f.metadata, 'caption'),
                    alt: readMetaText(f.metadata, 'alt'),
                }),
            };
        case 'divider':
            return {
                ...base,
                type: 'input',
                metadata: withMeta(f, { type: 'text', placeholder: '', rules: {}, builderType: 'divider' }),
            };

        case 'long_text':
            return {
                ...base,
                type: 'textarea',
                metadata: withMeta(f, {
                    placeholder: f.placeholder || '',
                    rules: mergeTextRules(req, f),
                    builderType: 'long_text',
                }),
            };
        case 'paragraph':
            return {
                ...base,
                type: 'textarea',
                metadata: withMeta(f, {
                    placeholder: '',
                    rules: {},
                    builderType: 'paragraph',
                    content: readMetaText(f.metadata, 'content'),
                }),
            };

        case 'dropdown': {
            const choices = serializeOptionChoices(
                (f.options || []).map((opt) => ({
                    ...opt,
                    type: 'text',
                    imageUrl: '',
                    label: String(opt.label ?? '').trim(),
                }))
            );
            const inCsv = choices.map((c) => c.label).join(',');
            return {
                ...base,
                type: 'select',
                metadata: withMeta(f, {
                    is_multiple: false,
                    rules: { ...req, in: inCsv },
                    builderType: 'dropdown',
                    optionChoices: choices,
                }),
            };
        }
        case 'checkbox': {
            const choices = serializeOptionChoices(f.options || []);
            const inCsv = choices.map((c) => c.label).join(',');
            return {
                ...base,
                type: 'checkbox',
                metadata: withMeta(f, {
                    is_multiple: true,
                    rules: { ...req, in: inCsv },
                    builderType: 'checkbox',
                    optionChoices: choices,
                }),
            };
        }
        case 'radio': {
            const choices = serializeOptionChoices(f.options || []);
            const inCsv = choices.map((c) => c.label).join(',');
            return {
                ...base,
                type: 'radio',
                metadata: withMeta(f, {
                    is_multiple: false,
                    rules: { ...req, in: inCsv },
                    builderType: 'radio',
                    optionChoices: choices,
                }),
            };
        }

        case 'date':
            return { ...base, type: 'datePicker', metadata: withMeta(f, { rules: req, builderType: 'date' }) };
        case 'image_upload':
            return {
                ...base,
                type: 'fileUpload',
                metadata: toBackendUploadMetadata(f, 'image_upload', req),
            };
        case 'file_upload':
            return {
                ...base,
                type: 'fileUpload',
                metadata: toBackendUploadMetadata(f, 'file_upload', req),
            };
        case 'banner':
            return {
                ...base,
                type: 'fileUpload',
                metadata: withMeta(f, {
                    rules: {},
                    builderType: 'banner',
                    accepts: 'gif, png, jpg, jpeg',
                    bannerUrl: cleanStoredImageUrl(f.metadata?.bannerUrl),
                    bannerFileName: readMetaText(f.metadata, 'bannerFileName'),
                    content: readMetaText(f.metadata, 'content'),
                    formBanner: Boolean(f.metadata?.formBanner),
                }),
            };

        default:
            return { ...base, type: 'input', metadata: withMeta(f, { type: 'text', placeholder: '', rules: req }) };
    }
}

function guessType(apiType: string, m: TFormFieldMetadataBag): string {
    if (apiType === 'input') {
        if (m.type === 'email') return 'email';
        if (m.type === 'tel') return 'phone';
        if (m.type === 'number') return 'number';
        if (typeof m.imageUrl === 'string' || typeof m.caption === 'string') return 'image';
        return 'short_text';
    }
    if (apiType === 'textarea') return 'long_text';
    if (apiType === 'select') return m.is_multiple ? 'checkbox' : 'dropdown';
    if (apiType === 'checkbox') return 'checkbox';
    if (apiType === 'radio') return 'radio';
    if (apiType === 'datePicker') return 'date';
    if (apiType === 'fileUpload') return 'file_upload';
    return 'short_text';
}

export function fromBackendField(bf: IBackendField): IBuilderField {
    const m: TFormFieldMetadataBag = { ...readMetadataBag(bf.metadata) };
    delete m.options;
    const rules = readRuleSet(m);
    const bt = readMetaText(m, 'builderType') || guessType(bf.type, m);
    const inStr = typeof rules.in === 'string' ? rules.in : '';
    const parsedChoices = parseOptionChoices(m.optionChoices);
    const optsRaw: IFieldOptionEntry[] =
        parsedChoices ??
        (['dropdown', 'checkbox', 'radio'].includes(bt)
            ? inStr
                  .split(',')
                  .map((s) => s.trim())
                  .filter(Boolean)
                  .map((label) => ({ id: createUuid(), type: 'text' as const, label }))
            : []);
    const opts: IFieldOptionEntry[] =
        bt === 'dropdown'
            ? optsRaw.map((opt) => ({
                  ...opt,
                  type: 'text',
                  imageUrl: '',
                  label: String(opt.label ?? '').trim(),
              }))
            : optsRaw;

    const maxFromRules = rules.max;
    let maxLengthForMeta: number | undefined;
    if (['short_text', 'long_text'].includes(bt) && maxFromRules != null && String(maxFromRules) !== '') {
        const n = Number(maxFromRules);
        if (Number.isFinite(n) && n > 0) {
            maxLengthForMeta = n;
        }
    }

    const sectionIdRaw = readMetaText(m, 'sectionId');
    const sectionId = sectionIdRaw.trim() !== '' ? sectionIdRaw : DEFAULT_FORM_SECTION_ID;
    const uploadType = bt === 'image_upload' || bt === 'file_upload' ? bt : null;
    const uploadMetadata =
        uploadType === null
            ? {}
            : {
                  multiple: m.multiple === true,
                  allowed_mime_types: allowedMimeTypes(
                      typeof rules.mimes === 'string' && typeof m.accepts !== 'string'
                          ? { ...m, accepts: rules.mimes }
                          : m,
                      uploadType
                  ),
                  ...(m.multiple === true || readLimitValue(m.max_files) !== undefined
                      ? { max_files: normalizeUploadLimit('max_files', readLimitValue(m.max_files)) }
                      : {}),
                  ...(m.multiple === true ||
                  readLimitValue(m.max_total_size_kb) !== undefined ||
                  readLimitValue(m.max_total_size_mb) !== undefined
                      ? {
                            max_total_size_mb: normalizeUploadLimit(
                                'max_total_size_mb',
                                readLimitValue(m.max_total_size_mb) ?? convertKilobytesToMegabytes(m.max_total_size_kb)
                            ),
                        }
                      : {}),
              };

    return {
        id: bf.id,
        type: bt,
        label: bf.label || '',
        description: bf.description || '',
        name: bf.name || '',
        placeholder: readMetaText(m, 'placeholder'),
        required: !!rules.required,
        options: opts,
        is_append: bf.is_append === true,
        metadata: {
            ...m,
            ...uploadMetadata,
            sectionId,
            maxStars: readMetaNumber(m, 'maxStars', 5) || 5,
            accepts:
                readMetaText(m, 'accepts') || (typeof rules.mimes === 'string' ? rules.mimes : '').replace(/,/g, ', '),
            formBanner: m.formBanner === true,
            ...(maxLengthForMeta != null ? { maxLength: maxLengthForMeta } : {}),
            ...(bt === 'image'
                ? {
                      imageUrl: cleanStoredImageUrl(m.imageUrl),
                      caption: readMetaText(m, 'caption'),
                      alt: readMetaText(m, 'alt'),
                  }
                : {}),
        },
    };
}

export function toBackendFields(builderFields: IBuilderField[]): IBackendField[] {
    return builderFields.map((f, i) => toBackendField(f, i + 1));
}
