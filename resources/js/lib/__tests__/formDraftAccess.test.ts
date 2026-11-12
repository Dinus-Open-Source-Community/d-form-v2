import { describe, expect, it } from 'vitest';
import { resolveDraftAccessDecision, type TDraftAccess } from '@/lib/formDraftAccess';

describe('resolveDraftAccessDecision', () => {
    const cases: Array<[TDraftAccess, { autosaveEnabled: boolean; saveMode: string; status: string }]> = [
        ['available', { autosaveEnabled: true, saveMode: 'publish-draft', status: 'idle' }],
        ['forbidden', { autosaveEnabled: false, saveMode: 'store-form', status: 'idle' }],
        ['retryable-error', { autosaveEnabled: false, saveMode: 'publish-draft', status: 'error' }],
    ];

    for (const [access, expected] of cases) {
        it(`draf ${access} → ${expected.saveMode}`, () => {
            expect(resolveDraftAccessDecision(access)).toEqual(expected);
        });
    }

    it('403 tidak pernah memakai jalur publish draf', () => {
        const decision = resolveDraftAccessDecision('forbidden');

        expect(decision.saveMode).toBe('store-form');
        expect(decision.autosaveEnabled).toBe(false);
    });

    it('hanya draf tersedia yang menyalakan autosave', () => {
        const enabled = (['available', 'forbidden', 'retryable-error'] as TDraftAccess[]).filter(
            (access) => resolveDraftAccessDecision(access).autosaveEnabled
        );

        expect(enabled).toEqual(['available']);
    });
});
