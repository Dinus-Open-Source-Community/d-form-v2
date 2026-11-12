/** Canonical upload formats and builder limits shared by upload surfaces. */
export const DEFAULT_ALLOWED_MIME_TYPES = {
    image_upload: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    file_upload: [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/csv',
        'text/plain',
    ],
} as const;

export const DEFAULT_UPLOAD_LIMITS = {
    max_files: 5,
    max_total_size_kb: 20 * 1024,
    max_total_size_mb: 20,
} as const;

export const UPLOAD_FORMAT_OPTIONS = {
    image_upload: [
        { mimeType: 'image/jpeg', label: 'JPEG' },
        { mimeType: 'image/png', label: 'PNG' },
        { mimeType: 'image/webp', label: 'WEBP' },
        { mimeType: 'image/gif', label: 'GIF' },
    ],
    file_upload: [
        { mimeType: 'application/pdf', label: 'PDF' },
        {
            mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            label: 'DOCX',
        },
        {
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            label: 'XLSX',
        },
        {
            mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            label: 'PPTX',
        },
        { mimeType: 'text/csv', label: 'CSV' },
        { mimeType: 'text/plain', label: 'TXT' },
    ],
} as const;
