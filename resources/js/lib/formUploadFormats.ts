import { DEFAULT_ALLOWED_MIME_TYPES } from '@/constants/formUpload';

const MIME_TYPE_EXTENSIONS: Readonly<Record<string, readonly string[]>> = {
    'image/jpeg': ['jpg', 'jpeg'],
    'image/png': ['png'],
    'image/webp': ['webp'],
    'image/gif': ['gif'],
    'application/pdf': ['pdf'],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['docx'],
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['xlsx'],
    'application/vnd.openxmlformats-officedocument.presentationml.presentation': ['pptx'],
    'text/csv': ['csv'],
    'text/plain': ['txt'],
};

/** Convert MIME types to the compact extension list used by upload rules. */
export function formatAllowedMimeTypesAsExtensions(mimeTypes: readonly string[]): string {
    return mimeTypes.flatMap((mimeType) => MIME_TYPE_EXTENSIONS[mimeType] ?? []).join(',');
}

/** Convert MIME types to a readable extension list for upload hints. */
export function formatAllowedUploadFormats(mimeTypes: readonly string[]): string {
    return formatAllowedMimeTypesAsExtensions(mimeTypes).split(',').join(', ');
}

/** Return the canonical extension list for a builder upload type. */
export function formatDefaultUploadFormats(type: keyof typeof DEFAULT_ALLOWED_MIME_TYPES): string {
    return formatAllowedUploadFormats(DEFAULT_ALLOWED_MIME_TYPES[type]);
}
