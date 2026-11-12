import { describe, expect, it, vi } from 'vitest';
import { nextTick, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { makeFormField } from '@/lib/__tests__/formFillFieldFixtures';
import { useFormFillFieldsList } from '@/hooks/useFormFillFieldsList';
import { buildBundleAnswerKey } from '@/lib/formFillFields';
import type { IFormBuilderSection } from '@/types/form-builder';
import type { TFormFillAnswerMap } from '@/types/form';

const SECTIONS: IFormBuilderSection[] = [
    { id: 'personal', title: 'Personal' },
    { id: 'experience', title: 'Experience' },
];

function createList(
    options: {
        registrationMode?: string;
        validateSection?: (fields: IFormField[]) => boolean;
        bundleNames?: string[];
        blocked?: boolean;
    } = {}
) {
    const fields = [
        makeFormField({ name: 'full_name', order: 0, metadata: { sectionId: 'personal' } }),
        makeFormField({ name: 'reason', order: 1, metadata: { sectionId: 'experience' } }),
        makeFormField({ name: 'shirt', order: 2, metadata: { sectionId: 'experience', duplicatable: true } }),
        makeFormField({ name: 'food', order: 3, metadata: { sectionId: 'experience', duplicatable: true } }),
    ];
    const answerForm = useForm<TFormFillAnswerMap>({ full_name: '', reason: '', shirt: '', food: '' });
    const filePreviewUrls = reactive<Record<string, string>>({});
    const validateSection = vi.fn(options.validateSection ?? (() => true));
    const bundleNames = new Set(options.bundleNames ?? ['shirt', 'food']);

    const list = useFormFillFieldsList({
        fields: () => fields,
        sections: () => SECTIONS,
        registrationMode: options.registrationMode ?? 'single',
        answerForm,
        filePreviewUrls,
        validateSection,
        isBundleDuplicatable: (field) => bundleNames.has(field.name),
        isBlocked: () => options.blocked ?? false,
    });

    return { fields, answerForm, filePreviewUrls, validateSection, list };
}

/** Props simulasi: array sumber diganti seperti render ulang Inertia. */
function createSwappableList() {
    const fields = ref<IFormField[]>([
        makeFormField({ name: 'full_name', order: 0, metadata: { sectionId: 'personal' } }),
    ]);
    const sections = ref<IFormBuilderSection[]>([{ id: 'personal', title: 'Personal' }]);
    const answerForm = useForm<TFormFillAnswerMap>({ full_name: '', reason: '', shirt: '', food: '' });
    const filePreviewUrls = reactive<Record<string, string>>({});

    const list = useFormFillFieldsList({
        fields: () => fields.value,
        sections: () => sections.value,
        registrationMode: 'single',
        answerForm,
        filePreviewUrls,
        validateSection: () => true,
        isBundleDuplicatable: () => false,
        isBlocked: () => false,
    });

    return { fields, sections, list };
}

describe('useFormFillFieldsList', () => {
    it('re-derives sections and segments when the fields/sections props change', () => {
        const { fields, sections, list } = createSwappableList();

        expect(list.currentSectionFields.value.map((field) => field.name)).toEqual(['full_name']);

        fields.value = [
            makeFormField({ name: 'full_name', order: 0, metadata: { sectionId: 'personal' } }),
            makeFormField({ name: 'reason', order: 1, metadata: { sectionId: 'experience' } }),
        ];
        sections.value = [
            { id: 'personal', title: 'Personal' },
            { id: 'experience', title: 'Experience' },
        ];

        expect(list.currentSectionFields.value.map((field) => field.name)).toEqual(['full_name']);

        list.nextSection();

        expect(list.currentSectionFields.value.map((field) => field.name)).toEqual(['reason']);
        expect(list.formSegments.value.map((segment) => segment.type)).toEqual(['linear']);
    });

    it('starts on the first section and exposes its fields', () => {
        const { list } = createList();

        expect(list.currentSectionIndex.value).toBe(0);
        expect(list.currentSectionFields.value.map((field) => field.name)).toEqual(['full_name']);
    });

    it('validates the current section before moving on', () => {
        const { list, validateSection, fields } = createList({ validateSection: () => false });

        list.nextSection();

        expect(validateSection).toHaveBeenCalledWith([fields[0]]);
        expect(list.currentSectionIndex.value).toBe(0);
    });

    it('advances to the next section and stops at the last one', () => {
        const { list } = createList();

        list.nextSection();
        expect(list.currentSectionIndex.value).toBe(1);
        expect(list.currentSectionFields.value.map((field) => field.name)).toEqual(['reason', 'shirt', 'food']);

        list.nextSection();
        expect(list.currentSectionIndex.value).toBe(1);
    });

    it('clamps previousSection at the first section', () => {
        const { list } = createList();

        list.previousSection();
        expect(list.currentSectionIndex.value).toBe(0);

        list.nextSection();
        list.previousSection();
        expect(list.currentSectionIndex.value).toBe(0);
    });

    it('groups consecutive bundle fields into one segment', () => {
        const { list } = createList();
        list.nextSection();

        const segments = list.formSegments.value;
        expect(segments.map((segment) => segment.type)).toEqual(['linear', 'bundleGroup']);
        expect(segments[1]?.fields.map((field) => field.name)).toEqual(['shirt', 'food']);
    });

    it('builds answer keys for lead and participant slots', () => {
        const { list, fields } = createList();

        expect(list.storageKeyFor(fields[0], { slotIndex: null, title: '' })).toBe('full_name');
        expect(list.storageKeyFor(fields[2], { slotIndex: 1, title: 'Participant 3' })).toBe(
            buildBundleAnswerKey('shirt', 1)
        );
    });

    it('reports image upload readiness only when a preview exists', () => {
        const { list, answerForm, filePreviewUrls } = createList();
        const uploadField = makeFormField({ name: 'photo', metadata: { builderType: 'image_upload' } });

        expect(list.imageUploadFillReady(uploadField, 'photo')).toBe(false);

        answerForm.photo = new File(['x'], 'photo.png', { type: 'image/png' });
        filePreviewUrls.photo = 'blob:preview';
        expect(list.imageUploadFillReady(uploadField, 'photo')).toBe(true);

        expect(list.imageUploadFillReady(makeFormField({ name: 'note' }), 'note')).toBe(false);
    });

    it('opens the lightbox only when a source is given and clears it on close', async () => {
        const { list } = createList();

        list.openUploadLightbox(undefined, 'Missing');
        expect(list.uploadLightboxOpen.value).toBe(false);

        list.openUploadLightbox('blob:preview', 'photo.png');
        expect(list.uploadLightboxOpen.value).toBe(true);
        expect(list.uploadLightboxSrc.value).toBe('blob:preview');
        expect(list.uploadLightboxTitle.value).toBe('photo.png');

        list.uploadLightboxOpen.value = false;
        await nextTick();

        expect(list.uploadLightboxSrc.value).toBeNull();
        expect(list.uploadLightboxTitle.value).toBe('');
    });

    it('refuses to open the submit confirmation while blocked', () => {
        const { list } = createList({ blocked: true });

        list.requestSubmitConfirm();

        expect(list.submitConfirmOpen.value).toBe(false);
    });

    it('opens the submit confirmation and closes it again', () => {
        const { list } = createList();

        list.requestSubmitConfirm();
        expect(list.submitConfirmOpen.value).toBe(true);

        list.closeSubmitConfirm();
        expect(list.submitConfirmOpen.value).toBe(false);
    });

    it.each([
        ['single', 'Please review your answers. After submitting, changes may not be possible.'],
        [
            'team',
            'Your answers will be sent. Invitations will be emailed to the team members you listed where required.',
        ],
        ['bundle', 'All bundled entries in this registration will be submitted together.'],
    ])('describes the submit confirmation for %s registration', (mode, expected: string) => {
        const { list } = createList({ registrationMode: mode });

        expect(list.submitConfirmDescription.value).toBe(expected);
    });
});
