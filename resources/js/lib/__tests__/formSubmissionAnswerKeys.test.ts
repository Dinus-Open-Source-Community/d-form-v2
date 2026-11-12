import { describe, expect, it } from 'vitest';
import { collectSubmissionAnswerKeys, SUBMISSION_ANSWER_KEY_PREVIEW_LIMIT } from '@/lib/formSubmissionAnswerKeys';

describe('collectSubmissionAnswerKeys', () => {
    it('keeps builder order first, then discovered keys in first-seen order', () => {
        const keys = collectSubmissionAnswerKeys({
            fieldNames: ['name', 'email'],
            submissions: [
                { answers: { email: 'a', phone: '1', name: 'x' } },
                { answers: { city: 'Bandung', phone: '2' } },
            ],
        });

        expect(keys).toEqual(['name', 'email', 'phone', 'city']);
    });

    it('appends bundle leader then member keys', () => {
        const keys = collectSubmissionAnswerKeys({
            fieldNames: ['name'],
            groups: [
                {
                    leader: { answers: { name: 'lead', shirt: 'L' } },
                    members: [{ answers: { shirt: 'M' } }, { answers: { food: 'veg' } }],
                },
            ],
        });

        expect(keys).toEqual(['name', 'shirt', 'food']);
    });

    it('produces the same order for the preview and the full list', () => {
        const input = {
            fieldNames: ['a', 'b'],
            submissions: [{ answers: { c: 1, d: 2 } }, { answers: { e: 3 } }],
        };

        const full = collectSubmissionAnswerKeys(input);
        const preview = collectSubmissionAnswerKeys({ ...input, limit: SUBMISSION_ANSWER_KEY_PREVIEW_LIMIT });

        expect(preview).toEqual(full.slice(0, SUBMISSION_ANSWER_KEY_PREVIEW_LIMIT));
        expect(preview).toEqual(['a', 'b', 'c', 'd']);
        expect(full).toEqual(['a', 'b', 'c', 'd', 'e']);
    });

    it('tolerates missing answers bags', () => {
        expect(
            collectSubmissionAnswerKeys({
                fieldNames: ['a'],
                submissions: [{}, { answers: null }],
                groups: [{ leader: {}, members: [{}] }],
            })
        ).toEqual(['a']);
    });

    it('returns an empty list when nothing is defined', () => {
        expect(collectSubmissionAnswerKeys({ fieldNames: [] })).toEqual([]);
    });
});
