import { describe, expect, it } from 'vitest';
import { parseOprecDraft, serializeOprecDraft } from '@/lib/oprecDraft';

describe('oprecDraft', () => {
    it('FE-AUDIT-006-F3 round-trips mixed legacy JSON values', () => {
        const draft = {
            step: 2,
            values: { name: 'Ada', semester: 3, consent: true, divisions: ['media', 'data'] },
        };

        expect(parseOprecDraft(serializeOprecDraft(draft))).toEqual(draft);
    });

    it('FE-AUDIT-006-F3 keeps the legacy draft key shape readable', () => {
        expect(parseOprecDraft('{"values":{"semester":3,"consent":false,"divisions":["a"]}}')).toEqual({
            step: undefined,
            values: { semester: 3, consent: false, divisions: ['a'] },
        });
    });

    it('rejects malformed draft JSON without throwing', () => {
        expect(parseOprecDraft('{"values":null}')).toBeNull();
    });
});
