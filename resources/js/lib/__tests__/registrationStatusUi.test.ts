import { describe, expect, it } from 'vitest';
import {
    BUNDLE_GROUP_REVIEW_STATUS_BADGES,
    FORM_ANSWER_REVIEW_STATUS_BADGES,
    MEMBER_CONFIRMATION_STATUS_BADGES,
    REGISTRANT_STATUS_BADGES,
    bundleGroupReviewStatusBadge,
    formAnswerReviewStatusBadge,
    memberConfirmationStatusBadge,
    registrantStatusBadge,
    registrationRoleLabel,
} from '@/lib/registrationStatusUi';
import type {
    TBundleGroupReviewStatus,
    TFormAnswerReviewStatus,
    TMemberConfirmationStatus,
    TRegistrantStatus,
    TRegistrationRole,
} from '@/types/registrationStatus';

const REVIEW_STATUSES: TFormAnswerReviewStatus[] = ['pending', 'accepted', 'rejected'];
const MEMBER_STATUSES: TMemberConfirmationStatus[] = ['pending', 'accepted', 'rejected', 'expired'];
const GROUP_STATUSES: TBundleGroupReviewStatus[] = ['pending', 'partial', 'accepted', 'rejected'];
const REGISTRANT_STATUSES: TRegistrantStatus[] = ['pending', 'accepted', 'rejected'];
const ROLES: TRegistrationRole[] = ['leader', 'member'];

describe('registrationStatusUi: exhaustive maps', () => {
    it('covers exactly the review status enum', () => {
        expect(Object.keys(FORM_ANSWER_REVIEW_STATUS_BADGES).sort()).toEqual([...REVIEW_STATUSES].sort());
    });

    it('covers exactly the member confirmation enum', () => {
        expect(Object.keys(MEMBER_CONFIRMATION_STATUS_BADGES).sort()).toEqual([...MEMBER_STATUSES].sort());
    });

    it('covers exactly the bundle group review enum', () => {
        expect(Object.keys(BUNDLE_GROUP_REVIEW_STATUS_BADGES).sort()).toEqual([...GROUP_STATUSES].sort());
    });

    it('covers exactly the registrant status enum', () => {
        expect(Object.keys(REGISTRANT_STATUS_BADGES).sort()).toEqual([...REGISTRANT_STATUSES].sort());
    });

    it.each(REVIEW_STATUSES)('review badge for %s has label and classes', (status) => {
        const badge = formAnswerReviewStatusBadge(status);
        expect(badge.label.length).toBeGreaterThan(0);
        expect(badge.class.length).toBeGreaterThan(0);
    });

    it.each(MEMBER_STATUSES)('member badge for %s has label and classes', (status) => {
        const badge = memberConfirmationStatusBadge(status);
        expect(badge.label.length).toBeGreaterThan(0);
        expect(badge.class.length).toBeGreaterThan(0);
    });

    it.each(GROUP_STATUSES)('group badge for %s has label and classes', (status) => {
        const badge = bundleGroupReviewStatusBadge(status);
        expect(badge.label.length).toBeGreaterThan(0);
        expect(badge.class.length).toBeGreaterThan(0);
    });

    it.each(REGISTRANT_STATUSES)('registrant badge for %s has label and classes', (status) => {
        const badge = registrantStatusBadge(status);
        expect(badge.label.length).toBeGreaterThan(0);
        expect(badge.class.length).toBeGreaterThan(0);
    });
});

describe('registrationStatusUi: copy', () => {
    it('uses the shared Indonesian copy for review statuses', () => {
        expect(formAnswerReviewStatusBadge('pending').label).toBe('Menunggu review');
        expect(formAnswerReviewStatusBadge('accepted').label).toBe('Diterima');
        expect(formAnswerReviewStatusBadge('rejected').label).toBe('Ditolak');
    });

    it('treats a missing review/member status as pending', () => {
        expect(formAnswerReviewStatusBadge(null)).toEqual(FORM_ANSWER_REVIEW_STATUS_BADGES.pending);
        expect(formAnswerReviewStatusBadge(undefined)).toEqual(FORM_ANSWER_REVIEW_STATUS_BADGES.pending);
        expect(memberConfirmationStatusBadge(null)).toEqual(MEMBER_CONFIRMATION_STATUS_BADGES.pending);
    });

    it('labels every bundle group status', () => {
        expect(bundleGroupReviewStatusBadge('pending').label).toBe('Menunggu review');
        expect(bundleGroupReviewStatusBadge('partial').label).toBe('Sebagian direview');
        expect(bundleGroupReviewStatusBadge('accepted').label).toBe('Semua diterima');
        expect(bundleGroupReviewStatusBadge('rejected').label).toBe('Ditolak');
    });

    it('labels every registrant status', () => {
        expect(registrantStatusBadge('pending').label).toBe('Menunggu');
        expect(registrantStatusBadge('accepted').label).toBe('Disetujui');
        expect(registrantStatusBadge('rejected').label).toBe('Ditolak');
    });

    it.each(ROLES)('labels role %s', (role) => {
        expect(registrationRoleLabel(role).length).toBeGreaterThan(0);
    });

    it('falls back to a dash for a missing role', () => {
        expect(registrationRoleLabel(null)).toBe('—');
        expect(registrationRoleLabel(undefined)).toBe('—');
    });
});
