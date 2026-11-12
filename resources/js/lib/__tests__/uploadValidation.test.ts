import { afterEach, describe, expect, it, vi } from 'vitest';
import { MAX_UPLOAD_BYTES, validateUploadFile } from '@/lib/uploadValidation';

function fileFromBytes(bytes: readonly number[], name: string): File {
    return new File([new Uint8Array(bytes)], name);
}

function stubDecodableImage(): ReturnType<typeof vi.fn> {
    const close = vi.fn();
    vi.stubGlobal(
        'createImageBitmap',
        vi.fn(async () => ({ close }))
    );
    return close;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('validateUploadFile', () => {
    it('rejects a file larger than the shared limit', async () => {
        const oversized = new File([new Uint8Array(MAX_UPLOAD_BYTES + 1)], 'big.png');

        await expect(validateUploadFile(oversized)).resolves.toBe('Ukuran file maksimal 5 MB.');
    });

    it('accepts a file exactly at the shared limit when the rest is valid', async () => {
        stubDecodableImage();
        const boundary = new Uint8Array(MAX_UPLOAD_BYTES);
        boundary.set([0x89, 0x50, 0x4e, 0x47], 0);

        await expect(validateUploadFile(new File([boundary], 'boundary.png'))).resolves.toBeNull();
    });

    it('rejects unsupported and missing extensions', async () => {
        await expect(validateUploadFile(fileFromBytes([1, 2, 3], 'notes.xyz'))).resolves.toBe(
            'Format file tidak didukung.'
        );
        await expect(validateUploadFile(fileFromBytes([1, 2, 3], 'README'))).resolves.toBe(
            'Format file tidak didukung.'
        );
    });

    it('accepts clean CSV/TXT content and rejects NUL bytes', async () => {
        await expect(validateUploadFile(new File(['a,b\n1,2'], 'data.csv'))).resolves.toBeNull();
        await expect(validateUploadFile(new File(['ok text'], 'notes.txt'))).resolves.toBeNull();
        await expect(validateUploadFile(new File(['a\u0000b'], 'bad.txt'))).resolves.toBe('Isi file teks tidak valid.');
    });

    it('checks the PDF magic signature', async () => {
        const pdfBytes = new TextEncoder().encode('%PDF-1.4');

        await expect(validateUploadFile(new File([pdfBytes], 'doc.pdf'))).resolves.toBeNull();
        await expect(validateUploadFile(new File(['not a pdf'], 'fake.pdf'))).resolves.toBe(
            'Isi file PDF tidak valid.'
        );
    });

    it.each([
        ['image.jpg', [0xff, 0xd8, 0xff, 0x00]],
        ['image.jpeg', [0xff, 0xd8, 0xff, 0x00]],
        ['image.png', [0x89, 0x50, 0x4e, 0x47, 0x00]],
        ['image.gif', [0x47, 0x49, 0x46, 0x00]],
        ['image.webp', [0x52, 0x49, 0x46, 0x46, 0, 0, 0, 0, 0x57, 0x45, 0x42, 0x50]],
    ])('accepts a decodable %s with the right signature', async (name: string, bytes: number[]) => {
        stubDecodableImage();

        await expect(validateUploadFile(fileFromBytes(bytes, name))).resolves.toBeNull();
    });

    it('rejects an image whose bytes do not match its extension', async () => {
        stubDecodableImage();

        await expect(validateUploadFile(fileFromBytes([0x00, 0x00, 0x00], 'image.png'))).resolves.toBe(
            'Isi file tidak cocok dengan format gambar.'
        );
    });

    it('rejects an image the browser cannot decode and closes a successful bitmap', async () => {
        vi.stubGlobal(
            'createImageBitmap',
            vi.fn(async () => {
                throw new Error('decode failed');
            })
        );
        await expect(validateUploadFile(fileFromBytes([0x89, 0x50, 0x4e, 0x47], 'broken.png'))).resolves.toBe(
            'Gambar tidak dapat dibaca.'
        );

        const close = stubDecodableImage();
        await expect(validateUploadFile(fileFromBytes([0x89, 0x50, 0x4e, 0x47], 'ok.png'))).resolves.toBeNull();
        expect(close).toHaveBeenCalledOnce();
    });

    it('honors a caller-supplied format whitelist', async () => {
        await expect(validateUploadFile(fileFromBytes([0x89, 0x50, 0x4e, 0x47], 'image.png'), ['csv'])).resolves.toBe(
            'Format file tidak didukung.'
        );
        await expect(validateUploadFile(new File(['a,b'], 'data.csv'), ['csv'])).resolves.toBeNull();
    });

    it('honors MIME allowlists from field metadata', async () => {
        const pdfBytes = new TextEncoder().encode('%PDF-1.4');
        const pdf = new File([pdfBytes], 'document.pdf', { type: 'application/pdf' });

        await expect(validateUploadFile(pdf, ['application/pdf'])).resolves.toBeNull();
        await expect(validateUploadFile(pdf, ['image/*'])).resolves.toBe('Format file tidak didukung.');
    });

    it.each([
        ['document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ['workbook.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ['slides.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    ])('accepts allowlisted Office document %s without image decoding', async (name, mime) => {
        const officeFile = new File([new Uint8Array([0x50, 0x4b, 0x03, 0x04])], name, { type: mime });
        const decodeMock = vi.fn();
        vi.stubGlobal('createImageBitmap', decodeMock);

        await expect(validateUploadFile(officeFile, [mime])).resolves.toBeNull();
        expect(decodeMock).not.toHaveBeenCalled();
    });

    it.each([
        ['document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', ''],
        [
            'workbook.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/octet-stream',
        ],
        ['slides.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    ])('uses Office extension mapping for %s when MIME is empty or generic', async (name, allowedMime, fileMime) => {
        const officeFile = new File([new Uint8Array([0x50, 0x4b, 0x03, 0x04])], name, { type: fileMime });

        await expect(validateUploadFile(officeFile, [allowedMime])).resolves.toBeNull();
    });

    it('does not use Office MIME fallbacks for unsupported extensions or generic allowlists', async () => {
        const genericOfficeMime = new File(['zip bytes'], 'archive.zip', { type: 'application/octet-stream' });
        const genericMimeAllowlist = new File(['bytes'], 'payload.exe', { type: 'application/octet-stream' });
        const officeMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        await expect(validateUploadFile(genericOfficeMime, [officeMime])).resolves.toBe('Format file tidak didukung.');
        await expect(validateUploadFile(genericMimeAllowlist, ['application/octet-stream'])).resolves.toBe(
            'Format file tidak didukung.'
        );
    });
});
