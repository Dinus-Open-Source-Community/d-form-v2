/** Shared upload limits and supported content formats for client-side preflight. */
export const MAX_UPLOAD_BYTES = 5 * 1024 * 1024
export const ACCEPTED_UPLOAD_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'] as const
export const ACCEPTED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'] as const
export const ACCEPTED_CSV_EXTENSIONS = ['csv', 'txt'] as const

/** Checks the byte signature and decodes images before a file is sent to the server. */
export async function validateUploadFile(
    file: File,
    formats: readonly string[] = [...ACCEPTED_UPLOAD_EXTENSIONS, ...ACCEPTED_CSV_EXTENSIONS],
): Promise<string | null> {
    if (file.size > MAX_UPLOAD_BYTES) return 'Ukuran file maksimal 5 MB.'
    const extension = file.name.split('.').pop()?.toLowerCase()
    if (!extension || !formats.some((allowed) => allowed === extension)) return 'Format file tidak didukung.'
    const bytes = new Uint8Array(await file.slice(0, 12).arrayBuffer())
    if (extension === 'csv' || extension === 'txt') {
        const text = await file.text()
        return text.includes('\u0000') ? 'Isi file teks tidak valid.' : null
    }
    if (extension === 'pdf') {
        return new TextDecoder().decode(bytes.slice(0, 5)) === '%PDF-' ? null : 'Isi file PDF tidak valid.'
    }
    const signatures: Record<string, boolean> = {
        jpg: bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff,
        jpeg: bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff,
        png: bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47,
        gif: new TextDecoder().decode(bytes.slice(0, 3)) === 'GIF',
        webp: new TextDecoder().decode(bytes.slice(0, 4)) === 'RIFF' && new TextDecoder().decode(bytes.slice(8, 12)) === 'WEBP',
    }
    if (!signatures[extension]) return 'Isi file tidak cocok dengan format gambar.'
    try {
        const bitmap = await createImageBitmap(file)
        bitmap.close()
        return null
    } catch {
        return 'Gambar tidak dapat dibaca.'
    }
}
