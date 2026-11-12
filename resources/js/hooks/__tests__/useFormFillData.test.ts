import { describe, expect, it } from 'vitest';
import { makeFormField } from '@/lib/__tests__/formFillFieldFixtures';
import { useFormFillData } from '@/hooks/useFormFillData';

const EVENT = { id: 'event-1', slug: 'event-1', title: 'Event 1' };

function createData(overrides: Partial<Parameters<typeof useFormFillData>[0]> = {}) {
    return useFormFillData({
        event: EVENT,
        form: {
            id: 'form-1',
            title: 'Registration',
            description: '  Fill me  ',
            closed_at: null,
            banner_url: null,
            banner_caption: null,
        },
        fields: [
            makeFormField({ name: 'full_name', type: 'input' }),
            makeFormField({ name: 'topics', type: 'checkbox', metadata: { optionChoices: ['a', 'b'] } }),
        ],
        accessStatus: 'allowed',
        accessMessage: '',
        memberSlots: 0,
        registrationMode: 'single',
        ...overrides,
    });
}

describe('useFormFillData', () => {
    it('seeds the answer form from the field types', () => {
        const data = createData();

        expect(data.answerForm.full_name).toBe('');
        expect(data.answerForm.topics).toEqual([]);
    });

    it('derives banner, description, and access flags', () => {
        const data = createData();

        expect(data.formHasDescription.value).toBe(true);
        expect(data.formBannerImageSrc.value).toBe('');
        expect(data.formBannerCaption.value).toBe('');
        expect(data.isBlocked.value).toBe(false);
        expect(data.blockCopy.value).toEqual({ title: '', body: 'This form is not available right now.' });
    });

    it('uses the synthetic banner field before the form banner fallback', () => {
        const data = createData({
            fields: [
                makeFormField({
                    name: 'form_banner',
                    type: 'banner',
                    metadata: { formBanner: true, bannerUrl: 'events/banners/x.png', content: 'Caption' },
                }),
            ],
            form: {
                id: 'form-1',
                title: 'Registration',
                description: null,
                closed_at: null,
                banner_url: 'events/banners/fallback.png',
                banner_caption: 'Fallback caption',
            },
        });

        expect(data.formBannerImageSrc.value).toBe('/storage/events/banners/x.png');
        expect(data.formBannerCaption.value).toBe('Caption');
        expect(data.formHasDescription.value).toBe(false);
    });

    it('blocks the form and carries the access message', () => {
        const data = createData({ accessStatus: 'quota_full', accessMessage: 'Quota reached.' });

        expect(data.isBlocked.value).toBe(true);
        expect(data.blockCopy.value).toEqual({ title: 'Registration is full.', body: 'Quota reached.' });
    });

    it('writes answers and team member emails through the form', () => {
        const data = createData({ memberSlots: 2, registrationMode: 'team' });

        data.setAnswer('full_name', 'Ada');
        data.setTeamMemberEmails(['a@example.com', 'b@example.com']);

        expect(data.answerForm.full_name).toBe('Ada');
        expect(data.answerForm.team_member_emails).toEqual(['a@example.com', 'b@example.com']);
    });

    it('toggles checkbox answers and resolves the selected option row', () => {
        const data = createData();

        data.onCheckboxToggle('topics', 'a', true);
        data.onCheckboxToggle('topics', 'b', true);
        data.onCheckboxToggle('topics', 'a', false);

        expect(data.answerForm.topics).toEqual(['b']);
        expect(data.isCheckboxSelected('topics', 'b')).toBe(true);

        data.setAnswer('topics', 'a');
        expect(
            data.getSelectedOptionRow(makeFormField({ name: 'topics', metadata: { optionChoices: ['a'] } }))
        ).toEqual({ type: 'text', label: 'a' });
    });

    it('only reports a file name for file answers', () => {
        const data = createData();

        expect(data.fileAnswerName('full_name')).toBe('');

        const file = new File(['content'], 'cv.pdf', { type: 'application/pdf' });
        data.setAnswer('full_name', file);
        expect(data.fileAnswerName('full_name')).toBe('cv.pdf');
    });
});
