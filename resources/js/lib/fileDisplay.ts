/**
 * Representasi file untuk UI unggah: ukuran, ekstensi, dan persentase.
 * Murni (tanpa DOM/komponen); ikon tipe berkas ada di `lib/fileTypeIcon`.
 */

const MB = 1024 * 1024;
const KB = 1024;
const GB = 1024 * MB;

/**
 * Ukuran file dalam locale id-ID: `512 B`, `700 KB`, `2,3 MB`, `1,5 GB`.
 * MB dipakai mulai 1 MB, GB mulai 1 GB; satu desimal di bawah 10, koma sebagai pemisah.
 */
export function formatFileSize(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';

    if (bytes >= GB) return `${formatSizeValue(bytes / GB)} GB`;

    if (bytes >= MB) return `${formatSizeValue(bytes / MB)} MB`;

    if (bytes >= KB) return `${Math.round(bytes / KB)} KB`;

    return `${Math.round(bytes)} B`;
}

/** Satu desimal untuk nilai di bawah 10 (`2,3`), tanpa desimal mulai 10 (`12`). */
function formatSizeValue(value: number): string {
    return value >= 10 ? value.toFixed(0) : value.toFixed(1).replace('.', ',');
}

/** Ekstensi huruf kecil tanpa titik (`foto.JPG` → `jpg`); string kosong bila tidak ada. */
export function fileExtension(fileName: string): string {
    const name = fileName.toLowerCase();

    return name.includes('.') ? (name.split('.').pop() ?? '') : '';
}

/** Persentase unggah 0..100 (integer). `total` 0/negatif → 0. */
export function uploadPercent(loaded: number, total: number): number {
    if (!Number.isFinite(loaded) || !Number.isFinite(total) || total <= 0) return 0;

    return Math.min(100, Math.max(0, Math.round((loaded / total) * 100)));
}

/** MIME gambar raster yang aman dirender sebagai `<img>` (SVG sengaja tidak). */
const RASTER_IMAGE_MIMES: ReadonlySet<string> = new Set([
    'image/png',
    'image/jpeg',
    'image/webp',
    'image/gif',
    'image/avif',
]);

const RASTER_IMAGE_EXTENSIONS: ReadonlySet<string> = new Set(['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif']);

/** Gambar yang aman dipratinjau: MIME/ekstensi gambar raster (SVG bukan raster). */
export function isPreviewableImage(mime: string, fileName: string): boolean {
    return RASTER_IMAGE_MIMES.has(mime.toLowerCase()) || RASTER_IMAGE_EXTENSIONS.has(fileExtension(fileName));
}
