import { describe, expect, it } from 'vitest';

import { duIssuanceBadge } from '@/lib/duSubmissionUi';
import type { IDuIssuance, IDuMapping, IDuSubmission } from '@/types/du';

const MAPPING: IDuMapping = { course_uid: 'course-1', course_title: 'Course', is_active: true };

function submission(overrides: Partial<IDuSubmission> = {}): IDuSubmission {
    return {
        enabled: true,
        class_field: null,
        class_value: 'Kelas A',
        mapping: null,
        suggestion: null,
        issuance: null,
        can_issue: false,
        ...overrides,
    };
}

function issuance(overrides: Partial<IDuIssuance> = {}): IDuIssuance {
    return { status: 'issued', code: 'DU-123', message: null, ...overrides };
}

describe('duIssuanceBadge', () => {
    it('returns null when the submission has no DU data', () => {
        expect(duIssuanceBadge(null)).toBeNull();
    });

    it('returns null for issued issuance because the code is the signal', () => {
        expect(duIssuanceBadge(submission({ mapping: MAPPING, issuance: issuance() }))).toBeNull();
    });

    it('maps failed issuance to destructive badge', () => {
        expect(duIssuanceBadge(submission({ issuance: issuance({ status: 'failed', code: null }) }))).toEqual({
            label: 'Gagal',
            className: 'border-destructive/35 text-destructive',
        });
    });

    it('maps unknown issuance to amber badge', () => {
        expect(duIssuanceBadge(submission({ issuance: issuance({ status: 'unknown', code: null }) }))).toEqual({
            label: 'Menunggu DU',
            className: 'border-amber-500/40 text-amber-700 dark:text-amber-300',
        });
    });

    it('maps pending issuance to primary badge', () => {
        expect(duIssuanceBadge(submission({ issuance: issuance({ status: 'pending', code: null }) }))).toEqual({
            label: 'Diproses',
            className: 'border-primary/30 text-primary',
        });
    });

    it('shows not-yet-issued when mapping exists without issuance', () => {
        expect(duIssuanceBadge(submission({ mapping: MAPPING }))).toEqual({
            label: 'Belum terbit',
            className: 'border-primary/30 text-primary',
        });
    });

    it('shows not-linked when neither mapping nor issuance exists', () => {
        expect(duIssuanceBadge(submission())).toEqual({
            label: 'Belum terhubung',
            className: 'border-amber-500/40 text-amber-700 dark:text-amber-300',
        });
    });
});
