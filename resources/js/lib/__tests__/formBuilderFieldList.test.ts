import { describe, expect, it } from 'vitest';
import {
    createFormBuilderFieldList,
    parseFormBuilderDragPayload,
    type IFormBuilderFieldList,
} from '@/lib/formBuilderFieldList';
import { isFormBuilderFieldType } from '@/lib/formBuilderFieldType';

interface ITestField {
    id: string;
    label: string;
    name: string;
    type: string;
}

/** Factory deterministik: id berurutan agar urutan bisa diassert tanpa membaca randomUUID. */
function createFieldFactory(): (type: TFormBuilderType, label: string) => ITestField {
    let counter = 0;

    return (type: TFormBuilderType, label: string): ITestField => {
        counter += 1;
        return { id: `id-${counter}`, label, name: `field_${counter}`, type };
    };
}

/** Store in-memory sederhana; menggantikan `Ref` milik hook. */
function createStore(): { fields: ITestField[] } {
    return { fields: [] };
}

function createList(store: { fields: ITestField[] }): IFormBuilderFieldList<ITestField> {
    return createFormBuilderFieldList<ITestField>({
        store: {
            read: (): ITestField[] => store.fields,
            commit: (next: ITestField[]): void => {
                store.fields = next;
            },
        },
        createField: createFieldFactory(),
    });
}

describe('createFormBuilderFieldList', () => {
    it('appendField menambah field di akhir dengan urutan label yang benar', () => {
        const store = createStore();
        const list = createList(store);

        list.appendField('short_text', 'Pertama');
        list.appendField('email', 'Kedua');

        expect(list.indexOf('id-1')).toBe(0);
        expect(list.indexOf('id-2')).toBe(1);
        expect(list.indexOf('tidak-ada')).toBe(-1);
    });

    it('insertNewField menaruh field baru pada indeks drop dan mengklamp indeks di luar rentang', () => {
        const store = createStore();
        const list = createList(store);
        list.appendField('short_text', 'A');
        list.appendField('short_text', 'C');

        const inserted = list.insertNewField('email', 'B', 1);

        expect(list.indexOf(inserted.id)).toBe(1);
        expect(list.insertNewField('email', 'E', 99).id).toBe('id-4');
        expect(list.indexOf('id-4')).toBe(3);
    });

    it('duplicateField menyalin tepat setelah aslinya dengan id/nama baru', () => {
        const store = createStore();
        const list = createFormBuilderFieldList<ITestField>({
            store: {
                read: (): ITestField[] => store.fields,
                commit: (next: ITestField[]): void => {
                    store.fields = next;
                },
            },
            createField: createFieldFactory(),
            decorateDuplicate: (copy: ITestField, original: ITestField): ITestField => ({
                ...copy,
                label: `${original.label} (copy)`,
            }),
        });

        const original = list.appendField('dropdown', 'Pilihan');
        const copy = list.duplicateField(original.id);

        expect(copy).not.toBeNull();
        expect(store.fields).toHaveLength(2);
        expect(store.fields[1].label).toBe('Pilihan (copy)');
        expect(store.fields[1].id).not.toBe(original.id);
        expect(store.fields[1].name).not.toBe(original.name);
        expect(list.duplicateField('tidak-ada')).toBeNull();
    });

    it('updateField dan deleteField melaporkan id yang tidak ditemukan tanpa mengubah daftar', () => {
        const store = createStore();
        const list = createList(store);
        const field = list.appendField('short_text', 'A');

        expect(list.updateField({ ...field, label: 'Baru' })).toBe(true);
        expect(store.fields[0].label).toBe('Baru');
        expect(list.deleteField(field.id)).toBe(true);
        expect(list.deleteField(field.id)).toBe(false);
        expect(list.updateField({ ...field, label: 'Lagi' })).toBe(false);
        expect(store.fields).toHaveLength(0);
    });

    it('moveFieldToIndex memakai semantik gap drag: indeks tujuan dihitung sebelum sumber dilepas', () => {
        const store = createStore();
        const list = createList(store);
        list.appendField('short_text', 'A');
        list.appendField('short_text', 'B');
        list.appendField('short_text', 'C');

        // Geser A (indeks 0) ke gap setelah B (indeks 2) → urutan B, A, C.
        expect(list.moveFieldToIndex(0, 2)).toBe(true);
        expect(list.indexOf('id-1')).toBe(1);
        // Geser C (kini indeks 2) ke awal → C, B, A.
        expect(list.moveFieldToIndex(2, 0)).toBe(true);
        expect(list.indexOf('id-3')).toBe(0);
        expect(list.moveFieldToIndex(9, 0)).toBe(false);
        expect(list.moveFieldToIndex(-1, 0)).toBe(false);
    });

    it('finalizeCreatedField menempelkan transformasi sebelum field masuk daftar', () => {
        const store = createStore();
        const list = createFormBuilderFieldList<ITestField>({
            store: {
                read: (): ITestField[] => store.fields,
                commit: (next: ITestField[]): void => {
                    store.fields = next;
                },
            },
            createField: createFieldFactory(),
            finalizeCreatedField: (field: ITestField): ITestField => ({ ...field, type: `${field.type}:scoped` }),
        });

        const created = list.appendField('short_text', 'A');

        expect(created.type).toBe('short_text:scoped');
        expect(store.fields[0].type).toBe('short_text:scoped');
    });
});

describe('parseFormBuilderDragPayload', () => {
    it.each([
        [
            '{"isNew":true,"type":"short_text","label":"Short Text"}',
            { isNew: true, type: 'short_text', label: 'Short Text' },
        ],
        ['{"id":"abc","fromIndex":1,"isNew":false}', { isNew: false, id: 'abc' }],
    ])('membaca payload valid %s', (raw: string, expected: unknown) => {
        expect(parseFormBuilderDragPayload(raw)).toEqual(expected);
    });

    it.each(['', '{bukan json', 'null', '[]', '"teks"'])('menolak payload non-objek %j', (raw: string) => {
        expect(parseFormBuilderDragPayload(raw)).toBeNull();
    });

    it('membuang tipe field yang tidak dikenal', () => {
        expect(parseFormBuilderDragPayload('{"isNew":true,"type":"tidak_ada","label":"X"}')).toEqual({
            isNew: true,
            label: 'X',
        });
    });
});

describe('isFormBuilderFieldType', () => {
    it.each(['short_text', 'dropdown', 'image_upload', 'confirmation', 'yes_no'])('menerima %s', (type: string) => {
        expect(isFormBuilderFieldType(type)).toBe(true);
    });

    it.each([null, undefined, 1, '', 'tidak_ada', 'SHORT_TEXT'])('menolak %j', (value: unknown) => {
        expect(isFormBuilderFieldType(value)).toBe(false);
    });
});
