/**
 * Pemetaan berkas ke ikon tipe (aset vendored `resources/js/assets/file-types`).
 * Murni (tanpa DOM/Vue) agar bisa diuji tanpa memasang komponen.
 */

import { fileExtension } from '@/lib/fileDisplay';

export type TFileTypeIconKey = 'pdf' | 'excel' | 'word' | 'powerpoint' | 'csv' | 'zip' | 'image' | 'text' | 'generic';

const EXTENSION_ICONS: Record<string, TFileTypeIconKey> = {
    pdf: 'pdf',
    xls: 'excel',
    xlsx: 'excel',
    xlsm: 'excel',
    ods: 'excel',
    doc: 'word',
    docx: 'word',
    odt: 'word',
    ppt: 'powerpoint',
    pptx: 'powerpoint',
    odp: 'powerpoint',
    csv: 'csv',
    tsv: 'csv',
    zip: 'zip',
    rar: 'zip',
    '7z': 'zip',
    gz: 'zip',
    tar: 'zip',
    jpg: 'image',
    jpeg: 'image',
    png: 'image',
    gif: 'image',
    webp: 'image',
    avif: 'image',
    bmp: 'image',
    svg: 'image',
    txt: 'text',
    md: 'text',
    log: 'text',
    rtf: 'text',
};

/** Kunci ikon dari MIME bila ekstensi tidak dikenal atau tidak ada. */
function iconKeyFromMime(mime: string): TFileTypeIconKey {
    if (mime === '') return 'generic';
    if (mime === 'application/pdf') return 'pdf';
    if (mime.includes('spreadsheetml') || mime.includes('ms-excel') || mime.includes('opendocument.spreadsheet')) {
        return 'excel';
    }
    if (
        mime.includes('wordprocessingml') ||
        mime.includes('ms-word') ||
        mime.includes('msword') ||
        mime.includes('opendocument.text')
    ) {
        return 'word';
    }
    if (
        mime.includes('presentationml') ||
        mime.includes('ms-powerpoint') ||
        mime.includes('opendocument.presentation')
    ) {
        return 'powerpoint';
    }
    if (mime.includes('tab-separated-values') || mime.includes('csv')) return 'csv';
    if (
        mime.includes('zip') ||
        mime.includes('rar') ||
        mime.includes('7z') ||
        mime.includes('gzip') ||
        mime.includes('x-tar')
    ) {
        return 'zip';
    }
    if (mime.startsWith('image/')) return 'image';
    if (mime.startsWith('text/')) return 'text';

    return 'generic';
}

/**
 * Kunci ikon tipe berkas: ekstensi diperiksa lebih dulu (nama adalah sinyal
 * paling dekat dengan isi berkas), lalu MIME, lalu `generic`.
 */
export function fileTypeIconKey(mime: string, fileName: string): TFileTypeIconKey {
    return EXTENSION_ICONS[fileExtension(fileName)] ?? iconKeyFromMime(mime.toLowerCase().trim());
}
