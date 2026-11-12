import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, nextTick, ref, type Ref } from 'vue';
import { HttpRequestError } from '@/adapters/http/httpError';
import type { IFormBuilderAutosaveModels } from '@/hooks/useFormBuilderAutosave';

const saveFormFields = vi.fn<(url: string, fields: unknown[]) => Promise<void>>();
const updateFormSettings = vi.fn<(url: string, payload: Record<string, unknown>) => Promise<void>>();
const persistFormSnapshotKeepalive = vi.fn<(options: unknown) => void>();

vi.mock('@/adapters/http/forms', () => ({
    saveFormFields: (...args: [string, unknown[]]) => saveFormFields(...args),
    updateFormSettings: (...args: [string, Record<string, unknown>]) => updateFormSettings(...args),
    persistFormSnapshotKeepalive: (...args: [unknown]) => persistFormSnapshotKeepalive(...args),
}));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => ({
        notifyAutosaveTerminalFailure: (): void => undefined,
    }),
}));

const { aggregateAutosaveStatus, useFormBuilderAutosave } = await import('@/hooks/useFormBuilderAutosave');

const EMPTY_METADATA = {
    purpose: 'registration' as const,
    requires_form_id: null,
    registration_mode: null,
    max_team_size: null,
    team_size: null,
    sections: [{ id: 'section-1', title: 'Bagian 1' }],
};

interface IHarness {
    models: IFormBuilderAutosaveModels;
    flush: () => Promise<void>;
    status: Ref<string>;
    hasUnsavedChanges: Ref<boolean>;
}

function createHarness(isDraft = true): IHarness {
    const models: IFormBuilderAutosaveModels = {
        formTitle: ref(''),
        formDescription: ref(''),
        successContent: ref(''),
        closedAt: ref(''),
        visibleFor: ref<string[]>([]),
        banner: ref({ id: null, bannerUrl: '', bannerFileName: '', caption: '' }),
        formFields: ref([]),
        formMetadata: ref({ ...EMPTY_METADATA, sections: [...EMPTY_METADATA.sections] }),
    };

    const autosave = useFormBuilderAutosave({
        models,
        endpoints: computed(() => ({ fieldsUrl: '/forms/f-1/fields', settingsUrl: '/forms/f-1' })),
        isDraft,
        debounceMs: 10,
        onError: (): void => undefined,
    });

    return { models, flush: autosave.flush, status: autosave.status, hasUnsavedChanges: autosave.hasUnsavedChanges };
}

/** Jalankan debounce + antrean save sampai tenang. */
async function settle(): Promise<void> {
    await vi.runAllTimersAsync();
    await Promise.resolve();
    await vi.runAllTimersAsync();
}

