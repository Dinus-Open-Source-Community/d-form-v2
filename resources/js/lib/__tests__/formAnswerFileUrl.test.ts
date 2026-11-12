import { describe, expect, it } from 'vitest';
import { extractFormAnswerFileUrl, extractFormAnswerFileUrls } from '@/lib/formAnswerFileUrl';

describe('extractFormAnswerFileUrl', () => {
    it('recognizes the authenticated answer route with the answer ID before files', () => {
        expect(extractFormAnswerFileUrl('/form-answers/answer-123/files/photo/uploaded-image.png')).toBe(
            '/form-answers/answer-123/files/photo/uploaded-image.png'
        );
    });

    it('extracts the private path from an absolute URL and keeps its query string', () => {
        expect(
            extractFormAnswerFileUrl('https://example.test/form-answers/answer-123/files/document/cv.pdf?download=1')
        ).toBe('/form-answers/answer-123/files/document/cv.pdf?download=1');
    });

    it('rejects paths that do not match the private route structure', () => {
        expect(extractFormAnswerFileUrl('/admin/form-answers/files/answer-123/cv.pdf')).toBeNull();
        expect(extractFormAnswerFileUrl('/form-answers/answer-123/files/photo')).toBeNull();
    });

    it('expands an array into individually authorized private URLs', () => {
        expect(
            extractFormAnswerFileUrls([
                '/form-answers/answer-123/files/photo/first.png',
                '/form-answers/answer-123/files/photo/second.png?download=1',
                'form-uploads/form-123/should-not-leak.png',
            ])
        ).toEqual([
            '/form-answers/answer-123/files/photo/first.png',
            '/form-answers/answer-123/files/photo/second.png?download=1',
        ]);
    });
});
