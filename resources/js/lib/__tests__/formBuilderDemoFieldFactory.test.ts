import { describe, expect, it } from 'vitest';
import { createDemoBuilderField } from '@/lib/formBuilderDemoFieldFactory';
import { formatDefaultUploadFormats } from '@/lib/formUploadFormats';

describe('createDemoBuilderField upload defaults', () => {
    it('uses the canonical supported formats instead of stale office extensions', () => {
        const field = createDemoBuilderField('file_upload', 'Documents');

        expect(field.metadata.accepts).toBe(formatDefaultUploadFormats('file_upload'));
        expect(field.metadata.accepts).not.toBe('pdf, doc, xls');
    });
});
