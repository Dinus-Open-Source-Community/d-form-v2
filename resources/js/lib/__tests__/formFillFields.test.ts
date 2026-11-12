import { describe, expect, it } from 'vitest';
import { makeFormField } from '@/lib/__tests__/formFillFieldFixtures';
import {
    buildBundleAnswerKey,
    containsAccountEmail,
    createFormFillInitialValues,
    formFieldAcceptAttribute,
    formFieldAnswerKey,
    formFieldAnswerKeys,
    formFieldFileHint,
    formFieldImageSrc,
    formFieldInputSubtype,
    formFieldMaxLength,
    formFieldMetadataText,
    formFieldParticipationSlots,
    formFieldPlaceholder,
    formFillBuilderType,
    hasFormAnswerValue,
    isBundleDuplicatableField,
    isFormFieldCheckboxLike,
    isFormFieldMultipleSelect,
    isFormFieldRadioLike,
    isFormFieldRequired,
    isFormFillFieldDisplayOnly,
    resolveFormAccessBlockCopy,
} from '@/lib/formFillFields';
import type { TFormAccessStatus, TFormFillAnswerValue } from '@/types/form';

const ACCESS_STATUSES: TFormAccessStatus[] = [
    'allowed',
    'not_visible',
    'form_closed',
    'registration_not_open',
    'quota_full',
    'unsupported_registration_mode',
    'pending_team_confirmation',
    'invitation_closed',
    'already_submitted',
    'event_form_already_chosen',
    'prerequisite_not_met',
];

describe('formFillFields: answer keys and slots', () => {
    it('builds bundle keys with the backend format', () => {
        expect(buildBundleAnswerKey('full_name', 2)).toBe('bundle__full_name__2');
        expect(formFieldAnswerKey(makeFormField({ name: 'email' }), null)).toBe('email');
        expect(formFieldAnswerKey(makeFormField({ name: 'email' }), 0)).toBe('bundle__email__0');
    });

    it('renders one slot unless the field repeats per bundle participant', () => {
        const field = makeFormField({ name: 'shirt', metadata: { duplicatable: true } });

        expect(formFieldParticipationSlots({ field, registrationMode: 'single', memberSlots: 2 })).toEqual([
            { slotIndex: null, title: '' },
        ]);

        expect(formFieldParticipationSlots({ field, registrationMode: 'bundle', memberSlots: 2 })).toEqual([
            { slotIndex: null, title: 'Lead participant' },
            { slotIndex: 0, title: 'Participant 2' },
            { slotIndex: 1, title: 'Participant 3' },
        ]);
    });

    it('ignores duplicatable=true on display-only fields', () => {
        const heading = makeFormField({
            name: 'intro',
            type: 'paragraph',
            metadata: { duplicatable: true },
        });

        expect(isBundleDuplicatableField(heading, 'bundle')).toBe(false);
        expect(isBundleDuplicatableField(heading, 'single')).toBe(false);
    });

    it('collects every answer key a field can produce', () => {
        const field = makeFormField({ name: 'shirt', metadata: { duplicatable: true } });

        expect(formFieldAnswerKeys({ field, registrationMode: 'bundle', memberSlots: 1 })).toEqual([
            'shirt',
            'bundle__shirt__0',
        ]);
    });
});

describe('formFillFields: builder type and display-only', () => {
    it('treats heading/paragraph/divider/banner/image as display-only', () => {
        const displayOnlyTypes: Array<IFormField['type']> = ['heading', 'paragraph', 'divider', 'banner', 'image'];
        for (const type of displayOnlyTypes) {
            expect(isFormFillFieldDisplayOnly(makeFormField({ name: `f-${type}`, type }))).toBe(true);
        }
    });

    it('keeps answerable fields out of the display-only set', () => {
        expect(isFormFillFieldDisplayOnly(makeFormField({ name: 'name', type: 'input' }))).toBe(false);
        expect(isFormFillFieldDisplayOnly(makeFormField({ name: 'cv', type: 'fileUpload' }))).toBe(false);
    });

    it('falls back to the API type when metadata has no builderType', () => {
        expect(formFillBuilderType(makeFormField({ name: 'name', type: 'input' }))).toBe('input');
    });
});

describe('formFillFields: initial answer values', () => {
    it('seeds typed defaults for every answerable field', () => {
        const values = createFormFillInitialValues({
            fields: [
                makeFormField({ name: 'name', type: 'input' }),
                makeFormField({ name: 'topics', type: 'checkbox' }),
                makeFormField({ name: 'skills', type: 'select', metadata: { is_multiple: true } }),
                makeFormField({ name: 'cv', type: 'fileUpload' }),
                makeFormField({ name: 'intro', type: 'heading' }),
            ],
            memberSlots: 0,
            registrationMode: 'single',
        });

        expect(values).toEqual({
            name: '',
            topics: [],
            skills: [],
            cv: null,
        });
    });

    it('adds team member email slots when the form needs them', () => {
        const values = createFormFillInitialValues({
            fields: [],
            memberSlots: 3,
            registrationMode: 'team',
        });

        expect(values.team_member_emails).toEqual(['', '', '']);
    });

    it('adds one answer key per bundle participant for duplicatable fields', () => {
        const values = createFormFillInitialValues({
            fields: [
                makeFormField({ name: 'shirt', metadata: { duplicatable: true } }),
                makeFormField({ name: 'cv', type: 'fileUpload', metadata: { duplicatable: true } }),
                makeFormField({ name: 'note', metadata: {} }),
            ],
            memberSlots: 2,
            registrationMode: 'bundle',
        });

        expect(values).toEqual({
            shirt: '',
            cv: null,
            note: '',
            team_member_emails: ['', ''],
            bundle__shirt__0: '',
            bundle__shirt__1: '',
            bundle__cv__0: null,
            bundle__cv__1: null,
        });
    });
});

