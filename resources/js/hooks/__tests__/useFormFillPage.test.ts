import { describe, expect, it, vi } from 'vitest';
import { makeFormField } from '@/lib/__tests__/formFillFieldFixtures';
import { useFormFillPage } from '@/hooks/useFormFillPage';

function createPage(overrides: Partial<Parameters<typeof useFormFillPage>[0]> = {}) {
    return useFormFillPage({
        event: { id: 'event-1', slug: 'event-1', title: 'Event 1' },
        form: {
            id: 'form-1',
            title: 'Registration',
            description: 'Join us',
            closed_at: null,
            banner_url: null,
            banner_caption: null,
        },
        fields: [
            makeFormField({ name: 'full_name', metadata: { rules: { required: true } } }),
            makeFormField({ name: 'shirt', metadata: { duplicatable: true } }),
        ],
        submitUrl: '/joined/events/1/forms/1/fill',
        accessStatus: 'allowed',
        accessMessage: '',
        memberSlots: 1,
        registrationMode: 'bundle',
        ...overrides,
    });
}

describe('useFormFillPage facade', () => {
    it('exposes the contract consumed by the fill page and its components', () => {
        const page = createPage();

        for (const key of [
            'answerForm',
            'metadata',
            'builderType',
            'registrationMode',
            'formBannerImageSrc',
            'formBannerCaption',
            'formHasDescription',
            'isBlocked',
            'blockCopy',
            'memberSlots',
            'getOptionRows',
            'getSelectedOptionRow',
            'isBundleDuplicatableField',
            'participationSlotsForField',
            'answerKeyForSlot',
            'getInputSubtype',
            'getPlaceholder',
            'isRequired',
            'isMultipleSelect',
            'isRadioLike',
            'isCheckboxLike',
            'maxLengthForField',
            'fileHint',
            'acceptValue',
            'onCheckboxToggle',
            'isCheckboxSelected',
            'onFileChange',
            'applyFileSelection',
            'clearFileUpload',
            'setAnswer',
            'setTeamMemberEmails',
            'validateSection',
            'filePreviewUrls',
            'fileAnswerName',
            'submit',
            'submitProgress',
            'cancelSubmit',
            'fieldError',
            'cardErrorsForFields',
            'cardErrorsForBundleSlot',
        ]) {
            expect(page).toHaveProperty(key);
        }
    });

    it('keeps pure field helpers on the context', () => {
        const page = createPage();
        const field = makeFormField({
            name: 'photo',
            type: 'image',
            metadata: { imageUrl: 'form-images/1/a.png' },
        });

        expect(page.metadata(field).imageUrl).toBe('form-images/1/a.png');
        expect(page.builderType(field)).toBe('image');
        expect(page.isRequired(makeFormField({ name: 'a', metadata: { rules: { required: true } } }))).toBe(true);
        expect(page.answerKeyForSlot(makeFormField({ name: 'shirt' }), 0)).toBe('bundle__shirt__0');
    });

    it('seeds bundle answers for the lead and each participant', () => {
        const page = createPage();

        expect(page.answerForm.shirt).toBe('');
        expect(page.answerForm['bundle__shirt__0']).toBe('');
        expect(page.answerForm.team_member_emails).toEqual(['']);
        expect(page.memberSlots.value).toBe(1);
    });

    it('offers one participation slot per bundle participant', () => {
        const page = createPage();
        const field = makeFormField({ name: 'shirt', metadata: { duplicatable: true } });

        expect(page.isBundleDuplicatableField(field)).toBe(true);
        expect(page.participationSlotsForField(field)).toEqual([
            { slotIndex: null, title: 'Lead participant' },
            { slotIndex: 0, title: 'Participant 2' },
        ]);
    });

    it('does not submit while the access guard blocks the form', () => {
        const page = createPage({ accessStatus: 'form_closed', accessMessage: 'Closed.' });
        const postSpy = vi.spyOn(page.answerForm, 'post').mockImplementation(() => {});

        page.submit();

        expect(page.isBlocked.value).toBe(true);
        expect(postSpy).not.toHaveBeenCalled();
        expect(page.blockCopy.value.title).toBe('Registration is closed.');
    });

    it('validates required answers through the facade', () => {
        const page = createPage();
        const field = makeFormField({ name: 'full_name', metadata: { rules: { required: true } } });

        expect(page.validateSection([field])).toBe(false);
        expect(page.fieldError('full_name')).toBeTruthy();

        page.setAnswer('full_name', 'Ada');
        expect(page.validateSection([field])).toBe(true);
    });
});
