import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { defaultFormBannerState } from '@/components/modules/builder/formBanner';
import {
    ALL_FORM_BUILDER_FIELD_TEMPLATES,
    type IFormBuilderPaletteField,
} from '@/components/modules/builder/formBuilderPalette';
import { useFormBuilderDemoPage } from '@/hooks/useFormBuilderDemoPage';
import { useFormBuilderWorkspace, type IFormBuilderWorkspaceModels } from '@/hooks/useFormBuilderWorkspace';
import { emptyFormRegistrationMetadata } from '@/lib/formRegistrationMetadata';
import type { IBuilderField } from '@/types/form-builder';

/**
 * Parity entry point demo (`useFormBuilderDemoPage`) vs production
 * (`useFormBuilderWorkspace`) untuk operasi daftar field yang dipakai bersama.
 * Kalau salah satu entry point menyimpang dari `createFormBuilderFieldList`,
 * urutan/panjang hasil akan beda di test ini.
 */

interface IParityEntry {
    fields: () => Array<{ id: string; label: string; type: string }>;
    add: (type: TFormBuilderType) => string;
    duplicate: (id: string) => string | null;
    updateLabel: (id: string, label: string) => void;
    remove: (id: string) => void;
    selectedId: () => string | null;
}

function createWorkspaceEntry(): IParityEntry {
    const models: IFormBuilderWorkspaceModels = {
        formTitle: ref('Judul form'),
        formDescription: ref('Deskripsi'),
        closedAt: ref('2026-12-31'),
        visibleFor: ref<string[]>(['public']),
        banner: ref(defaultFormBannerState()),
        formFields: ref<IBuilderField[]>([]),
        formMetadata: ref(emptyFormRegistrationMetadata()),
        canvasScrollContainer: ref<HTMLElement | null>(null),
        successContent: ref(''),
    };
    const workspace = useFormBuilderWorkspace(models, { onSave: (): void => undefined });
    /** Label mengikuti palette agar sama dengan label yang dipakai `addFieldFromPicker` demo. */
    const paletteTemplate = (type: TFormBuilderType): IFormBuilderPaletteField => {
        const template = ALL_FORM_BUILDER_FIELD_TEMPLATES.find(
            (entry: IFormBuilderPaletteField): boolean => entry.type === type
        );
        if (template === undefined) throw new Error(`Palette template tidak tersedia untuk ${type}`);

        return template;
    };

    return {
        fields: () =>
            models.formFields.value.map((field: IBuilderField) => ({
                id: field.id,
                label: field.label,
                type: field.type,
            })),
        add: (type: TFormBuilderType): string => {
            workspace.addField(paletteTemplate(type));
            return workspace.selectedFieldId.value ?? '';
        },
        duplicate: (id: string): string | null => {
            workspace.duplicateField(id);
            return workspace.selectedFieldId.value;
        },
        updateLabel: (id: string, label: string): void => {
            const field = models.formFields.value.find((entry: IBuilderField) => entry.id === id);
            if (field === undefined) return;
            workspace.updateField({ ...field, label });
        },
        remove: (id: string): void => {
            workspace.deleteField(id);
        },
        selectedId: (): string | null => workspace.selectedFieldId.value,
    };
}

function createDemoEntry(): IParityEntry {
    const demo = useFormBuilderDemoPage();

    return {
        fields: () =>
            demo.formFields.value.map((field: IFormField) => ({
                id: field.id,
                label: field.label,
                type: field.type,
            })),
        add: (type: TFormBuilderType): string => {
            demo.mobileFieldType.value = type;
            demo.addFieldFromPicker();
            return demo.selectedFieldId.value ?? '';
        },
        duplicate: (id: string): string | null => {
            demo.duplicateField(id);
            return demo.selectedFieldId.value;
        },
        updateLabel: (id: string, label: string): void => {
            const field = demo.formFields.value.find((entry: IFormField) => entry.id === id);
            if (field === undefined) return;
            demo.updateField({ ...field, label });
        },
        remove: (id: string): void => {
            demo.deleteField(id);
        },
        selectedId: (): string | null => demo.selectedFieldId.value,
    };
}

/** Urutan type+label adalah identitas yang stabil antar entry point (id acak). */
function shapeOf(entry: IParityEntry): string[] {
    return entry.fields().map((field: { label: string; type: string }): string => `${field.type}:${field.label}`);
}

