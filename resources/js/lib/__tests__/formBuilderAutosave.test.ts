import { describe, expect, it } from 'vitest';
import {
    buildFieldsKeepalivePayload,
    buildFormBuilderSettingsPayload,
    buildHeaderAutosavePayload,
    buildHeaderKeepalivePayload,
    isFormBuilderHeaderComplete,
    serializeFieldsSnapshot,
    serializeHeaderSnapshot,
    type IFormBuilderDraftState,
} from '@/lib/formBuilderAutosave';
import { emptyFormRegistrationMetadata } from '@/lib/formRegistrationMetadata';
import type { IBuilderField } from '@/types/form-builder';

/** State builder minimal yang valid (header lengkap). */
function completeState(overrides: Partial<IFormBuilderDraftState> = {}): IFormBuilderDraftState {
    return {
        title: 'Form Pendaftaran',
        description: 'Deskripsi singkat',
        successContent: 'Terima kasih',
        closedAt: '2026-12-01T10:00',
        visibleFor: ['public'],
        banner: { bannerUrl: '', caption: '' },
        fields: [],
        metadata: emptyFormRegistrationMetadata(),
        ...overrides,
    };
}

const SAMPLE_FIELDS: readonly IBuilderField[] = [
    {
        id: 'f-1',
        type: 'text',
        label: 'Nama',
        description: '',
        name: 'nama',
        placeholder: '',
        required: true,
        options: [],
        metadata: {},
    },
];

describe('isFormBuilderHeaderComplete', () => {
    const cases: Array<[string, Partial<IFormBuilderDraftState>, boolean]> = [
        ['lengkap', {}, true],
        ['judul kosong', { title: '   ' }, false],
        ['deskripsi kosong', { description: '' }, false],
        ['tanggal tutup kosong', { closedAt: '' }, false],
        ['tanggal tutup hanya spasi', { closedAt: '  ' }, false],
        ['visibilitas kosong', { visibleFor: [] }, false],
        ['semua kosong', { title: '', description: '', closedAt: '', visibleFor: [] }, false],
    ];

    for (const [label, overrides, expected] of cases) {
        it(`header ${label} → ${expected}`, () => {
            expect(isFormBuilderHeaderComplete(completeState(overrides))).toBe(expected);
        });
    }
});

describe('serializeHeaderSnapshot', () => {
    it('hanya memuat judul dan deskripsi', () => {
        const snapshot = JSON.parse(serializeHeaderSnapshot(completeState({ fields: SAMPLE_FIELDS })));

        expect(snapshot).toEqual({ title: 'Form Pendaftaran', description: 'Deskripsi singkat' });
    });

    it('tidak berubah saat closed_at, visibilitas, banner, atau fields berubah', () => {
        const base = serializeHeaderSnapshot(completeState());
        const changed = serializeHeaderSnapshot(
            completeState({
                closedAt: '2026-12-31T23:59',
                visibleFor: ['public', 'participant'],
                banner: { bannerUrl: '/x.png', caption: 'Caption' },
                fields: SAMPLE_FIELDS,
            })
        );

        expect(changed).toBe(base);
    });
});

describe('serializeFieldsSnapshot', () => {
    it('hanya memuat daftar field (tanpa section/header)', () => {
        const state = completeState({
            fields: SAMPLE_FIELDS,
            metadata: { ...completeState().metadata, sections: [{ id: 'section-1', title: 'Bagian 1' }] },
        });

        expect(Object.keys(JSON.parse(serializeFieldsSnapshot(state)))).toEqual(['fields']);
    });

    it('tidak berubah saat section berubah', () => {
        const base = serializeFieldsSnapshot(completeState({ fields: SAMPLE_FIELDS }));
        const changed = serializeFieldsSnapshot(
            completeState({
                fields: SAMPLE_FIELDS,
                metadata: { ...completeState().metadata, sections: [{ id: 'x', title: 'Bagian X' }] },
            })
        );

        expect(changed).toBe(base);
    });

    it('tidak berubah saat judul/deskripsi berubah', () => {
        const base = serializeFieldsSnapshot(completeState({ fields: SAMPLE_FIELDS }));

        expect(serializeFieldsSnapshot(completeState({ fields: SAMPLE_FIELDS, title: 'Lain' }))).toBe(base);
    });
});

describe('payload kanal header', () => {
    it('draf mengirim judul + deskripsi saja dengan autosave', () => {
        expect(buildHeaderAutosavePayload(completeState())).toEqual({
            title: 'Form Pendaftaran',
            description: 'Deskripsi singkat',
            autosave: true,
        });
    });

    const urls = { fieldsUrl: '/admin/events/e-1/forms/f-1/fields', settingsUrl: '/admin/events/e-1/forms/f-1' };

    it('keepalive draf hanya mengirim settings header', () => {
        const payload = buildHeaderKeepalivePayload(completeState(), urls, { isDraft: true });

        expect(payload.fieldsUrl).toBeUndefined();
        expect(payload.settingsUrl).toBe(urls.settingsUrl);
        expect(payload.settings).toEqual({
            title: 'Form Pendaftaran',
            description: 'Deskripsi singkat',
            autosave: true,
        });
    });

    it('keepalive published melewatkan settings saat header belum lengkap', () => {
        expect(buildHeaderKeepalivePayload(completeState({ closedAt: '' }), urls, { isDraft: false })).toEqual({});
    });

    it('keepalive published mengirim payload lengkap saat header lengkap', () => {
        const payload = buildHeaderKeepalivePayload(completeState(), urls, { isDraft: false });

        expect(payload.settings?.title).toBe('Form Pendaftaran');
        expect(payload.settings?.autosave).toBeUndefined();
    });
});

describe('payload kanal fields', () => {
    const urls = { fieldsUrl: '/admin/events/e-1/forms/f-1/fields', settingsUrl: '/admin/events/e-1/forms/f-1' };

    it('keepalive hanya membawa daftar field', () => {
        const payload = buildFieldsKeepalivePayload(urls, [{ name: 'nama' }]);

        expect(payload).toEqual({ fieldsUrl: urls.fieldsUrl, fields: [{ name: 'nama' }] });
        expect(payload.settingsUrl).toBeUndefined();
        expect(payload.settings).toBeUndefined();
    });

    it('daftar kosong (hapus field terakhir) tetap dikirim ke endpoint fields', () => {
        expect(buildFieldsKeepalivePayload(urls, [])).toEqual({ fieldsUrl: urls.fieldsUrl, fields: [] });
    });
});

describe('buildFormBuilderSettingsPayload (manual save / publish)', () => {
    it('payload publish tidak membawa flag autosave', () => {
        const payload = buildFormBuilderSettingsPayload(completeState(), { publish: true, fields: [] });

        expect(payload.publish).toBe(true);
        expect(payload.autosave).toBeUndefined();
        expect(payload.fields).toEqual([]);
    });

    it('mode autosave menandai payload dan tetap membawa header lengkap', () => {
        const payload = buildFormBuilderSettingsPayload(completeState(), { autosave: true });

        expect(payload.autosave).toBe(true);
        expect(payload.title).toBe('Form Pendaftaran');
        expect(payload.closed_at).toBe('2026-12-01T10:00');
    });

    it('tidak menyalin referensi visibleFor milik state', () => {
        const state = completeState();
        const payload = buildFormBuilderSettingsPayload(state);

        expect(payload.visible_for).not.toBe(state.visibleFor);
    });
});
