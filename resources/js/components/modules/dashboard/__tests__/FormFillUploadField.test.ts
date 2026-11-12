import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { useForm } from '@inertiajs/vue3';
import FormFillUploadField from '@/components/modules/dashboard/FormFillUploadField.vue';
import { useFormFillUpload } from '@/hooks/useFormFillUpload';
import type { TFormFillAnswerMap } from '@/types/form';
import type { TUploadRowState } from '@/lib/fileUploadRow';

async function renderField(
    image: boolean,
    options: {
        rowState?: TUploadRowState;
        fieldError?: string;
        rowStates?: readonly TUploadRowState[];
        rowErrorMessages?: readonly string[];
        maxFiles?: number;
        maxTotalSizeKb?: number;
    } = {}
): Promise<string> {
    const files = [
        new File(['first'], 'first.png', { type: 'image/png', lastModified: 1 }),
        new File(['second'], 'second.png', { type: 'image/png', lastModified: 2 }),
    ];

    return renderToString(
        createSSRApp({
            render: () =>
                h(FormFillUploadField, {
                    files,
                    previewUrls: ['blob:first', 'blob:second'],
                    image,
                    accept: 'image/png',
                    rowState: options.rowState ?? 'completed',
                    fieldError: options.fieldError ?? '',
                    rowStates: options.rowStates ?? [],
                    rowErrorMessages: options.rowErrorMessages ?? [],
                    maxFiles: options.maxFiles ?? 5,
                    maxTotalSizeKb: options.maxTotalSizeKb ?? 20 * 1024,
                }),
        })
    );
}

describe('FormFillUploadField', () => {
    it('renders multiple image previews vertically with named remove controls and bounded width', async () => {
        const html = await renderField(true);

        expect(html.match(/<figure/g)).toHaveLength(2);
        expect(html).toContain('alt="first.png"');
        expect(html).toContain('alt="second.png"');
        expect(html).toContain('aria-label="Hapus first.png"');
        expect(html).toContain('aria-label="Hapus second.png"');
        expect(html).toContain('min-w-0');
        expect(html).not.toContain('overflow-x');
        expect(html).toContain('Pilih hingga 5 berkas, maksimal 20 MB total.');
    });

    it('renders each document through the shared file-row component and provides a labeled multi-file input', async () => {
        const html = await renderField(false);

        expect(html).toContain('aria-label="Hapus first.png"');
        expect(html).toContain('aria-label="Hapus second.png"');
        expect(html).toContain('type="file"');
        expect(html).toContain('multiple');
        expect(html).toContain('aria-label="Tambah berkas"');
        expect(html).toContain('focus-within:ring');
    });

    it('connects upload errors to the native file input', async () => {
        const html = await renderField(false, { fieldError: 'Format file tidak didukung.' });
        const inputDescription = html.match(/aria-describedby="([^"]+)"/)?.[1];

        expect(inputDescription).toBeDefined();
        expect(html).toContain(`id="${inputDescription}"`);
        expect(html).toContain('role="alert"');
        expect(html).toContain('Format file tidak didukung.');
    });

    it('keeps retained document rows completed when the new selection has a field-level error', async () => {
        const html = await renderField(false, {
            rowState: 'completed',
            fieldError: 'Pilih maksimal 2 berkas.',
            maxFiles: 2,
            maxTotalSizeKb: 512,
        });

        expect(html).toContain('aria-label="Hapus first.png"');
        expect(html).toContain('aria-label="Hapus second.png"');
        expect(html).toContain('Selesai');
        expect(html).not.toContain('Gagal');
        expect(html).toContain('Pilih maksimal 2 berkas.');
        expect(html).toContain('Pilih hingga 2 berkas, maksimal 512 KB total.');
    });

    it('shows an indexed server error on its matching file row only', async () => {
        const html = await renderField(false, {
            rowState: 'completed',
            rowStates: ['error', 'completed'],
            rowErrorMessages: ['File DOCX tidak valid.', ''],
        });

        expect(html).toContain('File DOCX tidak valid.');
        expect(html.match(/Gagal/g)).toHaveLength(1);
        expect(html.match(/Selesai/g)).toHaveLength(1);
    });

    it('shows an indexed server error on one image thumbnail without failing its neighbor', async () => {
        const html = await renderField(true, {
            rowState: 'completed',
            rowStates: ['error', 'completed'],
            rowErrorMessages: ['Gambar tidak dapat dibaca.', ''],
        });

        expect(html.match(/<figure/g)).toHaveLength(2);
        expect(html).toContain('alt="first.png"');
        expect(html).toContain('alt="second.png"');
        expect(html).toContain('Gambar tidak dapat dibaca.');
        expect(html.match(/role="alert"/g)).toHaveLength(1);
        expect(html.match(/Gambar tidak dapat dibaca\./g)).toHaveLength(1);
        expect(html).not.toContain('Gagal mengunggah');
        expect(html).toContain('aria-label="Hapus first.png"');
        expect(html).toContain('aria-label="Hapus second.png"');
    });

    it('rerenders the healthy row after removing a failed row without shifting its error', async () => {
        const failedFile = new File(['failed'], 'failed.pdf', { type: 'application/pdf' });
        const healthyFile = new File(['healthy'], 'healthy.pdf', { type: 'application/pdf' });
        const html = await renderToString(
            createSSRApp({
                setup() {
                    const answerForm = useForm<TFormFillAnswerMap>({ cv: null });
                    const upload = useFormFillUpload(answerForm);
                    answerForm.cv = [failedFile, healthyFile];
                    answerForm.setError('cv.0', 'Berkas gagal divalidasi.');
                    upload.removeFileUpload('cv', 0);

                    return () => {
                        const files = Array.isArray(answerForm.cv)
                            ? answerForm.cv.filter((value): value is File => value instanceof File)
                            : [];
                        const messages = files.map((_file, index) => answerForm.errors[`cv.${index}`] ?? '');
                        const rowStates = messages.map((message) => (message ? 'error' : 'completed'));

                        return h(FormFillUploadField, {
                            files,
                            previewUrls: ['blob:healthy'],
                            image: false,
                            accept: 'application/pdf',
                            rowState: 'completed',
                            rowStates,
                            rowErrorMessages: messages,
                        });
                    };
                },
            })
        );

        expect(html).toContain('healthy.pdf');
        expect(html).toContain('Selesai');
        expect(html).toContain('aria-label="Hapus healthy.pdf"');
        expect(html).not.toContain('Gagal');
        expect(html).not.toContain('Berkas gagal divalidasi.');
    });
});
