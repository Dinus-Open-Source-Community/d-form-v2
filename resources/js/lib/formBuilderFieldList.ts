import { isRecord } from '@/lib/isRecord';
import { isFormBuilderFieldType } from '@/lib/formBuilderFieldType';

/**
 * Operasi daftar field form builder yang dipakai bersama oleh entry point demo
 * (`useFormBuilderDemoPage`) dan production (`useFormBuilderWorkspace`).
 *
 * Modul ini murni terhadap framework: state daftar masuk lewat `read`/`commit`,
 * sehingga tidak ada import Vue/DOM dan operasi yang sama bisa diuji tanpa mount
 * komponen. Perbedaan kedua entry point sengaja dibatasi pada adapter:
 * - `createField`: default metadata field baru (demo memakai bentuk string,
 *   production memakai `options`/`metadata` yang dipetakan ke backend).
 * - `finalizeCreatedField`: production menempelkan section aktif ke field baru.
 * - `decorateDuplicate`: demo menambah suffix "(copy)" pada label salinan.
 * Semua mutasi lain (tambah, duplikat, update, hapus, pindah) memakai satu
 * implementasi ini agar perilaku demo dan production tidak menyimpang.
 */

/** Bagian field yang dibutuhkan operasi daftar. */
export interface IFormBuilderListedField {
    id: string;
    label: string;
    name: string;
}

/** Muatan `application/json` drag palette/kanvas builder. */
export interface IFormBuilderFieldDragPayload {
    isNew: boolean;
    id?: string;
    type?: TFormBuilderType;
    label?: string;
}

/** Akses daftar field yang dimiliki pemanggil (mis. `Ref` milik hook). */
export interface IFormBuilderFieldStore<T extends IFormBuilderListedField> {
    read: () => T[];
    commit: (next: T[]) => void;
}

export interface ICreateFormBuilderFieldListOptions<T extends IFormBuilderListedField> {
    store: IFormBuilderFieldStore<T>;
    createField: (type: TFormBuilderType, label: string) => T;
    finalizeCreatedField?: (field: T) => T;
    decorateDuplicate?: (copy: T, original: T) => T;
}

export interface IFormBuilderFieldList<T extends IFormBuilderListedField> {
    /** Indeks field di daftar; `-1` bila tidak ada. */
    indexOf: (id: string) => number;
    /** Tambah field baru di akhir daftar. */
    appendField: (type: TFormBuilderType, label: string) => T;
    /** Tambah field baru pada indeks drop (diklamp ke rentang daftar). */
    insertNewField: (type: TFormBuilderType, label: string, insertIndex: number) => T;
    /** Salin field `id` tepat setelah aslinya; `null` bila id tidak ada. */
    duplicateField: (id: string) => T | null;
    /** Ganti field berdasarkan id; `false` bila id tidak ada. */
    updateField: (field: T) => boolean;
    /** Hapus field berdasarkan id; `false` bila id tidak ada. */
    deleteField: (id: string) => boolean;
    /**
     * Pindahkan field dari `sourceIndex` ke `insertIndex` zona drop.
     * `insertIndex` dihitung sebelum field sumber dilepas (semantik drag gap).
     */
    moveFieldToIndex: (sourceIndex: number, insertIndex: number) => boolean;
}

/** Nama teknis unik per field (dipakai jawaban form). */
function createFieldName(): string {
    return `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`;
}

/** Indeks drop yang valid untuk daftar sepanjang `length`. */
function clampInsertIndex(index: number, length: number): number {
    if (!Number.isFinite(index)) return length;

    return Math.min(Math.max(0, index), length);
}

/**
 * Parser muatan drag builder. Payload JSON berasal dari `dataTransfer` browser,
 * jadi divalidasi runtime sebelum dipakai membuat/memindahkan field.
 */
export function parseFormBuilderDragPayload(raw: string): IFormBuilderFieldDragPayload | null {
    let parsed: unknown;

    try {
        parsed = JSON.parse(raw);
    } catch {
        return null;
    }

    if (!isRecord(parsed)) return null;

    const payload: IFormBuilderFieldDragPayload = { isNew: parsed.isNew === true };

    if (typeof parsed.id === 'string' && parsed.id !== '') payload.id = parsed.id;
    if (isFormBuilderFieldType(parsed.type)) payload.type = parsed.type;
    if (typeof parsed.label === 'string') payload.label = parsed.label;

    return payload;
}

export function createFormBuilderFieldList<T extends IFormBuilderListedField>(
    options: ICreateFormBuilderFieldListOptions<T>
): IFormBuilderFieldList<T> {
    const { store } = options;

    function indexOf(id: string): number {
        return store.read().findIndex((field: T): boolean => field.id === id);
    }

    function insert(field: T, insertIndex: number): T {
        const next = [...store.read()];
        next.splice(clampInsertIndex(insertIndex, next.length), 0, field);
        store.commit(next);

        return field;
    }

    function appendField(type: TFormBuilderType, label: string): T {
        const created = options.createField(type, label);
        const prepared = options.finalizeCreatedField === undefined ? created : options.finalizeCreatedField(created);

        return insert(prepared, store.read().length);
    }

    function insertNewField(type: TFormBuilderType, label: string, insertIndex: number): T {
        const created = options.createField(type, label);
        const prepared = options.finalizeCreatedField === undefined ? created : options.finalizeCreatedField(created);

        return insert(prepared, insertIndex);
    }

    function duplicateField(id: string): T | null {
        const index = indexOf(id);
        if (index === -1) return null;

        const list = store.read();
        const original = list[index];
        // JSON round-trip: aman untuk field reaktif Vue (proxy tidak bisa di-`structuredClone`)
        // dan identik dengan perilaku duplikasi sebelumnya.
        const copy: T = JSON.parse(JSON.stringify(original));
        copy.id = crypto.randomUUID();
        copy.name = createFieldName();

        const decorated = options.decorateDuplicate === undefined ? copy : options.decorateDuplicate(copy, original);

        const next = [...list];
        next.splice(index + 1, 0, decorated);
        store.commit(next);

        return decorated;
    }

    function updateField(field: T): boolean {
        const index = indexOf(field.id);
        if (index === -1) return false;

        const next = [...store.read()];
        next[index] = field;
        store.commit(next);

        return true;
    }

    function deleteField(id: string): boolean {
        const list = store.read();
        const next = list.filter((field: T): boolean => field.id !== id);
        if (next.length === list.length) return false;

        store.commit(next);

        return true;
    }

    function moveFieldToIndex(sourceIndex: number, insertIndex: number): boolean {
        const list = store.read();
        if (sourceIndex < 0 || sourceIndex >= list.length) return false;

        const next = [...list];
        const [moved] = next.splice(sourceIndex, 1);
        next.splice(clampInsertIndex(insertIndex > sourceIndex ? insertIndex - 1 : insertIndex, next.length), 0, moved);
        store.commit(next);

        return true;
    }

    return { indexOf, appendField, insertNewField, duplicateField, updateField, deleteField, moveFieldToIndex };
}
