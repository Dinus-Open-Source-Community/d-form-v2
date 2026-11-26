import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { ref } from 'vue';
import { useFormBuilderWorkspace, type IFormBuilderWorkspaceModels } from '@/hooks/useFormBuilderWorkspace';
import type { IFormRegistrationMetadata } from '@/types/form';
import type { IBuilderField } from '@/types/form-builder';

const { showErrorToastMock } = vi.hoisted(() => ({ showErrorToastMock: vi.fn() }));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => ({ showErrorToast: showErrorToastMock }),
}));

const DU_MAP = {
    'AI Engineer': { course_uid: 'course-ai', course_title: 'AI Engineering' },
};

beforeEach(() => {
    showErrorToastMock.mockClear();
});

const EMPTY_METADATA: IFormRegistrationMetadata = {
    purpose: 'registration',
    requires_form_id: null,
    registration_mode: null,
    max_team_size: null,
    team_size: null,
    allow_multiple_submissions: false,
    allow_guest_submissions: false,
    max_submissions: 1,
    sections: [{ id: 'section-1', title: 'Bagian 1' }],
    referral_position: 'end',
    meeting_schedule_enabled: false,
};

function classField(id: string, name: string, metadata: Record<string, unknown> = {}): IBuilderField {
    return {
        id,
        type: 'dropdown',
        label: 'Class',
        description: '',
        name,
        placeholder: '',
        required: true,
        options: [{ id: `${id}-option`, type: 'text', label: 'AI Engineer', imageUrl: '' }],
        metadata,
    };
}

type TWorkspace = ReturnType<typeof useFormBuilderWorkspace>;

interface IWorkspaceHarness {
    models: IFormBuilderWorkspaceModels;
    workspace: TWorkspace;
}

/**
 * Jalankan hook di dalam setup aplikasi SSR agar registrasi `onMounted`/`onUnmounted`
 * punya instance komponen (tanpa DOM) dan tidak memicu Vue warning.
 */
async function createWorkspace(): Promise<IWorkspaceHarness> {
    const models: IFormBuilderWorkspaceModels = {
        formTitle: ref('Form Pendaftaran'),
        formDescription: ref('Deskripsi'),
        closedAt: ref('2026-12-01T10:00'),
        visibleFor: ref(['public']),
        banner: ref({ id: null, bannerUrl: '', bannerFileName: '', caption: '' }),
        formFields: ref([
            classField('f-a', 'class_a', { du_redeem_enabled: true, du_option_map: DU_MAP }),
            classField('f-b', 'class_b'),
        ]),
        formMetadata: ref({ ...EMPTY_METADATA, sections: [...EMPTY_METADATA.sections] }),
        canvasScrollContainer: ref(null),
    };

    const holder: { workspace: TWorkspace | null } = { workspace: null };

    const app = createSSRApp({
        setup(): () => null {
            holder.workspace = useFormBuilderWorkspace(models, { onSave: (): void => undefined });

            return (): null => null;
        },
    });
    await renderToString(app);

    if (holder.workspace === null) throw new Error('workspace tidak terbentuk');

    return { models, workspace: holder.workspace };
}