describe('builder entry point parity', () => {
    it('add: urutan, jumlah, dan seleksi field baru sama di demo dan production', () => {
        const workspace = createWorkspaceEntry();
        const demo = createDemoEntry();

        const workspaceIds = [workspace.add('short_text'), workspace.add('email')];
        const demoIds = [demo.add('short_text'), demo.add('email')];

        expect(shapeOf(demo)).toEqual(shapeOf(workspace));
        expect(demoIds).toHaveLength(2);
        expect(workspaceIds).toHaveLength(2);
        expect(demo.selectedId()).toBe(demoIds[1]);
        expect(workspace.selectedId()).toBe(workspaceIds[1]);
    });

    it('duplicate: salinan disisipkan tepat setelah aslinya; hanya demo menambah suffix "(copy)"', () => {
        const workspace = createWorkspaceEntry();
        const demo = createDemoEntry();

        workspace.add('short_text');
        workspace.add('email');
        demo.add('short_text');
        demo.add('email');

        const workspaceOriginal = workspace.fields()[0].id;
        const demoOriginal = demo.fields()[0].id;

        expect(workspace.duplicate(workspaceOriginal)).toBe(workspace.fields()[1].id);
        expect(demo.duplicate(demoOriginal)).toBe(demo.fields()[1].id);

        expect(workspace.fields().map((field: { type: string }): string => field.type)).toEqual([
            'short_text',
            'short_text',
            'email',
        ]);
        expect(shapeOf(workspace)).toEqual(['short_text:Short Text', 'short_text:Short Text', 'email:Email']);
        // Perbedaan yang disengaja (adapter `decorateDuplicate` demo).
        expect(shapeOf(demo)).toEqual(['short_text:Short Text', 'short_text:Short Text (copy)', 'email:Email']);
    });

    it('update dan delete: field berubah/hilang dengan aturan seleksi yang sama', () => {
        const workspace = createWorkspaceEntry();
        const demo = createDemoEntry();

        workspace.add('short_text');
        workspace.add('email');
        demo.add('short_text');
        demo.add('email');

        const workspaceFirst = workspace.fields()[0].id;
        const demoFirst = demo.fields()[0].id;

        workspace.updateLabel(workspaceFirst, 'A baru');
        demo.updateLabel(demoFirst, 'A baru');
        expect(shapeOf(demo)).toEqual(shapeOf(workspace));

        // Hapus field terpilih → seleksi kosong di kedua entry point.
        const workspaceSelected = workspace.selectedId();
        const demoSelected = demo.selectedId();
        expect(workspaceSelected).not.toBeNull();
        expect(demoSelected).not.toBeNull();
        if (workspaceSelected !== null) workspace.remove(workspaceSelected);
        if (demoSelected !== null) demo.remove(demoSelected);
        expect(workspace.selectedId()).toBeNull();
        expect(demo.selectedId()).toBeNull();
        expect(shapeOf(demo)).toEqual(shapeOf(workspace));

        // Hapus field tidak terpilih tidak mengubah seleksi.
        workspace.add('number');
        demo.add('number');
        workspace.remove(workspace.fields()[0].id);
        demo.remove(demo.fields()[0].id);
        expect(workspace.selectedId()).toBe(workspace.fields()[0].id);
        expect(demo.selectedId()).toBe(demo.fields()[0].id);
        expect(shapeOf(demo)).toEqual(shapeOf(workspace));

        // Id yang tidak dikenal tidak mengubah apa pun.
        workspace.remove('tidak-ada');
        demo.remove('tidak-ada');
        expect(shapeOf(demo)).toEqual(shapeOf(workspace));
    });

    it('selectedField menunjuk field yang sama setelah duplikasi', () => {
        const demoEntry = createDemoEntry();
        demoEntry.add('short_text');
        demoEntry.duplicate(demoEntry.fields()[0].id);

        expect(demoEntry.fields().map((field: { label: string }): string => field.label)).toEqual([
            'Short Text',
            'Short Text (copy)',
        ]);

        const workspaceEntry = createWorkspaceEntry();
        workspaceEntry.add('short_text');
        workspaceEntry.duplicate(workspaceEntry.fields()[0].id);

        expect(workspaceEntry.fields().map((field: { label: string }): string => field.label)).toEqual([
            'Short Text',
            'Short Text',
        ]);
    });
});
