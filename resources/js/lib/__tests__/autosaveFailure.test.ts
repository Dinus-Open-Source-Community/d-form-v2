import { describe, expect, it } from 'vitest';
import { classifyAutosaveFailure, type TAutosaveFailureAction, type TAutosaveFailureKind } from '@/lib/autosaveFailure';

describe('classifyAutosaveFailure', () => {
    const cases: Array<{
        label: string;
        status: number | null;
        body?: unknown;
        kind: TAutosaveFailureKind;
        action: TAutosaveFailureAction;
        contains: string;
    }> = [
        {
            label: '404 target hilang',
            status: 404,
            body: { message: 'No query results for model [App\\Models\\Event].' },
            kind: 'terminal',
            action: 'reload',
            contains: 'sudah tidak tersedia',
        },
        {
            label: '410 target hilang',
            status: 410,
            kind: 'terminal',
            action: 'reload',
            contains: 'sudah tidak tersedia',
        },
        {
            label: '419 sesi habis',
            status: 419,
            kind: 'terminal',
            action: 'reload',
            contains: 'Sesi Anda berakhir',
        },
        {
            label: '403 akses dicabut',
            status: 403,
            kind: 'terminal',
            action: 'none',
            contains: 'tidak lagi punya akses',
        },
        {
            label: '422 validasi',
            status: 422,
            body: { message: 'The given data was invalid.', errors: { title: ['Please enter the form title.'] } },
            kind: 'retryable',
            action: 'retry',
            contains: 'Judul form wajib diisi.',
        },
        {
            label: '500 server',
            status: 500,
            body: { message: 'Server Error' },
            kind: 'retryable',
            action: 'retry',
            contains: 'Server Error',
        },
        {
            label: 'network (tanpa status)',
            status: null,
            kind: 'retryable',
            action: 'retry',
            contains: 'Gagal menyimpan otomatis',
        },
    ];

    for (const testCase of cases) {
        it(`${testCase.label} → ${testCase.kind}/${testCase.action}`, () => {
            const decision = classifyAutosaveFailure(testCase.status, testCase.body);

            expect(decision.kind).toBe(testCase.kind);
            expect(decision.action).toBe(testCase.action);
            expect(decision.message).toContain(testCase.contains);
        });
    }

    it('tidak pernah menampilkan JSON atau ringkasan Laravel', () => {
        const decision = classifyAutosaveFailure(422, {
            message: 'Please enter the form title. (and 2 more errors)',
            errors: { title: ['Please enter the form title.'], description: ['x'] },
        });

        expect(decision.message).toBe('Judul form wajib diisi.');
        expect(decision.message.includes('(and ')).toBe(false);
    });

    it('memakai pesan terminal yang tidak bisa di-retry untuk 404', () => {
        const decision = classifyAutosaveFailure(404, {
            message: 'No query results for model [App\\Models\\Event].',
            exception: 'NotFoundHttpException',
        });

        expect(decision.action).toBe('reload');
        expect(decision.message).not.toContain('No query results');
        expect(decision.message).not.toContain('{');
    });
});