describe('formFillFields: access block copy', () => {
    it('returns copy for every access status', () => {
        for (const status of ACCESS_STATUSES) {
            const copy = resolveFormAccessBlockCopy(status, 'Backend says no.');
            expect(copy.body).toBe('Backend says no.');
            expect(copy.title).toBe(status === 'allowed' ? '' : copy.title);
            if (status !== 'allowed') expect(copy.title.length).toBeGreaterThan(0);
        }
    });

    it('marks already_submitted as a success block and falls back when the message is empty', () => {
        expect(resolveFormAccessBlockCopy('already_submitted', '')).toEqual({
            title: 'You have already submitted this form.',
            body: 'This form is not available right now.',
            success: true,
        });
        expect(resolveFormAccessBlockCopy('form_closed', 'Closed').success).toBeUndefined();
    });
});

describe('formFillFields: field presentation helpers', () => {
    it('resolves input subtypes and placeholders', () => {
        expect(formFieldInputSubtype(makeFormField({ name: 'a', metadata: { type: 'short_text' } }))).toBe('text');
        expect(formFieldInputSubtype(makeFormField({ name: 'b', metadata: { type: 'phone' } }))).toBe('tel');
        expect(formFieldInputSubtype(makeFormField({ name: 'c', type: 'url' }))).toBe('url');
        expect(formFieldInputSubtype(makeFormField({ name: 'd' }))).toBe('text');
        expect(formFieldPlaceholder(makeFormField({ name: 'e', metadata: { placeholder: 'Your name' } }))).toBe(
            'Your name'
        );
        expect(formFieldPlaceholder(makeFormField({ name: 'f' }))).toBe('');
    });

    it('resolves required, multi-select, radio, and checkbox flags', () => {
        expect(isFormFieldRequired(makeFormField({ name: 'a', metadata: { rules: { required: true } } }))).toBe(true);
        expect(isFormFieldMultipleSelect(makeFormField({ name: 'b', metadata: { is_multiple: 'true' } }))).toBe(true);
        expect(isFormFieldRadioLike(makeFormField({ name: 'c', metadata: { builderType: 'yes_no' } }))).toBe(true);
        expect(isFormFieldCheckboxLike(makeFormField({ name: 'd', type: 'checkbox' }))).toBe(true);
        expect(isFormFieldCheckboxLike(makeFormField({ name: 'e', metadata: { is_multiple: 1 } }))).toBe(true);
        expect(isFormFieldRadioLike(makeFormField({ name: 'f', type: 'input' }))).toBe(false);
    });

    it('reads text max length from rules first, then metadata', () => {
        expect(
            formFieldMaxLength(
                makeFormField({ name: 'a', metadata: { builderType: 'short_text', rules: { max: 12 } } })
            )
        ).toBe(12);
        expect(
            formFieldMaxLength(makeFormField({ name: 'b', metadata: { builderType: 'long_text', maxLength: '40' } }))
        ).toBe(40);
        expect(formFieldMaxLength(makeFormField({ name: 'c', metadata: { builderType: 'email', max: 10 } }))).toBe(
            undefined
        );
        expect(formFieldMaxLength(makeFormField({ name: 'd', metadata: { builderType: 'short_text', max: 0 } }))).toBe(
            undefined
        );
    });

    it('builds upload hints and accept attributes', () => {
        const field = makeFormField({
            name: 'photo',
            metadata: { builderType: 'image_upload', rules: { mimes: 'png, jpg', max_size: 2048 } },
        });

        expect(formFieldFileHint(field)).toBe(
            'Recommended: 1200 x 900 px (4:3) · Allowed: png, jpg · Max size: 2048 KB'
        );
        expect(formFieldAcceptAttribute(field)).toBe('.png,.jpg');
        expect(formFieldAcceptAttribute(makeFormField({ name: 'plain' }))).toBeUndefined();
    });

    it('uses the typed upload allowlist when rendering the allowed-format hint', () => {
        const field = makeFormField({
            name: 'documents',
            metadata: {
                builderType: 'file_upload',
                allowed_mime_types: [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
            },
        });

        expect(formFieldFileHint(field)).toContain('Allowed: pdf, docx');
    });

    it('resolves display-only image sources and metadata text', () => {
        const field = makeFormField({
            name: 'diagram',
            type: 'image',
            metadata: { imageUrl: 'form-images/1/diagram.png', caption: 'Architecture' },
        });

        expect(formFieldImageSrc(field)).toBe('/storage/form-images/1/diagram.png');
        expect(formFieldMetadataText(field, 'caption')).toBe('Architecture');
        expect(formFieldMetadataText(field, 'alt')).toBe('');
        expect(formFieldImageSrc(makeFormField({ name: 'empty' }))).toBe('');
    });
});

describe('formFillFields: value emptiness and account email', () => {
    const valueCases: Array<[TFormFillAnswerValue | undefined, boolean]> = [
        [undefined, false],
        [null, false],
        ['', false],
        ['   ', false],
        ['a', true],
        [[], false],
        [['a'], true],
    ];

    it.each(valueCases)('hasFormAnswerValue(%j) === %s', (value, expected: boolean) => {
        expect(hasFormAnswerValue(value)).toBe(expected);
    });

    it('detects the signed-in email in a member list', () => {
        expect(containsAccountEmail(['Me@Example.com '], 'me@example.com')).toBe(true);
        expect(containsAccountEmail(['other@example.com'], 'me@example.com')).toBe(false);
        expect(containsAccountEmail(undefined, 'me@example.com')).toBe(false);
        expect(containsAccountEmail(['me@example.com'], '')).toBe(false);
    });
});
