import { readWindow } from '@/adapters/browser/environment';

/** Area storage yang dipakai aplikasi. */
export type TStorageArea = 'local' | 'session';

export interface IStorageKeyOptions {
    area: TStorageArea;
    key: string;
}

export interface IStorageWriteOptions extends IStorageKeyOptions {
    value: string;
}

/** Ambil area storage; `null` saat SSR atau saat browser memblokir akses. */
function readStorageArea(area: TStorageArea): Storage | null {
    const browserWindow = readWindow();
    if (browserWindow === null) return null;

    try {
        return area === 'local' ? browserWindow.localStorage : browserWindow.sessionStorage;
    } catch {
        return null;
    }
}

/** Baca nilai storage; `null` bila tidak tersedia atau browser menolak. */
export function readStorage(options: IStorageKeyOptions): string | null {
    try {
        return readStorageArea(options.area)?.getItem(options.key) ?? null;
    } catch {
        return null;
    }
}

/** Tulis nilai storage; `false` bila storage tidak tersedia. */
export function writeStorage(options: IStorageWriteOptions): boolean {
    try {
        const storage = readStorageArea(options.area);
        if (storage === null) return false;

        storage.setItem(options.key, options.value);

        return true;
    } catch {
        return false;
    }
}

/** Hapus nilai storage; `false` bila storage tidak tersedia. */
export function removeStorage(options: IStorageKeyOptions): boolean {
    try {
        const storage = readStorageArea(options.area);
        if (storage === null) return false;

        storage.removeItem(options.key);

        return true;
    } catch {
        return false;
    }
}