beforeEach(() => {
    vi.useFakeTimers();
    saveFormFields.mockReset();
    saveFormFields.mockResolvedValue(undefined);
    updateFormSettings.mockReset();
    updateFormSettings.mockResolvedValue(undefined);
    persistFormSnapshotKeepalive.mockReset();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('autosave gabungan header dan fields', () => {
    it('mengubah title menyimpan fields lalu header dalam satu siklus', async () => {
        const harness = createHarness();

        harness.models.formTitle.value = 'Judul Baru';
        await settle();

        expect(updateFormSettings).toHaveBeenCalledTimes(1);
        expect(updateFormSettings.mock.calls[0]?.[1]).toEqual({
            title: 'Judul Baru',
            description: '',
            autosave: true,
        });
        expect(saveFormFields).toHaveBeenCalledTimes(1);
    });

    it('mengubah description menyimpan fields lalu header', async () => {
        const harness = createHarness();

        harness.models.formDescription.value = 'Deskripsi baru';
        await settle();

        expect(updateFormSettings).toHaveBeenCalledTimes(1);
        expect(saveFormFields).toHaveBeenCalledTimes(1);
    });

    it('mengubah pengaturan lain menyimpan snapshot gabungan', async () => {
        const harness = createHarness();

        harness.models.closedAt.value = '2026-12-01T10:00';
        harness.models.visibleFor.value = ['public'];
        harness.models.formMetadata.value = { ...EMPTY_METADATA, max_team_size: 4 };
        await settle();

        expect(updateFormSettings).toHaveBeenCalledTimes(1);
        expect(saveFormFields).toHaveBeenCalledTimes(1);
    });
});

describe('autosave fields dan header bersama', () => {
    const FIELD = {
        id: 'f-1',
        type: 'short_text',
        label: 'Nama',
        description: '',
        name: 'nama',
        placeholder: '',
        required: false,
        options: [],
        metadata: {},
    };

    it('menambah section menyimpan snapshot gabungan', async () => {
        const harness = createHarness();

        harness.models.formMetadata.value = {
            ...EMPTY_METADATA,
            sections: [...EMPTY_METADATA.sections, { id: 'section-2', title: 'Bagian 2' }],
        };
        await settle();

        expect(saveFormFields).toHaveBeenCalledTimes(1);
        expect(updateFormSettings).toHaveBeenCalledTimes(1);
    });

    it('menambah section saat sudah ada field mengirim snapshot gabungan', async () => {
        const harness = createHarness();

        harness.models.formFields.value = [{ ...FIELD }];
        await settle();
        saveFormFields.mockClear();
        updateFormSettings.mockClear();
        updateFormSettings.mockClear();

        harness.models.formMetadata.value = {
            ...EMPTY_METADATA,
            sections: [...EMPTY_METADATA.sections, { id: 'section-2', title: 'Bagian 2' }],
        };
        await settle();

        expect(saveFormFields).toHaveBeenCalledTimes(1);
        expect(updateFormSettings).toHaveBeenCalledTimes(1);
    });

    it('menambah field pertama menyimpan fields tanpa data header/section', async () => {
        const harness = createHarness();

        harness.models.banner.value = {
            id: 'banner-1',
            bannerUrl: '/storage/form-images/banner.png',
            bannerFileName: 'banner.png',
            caption: 'Banner form',
        };
        harness.models.formFields.value = [{ ...FIELD }];
        await settle();

        expect(saveFormFields).toHaveBeenCalledTimes(1);
        expect(saveFormFields.mock.calls[0]?.[1]).toHaveLength(1);
        expect(saveFormFields.mock.calls[0]?.[1]).not.toContainEqual(expect.objectContaining({ name: 'form_banner' }));
        expect(updateFormSettings).toHaveBeenCalledTimes(1);
    });

    const fieldTriggers: Array<[string, (harness: IHarness) => void]> = [
        [
            'judul field berubah',
            (harness) => {
                harness.models.formFields.value = [{ ...FIELD, label: 'Nama Lengkap' }];
            },
        ],
        [
            'aturan field berubah',
            (harness) => {
                harness.models.formFields.value = [{ ...FIELD, required: true }];
            },
        ],
        [
            'opsi/tipe field berubah',
            (harness) => {
                harness.models.formFields.value = [{ ...FIELD, type: 'long_text' }];
            },
        ],
        [
            'duplikasi field',
            (harness) => {
                harness.models.formFields.value = [{ ...FIELD }, { ...FIELD, id: 'f-2', name: 'nama_2' }];
            },
        ],
        [
            'urutan field berubah',
            (harness) => {
                harness.models.formFields.value = [{ ...FIELD, id: 'f-2', name: 'nama_2' }, { ...FIELD }];
            },
        ],
        [
            'field dihapus (masih ada sisa)',
            (harness) => {
                harness.models.formFields.value = [{ ...FIELD, id: 'f-2', name: 'nama_2' }];
            },
        ],
    ];

    for (const [label, mutate] of fieldTriggers) {
        it(`${label} → tepat satu siklus autosave gabungan`, async () => {
            const harness = createHarness();

            harness.models.formFields.value = [{ ...FIELD }];
            await settle();
            saveFormFields.mockClear();
            updateFormSettings.mockClear();

            mutate(harness);
            await settle();

            expect(saveFormFields).toHaveBeenCalledTimes(1);
            expect(updateFormSettings).toHaveBeenCalledTimes(1);
        });
    }

    it('menghapus field terakhir mengirim POST fields dengan daftar kosong', async () => {
        const harness = createHarness();

        harness.models.formFields.value = [{ ...FIELD }];
        await settle();
        saveFormFields.mockClear();
        updateFormSettings.mockClear();

        harness.models.formFields.value = [];
        await settle();

        expect(saveFormFields).toHaveBeenCalledTimes(1);
        expect(saveFormFields.mock.calls[0]?.[1]).toEqual([]);
        expect(updateFormSettings).toHaveBeenCalledTimes(1);
    });

    it('form published hanya mengirim fields saat metadata field berubah', async () => {
        const harness = createHarness(false);

        harness.models.formTitle.value = 'Form Pendaftaran';
        harness.models.formDescription.value = 'Deskripsi singkat';
        harness.models.closedAt.value = '2026-12-01T10:00';
        harness.models.visibleFor.value = ['public'];
        await settle();

        saveFormFields.mockClear();
        updateFormSettings.mockClear();

        harness.models.formFields.value = [
            {
                ...FIELD,
                type: 'file_upload',
                metadata: { multiple: true },
            },
        ];
        await settle();

        expect(saveFormFields).toHaveBeenCalledTimes(1);
        expect(updateFormSettings).not.toHaveBeenCalled();
    });
});

describe('agregasi status', () => {
    it('memilih status dengan prioritas tertinggi', () => {
        expect(aggregateAutosaveStatus(['idle', 'saved'])).toBe('saved');
        expect(aggregateAutosaveStatus(['saved', 'saving'])).toBe('saving');
        expect(aggregateAutosaveStatus(['saving', 'uploading'])).toBe('uploading');
        expect(aggregateAutosaveStatus(['uploading', 'error'])).toBe('error');
        expect(aggregateAutosaveStatus(['idle', 'idle'])).toBe('idle');
    });

    it('hasUnsavedChanges true bila salah satu kanal punya pekerjaan', async () => {
        const harness = createHarness();

        expect(harness.hasUnsavedChanges.value).toBe(false);

        harness.models.formTitle.value = 'Judul';
        await nextTick();
        expect(harness.hasUnsavedChanges.value).toBe(true);

        await settle();
        expect(harness.hasUnsavedChanges.value).toBe(false);
    });
});

describe('kegagalan terminal', () => {
    it('404 menghentikan kedua kanal dan tidak ada retry', async () => {
        const harness = createHarness();

        updateFormSettings.mockRejectedValueOnce(
            new HttpRequestError({ kind: 'not_found', status: 404, message: 'Not found' })
        );

        harness.models.formTitle.value = 'Judul';
        await settle();
        expect(harness.status.value).toBe('error');

        updateFormSettings.mockClear();
        saveFormFields.mockClear();

        harness.models.formTitle.value = 'Judul Lagi';
        harness.models.formFields.value = [
            {
                id: 'f-1',
                type: 'short_text',
                label: 'Nama',
                description: '',
                name: 'nama',
                placeholder: '',
                required: false,
                options: [],
                metadata: {},
            },
        ];
        await settle();

        expect(updateFormSettings).not.toHaveBeenCalled();
        expect(saveFormFields).not.toHaveBeenCalled();
    });
});
