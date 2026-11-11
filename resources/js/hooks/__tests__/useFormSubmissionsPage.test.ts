import { describe, expect, it } from 'vitest';
import { makeFormField } from '@/lib/__tests__/formFillFieldFixtures';
import { useFormSubmissionsPage } from '@/hooks/useFormSubmissionsPage';

const FIELDS = [
    makeFormField({ name: 'name', label: 'Name', order: 0 }),
    makeFormField({ name: 'email', label: 'Email', order: 1 }),
    makeFormField({ name: 'city', label: 'City', order: 2 }),
];

const SUBMISSION: IFormSubmission = {
    id: 'submission-1',
    user: null,
    answers: { phone: '0812', name: 'Ada' },
    submitted_at: '2026-11-11T10:00:00+07:00',
};

const GROUP: IBundleSubmissionGroup = {
    group_token: 'group-1',
    leader: {
        ...SUBMISSION,
        id: 'leader-1',
        answers: { shirt: 'L' },
        can_open_detail: true,
        can_review: true,
    },
    members: [
        {
            ...SUBMISSION,
            id: 'member-1',
            answers: { food: 'veg' },
            can_open_detail: true,
            can_review: true,
        },
    ],
    total_participants: 2,
    accepted_count: 0,
    pending_count: 2,
    rejected_count: 0,
    expired_count: 0,
    group_review_status: 'pending',
    submitted_at: '2026-11-11T10:00:00+07:00',
};

describe('useFormSubmissionsPage answer keys', () => {
    it('previews the first four keys in builder-then-discovery order', () => {
        const page = useFormSubmissionsPage({
            event: { id: 'event-1', title: 'Event' },
            form: { id: 'form-1', title: 'Form' },
            fields: FIELDS,
            submissions: { current_page: 1, last_page: 1, per_page: 10, total: 1, data: [SUBMISSION] },
            bundleGroups: { current_page: 1, last_page: 1, per_page: 10, total: 1, data: [GROUP] },
        });

        expect(page.answerKeys.value).toEqual(['name', 'email', 'city', 'phone']);
        expect(page.allAnswerKeys.value).toEqual(['name', 'email', 'city', 'phone', 'shirt', 'food']);
    });

    it('keeps the preview a strict prefix of the full key list', () => {
        const page = useFormSubmissionsPage({
            event: { id: 'event-1', title: 'Event' },
            form: { id: 'form-1', title: 'Form' },
            fields: FIELDS,
            submissions: { current_page: 1, last_page: 1, per_page: 10, total: 1, data: [SUBMISSION] },
            bundleGroups: { current_page: 1, last_page: 1, per_page: 10, total: 1, data: [GROUP] },
        });

        expect(page.allAnswerKeys.value.slice(0, page.answerKeys.value.length)).toEqual(page.answerKeys.value);
    });

    it('works without submissions or bundle groups', () => {
        const page = useFormSubmissionsPage({
            event: { id: 'event-1', title: 'Event' },
            form: { id: 'form-1', title: 'Form' },
            fields: FIELDS,
        });

        expect(page.answerKeys.value).toEqual(['name', 'email', 'city']);
        expect(page.allAnswerKeys.value).toEqual(['name', 'email', 'city']);
    });
});