describe('workspace single-flag redeem DU', () => {
    it('menolak mengaktifkan redeem DU di field B saat field A masih owner', async () => {
        const { models, workspace } = await createWorkspace();
        const fieldB = models.formFields.value[1];

        if (fieldB === undefined) throw new Error('field B tidak ada');

        workspace.updateField({ ...fieldB, metadata: { ...fieldB.metadata, du_redeem_enabled: true } });

        expect(models.formFields.value[0]?.metadata.du_redeem_enabled).toBe(true);
        expect(models.formFields.value[1]?.metadata.du_redeem_enabled).toBeUndefined();
        expect(showErrorToastMock).toHaveBeenCalledWith('Matikan Redeem DU di field Class dulu.');
    });

    it('mengizinkan field B menjadi owner setelah field A mematikan flag', async () => {
        const { models, workspace } = await createWorkspace();
        const fieldA = models.formFields.value[0];

        if (fieldA === undefined) throw new Error('field A tidak ada');

        workspace.updateField({ ...fieldA, metadata: { ...fieldA.metadata, du_redeem_enabled: false } });

        const fieldB = models.formFields.value[1];

        if (fieldB === undefined) throw new Error('field B tidak ada');

        workspace.updateField({ ...fieldB, metadata: { ...fieldB.metadata, du_redeem_enabled: true } });

        expect(models.formFields.value[0]?.metadata.du_redeem_enabled).toBe(false);
        expect(models.formFields.value[1]?.metadata.du_redeem_enabled).toBe(true);
        expect(showErrorToastMock).not.toHaveBeenCalled();
    });

    it('mematikan flag tidak mengubah flag field lain', async () => {
        const { models, workspace } = await createWorkspace();
        const fieldA = models.formFields.value[0];

        if (fieldA === undefined) throw new Error('field A tidak ada');

        workspace.updateField({ ...fieldA, metadata: { ...fieldA.metadata, du_redeem_enabled: false } });

        expect(models.formFields.value[0]?.metadata.du_redeem_enabled).toBe(false);
        expect(models.formFields.value[1]?.metadata.du_redeem_enabled).toBeUndefined();
    });
});

describe('workspace hapus dan duplikasi field kelas DU', () => {
    it('menduplikasi field DU membuang flag dari salinan tetapi mempertahankan opsi dan mapping', async () => {
        const { models, workspace } = await createWorkspace();

        workspace.duplicateField('f-a');

        const [original, copy] = models.formFields.value;

        expect(models.formFields.value).toHaveLength(3);
        expect(original?.metadata.du_redeem_enabled).toBe(true);
        expect(copy?.id).not.toBe('f-a');
        expect(copy?.metadata.du_redeem_enabled).toBeUndefined();
        expect(copy?.metadata.du_option_map).toEqual(DU_MAP);
        expect(copy?.options).toEqual(original?.options);
    });

    it('menolak hapus field kelas DU dan memberi toast', async () => {
        const { models, workspace } = await createWorkspace();

        workspace.deleteField('f-a');

        expect(models.formFields.value.map((field) => field.id)).toEqual(['f-a', 'f-b']);
        expect(showErrorToastMock).toHaveBeenCalledWith(
            'Field kelas DU tidak dapat dihapus. Matikan integrasi DU dulu.'
        );
    });

    it('menolak hapus section yang berisi field kelas DU', async () => {
        const { models, workspace } = await createWorkspace();
        const fieldA = models.formFields.value[0];

        if (fieldA === undefined) throw new Error('field A tidak ada');

        models.formMetadata.value = {
            ...models.formMetadata.value,
            sections: [
                { id: 'section-1', title: 'Bagian 1' },
                { id: 'section-2', title: 'Bagian 2' },
            ],
        };
        models.formFields.value = [
            { ...fieldA, metadata: { ...fieldA.metadata, sectionId: 'section-2' } },
            ...models.formFields.value.slice(1),
        ];

        workspace.removeSection('section-2');

        expect(models.formMetadata.value.sections.map((section) => section.id)).toEqual(['section-1', 'section-2']);
        expect(models.formFields.value.map((field) => field.id)).toEqual(['f-a', 'f-b']);
        expect(showErrorToastMock).toHaveBeenCalledWith(
            'Field kelas DU tidak dapat dihapus. Matikan integrasi DU dulu.'
        );
    });

    it('tetap bisa menghapus field setelah flag dimatikan', async () => {
        const { models, workspace } = await createWorkspace();
        const fieldA = models.formFields.value[0];

        if (fieldA === undefined) throw new Error('field A tidak ada');

        workspace.updateField({ ...fieldA, metadata: { ...fieldA.metadata, du_redeem_enabled: false } });
        workspace.deleteField('f-a');

        expect(models.formFields.value.map((field) => field.id)).toEqual(['f-b']);
    });
});
