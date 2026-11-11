import { afterEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { useAutosaveSync, type IAutosaveStorage } from '@/hooks/useAutosaveSync';

interface IDeferred {
    promise: Promise<void>;
    resolve: () => void;
    reject: (reason?: unknown) => void;
}

/** Deferred tanpa assertion: resolve/reject diisi sinkron saat executor Promise berjalan. */
function createDeferred(): IDeferred {
    let resolveFn: () => void = () => {};
    let rejectFn: (reason?: unknown) => void = () => {};
    const promise = new Promise<void>((resolve, reject) => {
        resolveFn = resolve;
        rejectFn = reject;
    });
    return { promise, resolve: resolveFn, reject: rejectFn };
}

interface ISaveController {
    save: (snapshot: string) => Promise<void>;
    calls: string[];
    deferreds: IDeferred[];
}

/** `save` yang menahan setiap request sampai test mengendalikan deferred-nya. */
function createSaveController(): ISaveController {
    const calls: string[] = [];
    const deferreds: IDeferred[] = [];
    const save = (snapshot: string): Promise<void> => {
        calls.push(snapshot);
        const deferred = createDeferred();
        deferreds.push(deferred);
        return deferred.promise;
    };
    return { save, calls, deferreds };
}

const DEBOUNCE_MS = 50;

/** Window palsu yang merekam listener `pagehide` agar bisa dipicu manual. */
function stubPageLifecycle(): { fire: (type: string) => void } {
    const listeners = new Map<string, Array<() => void>>();
    vi.stubGlobal('window', {
        addEventListener: (type: string, handler: () => void): void => {
            listeners.set(type, [...(listeners.get(type) ?? []), handler]);
        },
        removeEventListener: (type: string, handler: () => void): void => {
            listeners.set(
                type,
                (listeners.get(type) ?? []).filter((entry: () => void): boolean => entry !== handler)
            );
        },
    });

    return {
        fire: (type: string): void => {
            for (const handler of listeners.get(type) ?? []) handler();
        },
    };
}

afterEach(() => {
    vi.clearAllTimers();
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('useAutosaveSync', () => {
    it('debounce: hanya menyimpan setelah debounceMs dan memakai snapshot terbaru', async () => {
        vi.useFakeTimers();
        let current = 'v1';
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => current, controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        expect(controller.calls).toEqual([]);

        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS - 10);
        expect(controller.calls).toEqual([]);

        current = 'v2';
        await vi.advanceTimersByTimeAsync(10);
        expect(controller.calls).toEqual(['v2']);
    });

    it('single-flight: dua flush yang tumpang tindih menghasilkan satu save aktif lalu satu follow-up', async () => {
        vi.useFakeTimers();
        let current = 'v1';
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => current, controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(controller.calls).toEqual(['v1']);

        const firstFlush = autosave.flush();
        const secondFlush = autosave.flush();
        expect(controller.calls).toEqual(['v1']);

        current = 'v2';
        controller.deferreds[0]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        expect(controller.calls).toEqual(['v1', 'v2']);

        controller.deferreds[1]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        await Promise.all([firstFlush, secondFlush]);
        expect(autosave.status.value).toBe('saved');
    });

    it('last-write-wins: perubahan snapshot saat save berjalan dikirim pada save terakhir', async () => {
        vi.useFakeTimers();
        let current = 'v1';
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => current, controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(controller.calls).toEqual(['v1']);

        current = 'v3';
        void autosave.flush();
        controller.deferreds[0]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        expect(controller.calls).toEqual(['v1', 'v3']);

        controller.deferreds[1]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        expect(controller.calls[controller.calls.length - 1]).toBe('v3');
        expect(autosave.status.value).toBe('saved');
    });

    it('error: status error + onError sekali, tanpa retry otomatis, retry() memulihkan ke saved', async () => {
        vi.useFakeTimers();
        const current = 'v1';
        const controller = createSaveController();
        const onError = vi.fn();
        const autosave = useAutosaveSync(() => current, controller.save, {
            debounceMs: DEBOUNCE_MS,
            onError,
        });

        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        controller.deferreds[0]?.reject(new Error('boom'));
        await vi.advanceTimersByTimeAsync(0);

        expect(autosave.status.value).toBe('error');
        expect(onError).toHaveBeenCalledTimes(1);
        expect(onError).toHaveBeenCalledWith('boom');

        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS * 4);
        expect(controller.calls).toEqual(['v1']);

        const retry = autosave.retry();
        await vi.advanceTimersByTimeAsync(0);
        expect(controller.calls).toEqual(['v1', 'v1']);
        controller.deferreds[1]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        await retry;
        expect(autosave.status.value).toBe('saved');
    });

    it('suspend/resume: perubahan saat suspend tidak dikirim, resume menyimpan satu kali dengan snapshot terbaru', async () => {
        vi.useFakeTimers();
        let current = 'v1';
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => current, controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.suspend();
        current = 'v2';
        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(controller.calls).toEqual([]);

        current = 'v3';
        autosave.schedule();
        void autosave.flush();
        expect(controller.calls).toEqual([]);

        autosave.resume();
        await vi.advanceTimersByTimeAsync(0);
        expect(controller.calls).toEqual(['v3']);

        controller.deferreds[0]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        expect(controller.calls).toEqual(['v3']);
        expect(autosave.status.value).toBe('saved');
    });

    it('isBlocked: status uploading, tidak ada save sampai unblocked', async () => {
        vi.useFakeTimers();
        const current = 'v1';
        const controller = createSaveController();
        const blocked = ref(true);
        const autosave = useAutosaveSync(() => current, controller.save, {
            debounceMs: DEBOUNCE_MS,
            blockedRetryMs: DEBOUNCE_MS,
            blockedWaitTimeoutMs: 1_000,
            isBlocked: blocked,
        });

        autosave.schedule();
        expect(autosave.status.value).toBe('uploading');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS + 60);
        expect(controller.calls).toEqual([]);

        blocked.value = false;
        await vi.advanceTimersByTimeAsync(200);
        expect(controller.calls).toEqual(['v1']);
        expect(autosave.status.value).toBe('saving');

        controller.deferreds[0]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        expect(autosave.status.value).toBe('saved');
    });

    it('cancel: membatalkan debounce yang belum jalan', async () => {
        vi.useFakeTimers();
        const current = 'v1';
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => current, controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        autosave.cancel();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS * 2);
        expect(controller.calls).toEqual([]);
    });

    it('waitForIdle: menunggu save aktif tanpa memicu save baru; saat idle langsung resolve', async () => {
        vi.useFakeTimers();
        const current = 'v1';
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => current, controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(controller.calls).toEqual(['v1']);

        let settled = false;
        const idle = autosave.waitForIdle().then((): void => {
            settled = true;
        });

        // Tidak memicu save baru dan belum selesai selama save aktif menggantung.
        expect(controller.calls).toEqual(['v1']);
        await vi.advanceTimersByTimeAsync(0);
        expect(settled).toBe(false);
        expect(controller.calls).toEqual(['v1']);

        controller.deferreds[0]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        await idle;

        expect(settled).toBe(true);
        expect(controller.calls).toEqual(['v1']);
        expect(autosave.status.value).toBe('saved');

        // Idle: resolve langsung tanpa memanggil save.
        await autosave.waitForIdle();
        expect(controller.calls).toEqual(['v1']);
    });

    it('storage: kegagalan tulis dilaporkan sekali sampai tulis berikutnya sukses', () => {
        vi.useFakeTimers();
        let shouldThrow = true;
        const storage: IAutosaveStorage = {
            read: () => null,
            write: () => {
                if (shouldThrow) throw new Error('quota');
            },
            remove: () => {},
        };
        const onError = vi.fn();
        const autosave = useAutosaveSync(
            () => 'v1',
            async () => {},
            {
                debounceMs: DEBOUNCE_MS,
                storage,
                storageKey: 'draft',
                onError,
            }
        );

        autosave.schedule();
        autosave.schedule();
        expect(onError).toHaveBeenCalledTimes(1);
        expect(onError).toHaveBeenCalledWith('quota');
        expect(autosave.status.value).toBe('idle');

        shouldThrow = false;
        autosave.schedule();
        shouldThrow = true;
        autosave.schedule();
        expect(onError).toHaveBeenCalledTimes(2);

        autosave.cancel();
    });

    it('snapshot: error serialisasi dilaporkan sebagai kegagalan menyiapkan data', () => {
        vi.useFakeTimers();
        let failSnapshot = false;
        const write = vi.fn();
        const storage: IAutosaveStorage = { read: () => null, write, remove: () => {} };
        const onError = vi.fn();
        const autosave = useAutosaveSync(
            () => {
                if (failSnapshot) throw new Error('circular');
                return 'v1';
            },
            async () => {},
            { debounceMs: DEBOUNCE_MS, storage, storageKey: 'draft', onError }
        );

        failSnapshot = true;
        autosave.schedule();

        expect(write).not.toHaveBeenCalled();
        expect(onError).toHaveBeenCalledWith('Gagal menyiapkan data yang akan disimpan.');
        expect(autosave.status.value).toBe('idle');

        autosave.cancel();
    });

    it('markSaved: error kembali ke saved; status idle tidak berubah', async () => {
        vi.useFakeTimers();
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => 'v1', controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.markSaved();
        expect(autosave.status.value).toBe('idle');

        autosave.schedule();
        void autosave.flush();
        await vi.advanceTimersByTimeAsync(0);
        controller.deferreds[0]?.reject(new Error('boom'));
        await vi.advanceTimersByTimeAsync(0);
        expect(autosave.status.value).toBe('error');

        autosave.markSaved();
        expect(autosave.status.value).toBe('saved');
    });

    it('edit setelah error memicu save berikutnya tanpa retry manual', async () => {
        vi.useFakeTimers();
        let current = 'v1';
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => current, controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        controller.deferreds[0]?.reject(new Error('boom'));
        await vi.advanceTimersByTimeAsync(0);
        expect(autosave.status.value).toBe('error');

        current = 'v2';
        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(controller.calls).toEqual(['v1', 'v2']);

        controller.deferreds[1]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        expect(autosave.status.value).toBe('saved');
    });

    it('suspend saat save berjalan membatalkan putaran tambahan; resume menyimpannya', async () => {
        vi.useFakeTimers();
        let current = 'v1';
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => current, controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(controller.calls).toEqual(['v1']);

        current = 'v2';
        void autosave.flush();
        autosave.suspend();

        controller.deferreds[0]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        // Putaran yang diantre sebelum suspend tidak dijalankan.
        expect(controller.calls).toEqual(['v1']);

        autosave.resume();
        await vi.advanceTimersByTimeAsync(0);
        expect(controller.calls).toEqual(['v1', 'v2']);

        controller.deferreds[1]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        expect(autosave.status.value).toBe('saved');
    });

    it('resume tanpa snapshot tertunda tidak menyimpan', async () => {
        vi.useFakeTimers();
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => 'v1', controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.suspend();
        autosave.resume();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS * 2);
        expect(controller.calls).toEqual([]);
    });

    it('suspend + waitForIdle saat debounce tertunda tidak memulai save', async () => {
        vi.useFakeTimers();
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => 'v1', controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        autosave.suspend();
        await autosave.waitForIdle();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS * 2);
        expect(controller.calls).toEqual([]);
    });

    it('blocked timeout memunculkan status error', async () => {
        vi.useFakeTimers();
        const controller = createSaveController();
        const blocked = ref(true);
        const autosave = useAutosaveSync(() => 'v1', controller.save, {
            debounceMs: DEBOUNCE_MS,
            isBlocked: blocked,
            blockedWaitTimeoutMs: 400,
            onError: () => undefined,
        });

        autosave.schedule();
        void autosave.flush();
        await vi.advanceTimersByTimeAsync(500);

        expect(autosave.status.value).toBe('error');
        expect(controller.calls).toEqual([]);
    });

    it('disabled setelah unblock mengembalikan status idle', async () => {
        vi.useFakeTimers();
        const controller = createSaveController();
        const blocked = ref(true);
        const enabled = ref(true);
        const autosave = useAutosaveSync(() => 'v1', controller.save, {
            debounceMs: DEBOUNCE_MS,
            enabled,
            isBlocked: blocked,
            blockedWaitTimeoutMs: 1_000,
        });

        autosave.schedule();
        void autosave.flush();
        await vi.advanceTimersByTimeAsync(0);
        expect(autosave.status.value).toBe('uploading');

        enabled.value = false;
        blocked.value = false;
        await vi.advanceTimersByTimeAsync(200);

        expect(autosave.status.value).toBe('idle');
        expect(controller.calls).toEqual([]);
    });
});

describe('useAutosaveSync unload guard', () => {
    it('hasUnsavedChanges: true saat edit tertunda, false setelah save sukses', async () => {
        vi.useFakeTimers();
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => 'v1', controller.save, { debounceMs: DEBOUNCE_MS });

        expect(autosave.hasUnsavedChanges.value).toBe(false);

        autosave.schedule();
        expect(autosave.hasUnsavedChanges.value).toBe(true);

        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        controller.deferreds[0]?.resolve();
        await vi.advanceTimersByTimeAsync(0);

        expect(autosave.hasUnsavedChanges.value).toBe(false);
    });

    it('edit baru saat save berjalan tetap tercatat belum tersimpan', async () => {
        vi.useFakeTimers();
        let current = 'v1';
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => current, controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(controller.calls).toEqual(['v1']);

        current = 'v2';
        autosave.schedule();
        controller.deferreds[0]?.resolve();
        await vi.advanceTimersByTimeAsync(0);

        // Masih ada debounce yang menunggu v2 → belum boleh dianggap bersih.
        expect(autosave.hasUnsavedChanges.value).toBe(true);

        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(controller.calls).toEqual(['v1', 'v2']);

        controller.deferreds[1]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        expect(autosave.hasUnsavedChanges.value).toBe(false);
    });

    it('pagehide mengirim snapshot final hanya saat ada perubahan belum tersimpan', async () => {
        vi.useFakeTimers();
        const pageLifecycle = stubPageLifecycle();
        let current = 'v1';
        const controller = createSaveController();
        const saveOnUnload = vi.fn();
        const autosave = useAutosaveSync(() => current, controller.save, {
            debounceMs: DEBOUNCE_MS,
            saveOnUnload,
        });

        // Form bersih: unload tidak boleh memicu request tambahan.
        pageLifecycle.fire('pagehide');
        expect(saveOnUnload).not.toHaveBeenCalled();

        autosave.schedule();
        current = 'v2';
        autosave.schedule();
        pageLifecycle.fire('pagehide');

        expect(saveOnUnload).toHaveBeenCalledTimes(1);
        expect(saveOnUnload).toHaveBeenCalledWith('v2');

        // Keepalive tidak menandai state bersih, tetapi debounce yang menunggu dibatalkan
        // agar snapshot yang sama tidak dikirim ulang lewat XHR (mis. saat restore bfcache).
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS * 2);
        expect(controller.calls).toEqual([]);
        expect(saveOnUnload).toHaveBeenCalledTimes(1);
    });

    it('pagehide tidak mengirim snapshot saat autosave disabled', async () => {
        vi.useFakeTimers();
        const pageLifecycle = stubPageLifecycle();
        const enabled = ref(true);
        const saveOnUnload = vi.fn();
        const autosave = useAutosaveSync(
            () => 'v1',
            async () => undefined,
            {
                debounceMs: DEBOUNCE_MS,
                enabled,
                saveOnUnload,
            }
        );

        autosave.schedule();
        enabled.value = false;
        pageLifecycle.fire('pagehide');

        expect(saveOnUnload).not.toHaveBeenCalled();
    });

    it('markSaved membersihkan tanda perubahan setelah manual save', async () => {
        vi.useFakeTimers();
        vi.stubGlobal('window', undefined);
        const autosave = useAutosaveSync(
            () => 'v1',
            async () => undefined,
            { debounceMs: DEBOUNCE_MS }
        );

        autosave.schedule();
        expect(autosave.hasUnsavedChanges.value).toBe(true);

        autosave.markSaved();
        expect(autosave.hasUnsavedChanges.value).toBe(false);
    });
});

describe('useAutosaveSync flush tanpa perubahan', () => {
    it('flush saat tidak ada edit tertunda tidak memulai save', async () => {
        vi.useFakeTimers();
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => 'v1', controller.save, { debounceMs: DEBOUNCE_MS });

        await autosave.flush();
        await vi.advanceTimersByTimeAsync(0);

        expect(controller.calls).toEqual([]);
        expect(autosave.hasUnsavedChanges.value).toBe(false);
    });

    it('flush setelah save sukses tidak mengirim putaran kedua', async () => {
        vi.useFakeTimers();
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => 'v1', controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        controller.deferreds[0]?.resolve();
        await vi.advanceTimersByTimeAsync(0);
        expect(controller.calls).toEqual(['v1']);

        // Navigasi eksplisit + unmount memanggil flush lagi: tidak boleh menambah request.
        await autosave.flush();
        await autosave.flush();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(controller.calls).toEqual(['v1']);
    });

    it('retry setelah error tetap berjalan meski status bukan navigating', async () => {
        vi.useFakeTimers();
        const controller = createSaveController();
        const autosave = useAutosaveSync(() => 'v1', controller.save, { debounceMs: DEBOUNCE_MS });

        autosave.schedule();
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        controller.deferreds[0]?.reject(new Error('boom'));
        await vi.advanceTimersByTimeAsync(0);
        expect(autosave.status.value).toBe('error');
        expect(autosave.hasUnsavedChanges.value).toBe(true);

        void autosave.retry();
        await vi.advanceTimersByTimeAsync(0);
        expect(controller.calls).toEqual(['v1', 'v1']);
    });

    it('sanitasi snapshot hanya untuk draft storage; save menerima snapshot mentah', async () => {
        vi.useFakeTimers();
        const writes: string[] = [];
        const snapshots: string[] = [];
        const storage: IAutosaveStorage = {
            read: () => null,
            write: (_key: string, value: string): void => {
                writes.push(value);
            },
            remove: () => {},
        };
        const raw = JSON.stringify({ bannerUrl: 'blob:https://dform.test/1', title: 'A' });
        const autosave = useAutosaveSync(
            () => raw,
            async (snapshot: string): Promise<void> => {
                snapshots.push(snapshot);
            },
            { debounceMs: DEBOUNCE_MS, storage, storageKey: 'draft' }
        );

        autosave.schedule();
        void autosave.flush();
        await vi.advanceTimersByTimeAsync(0);

        expect(JSON.parse(writes[0] ?? '{}')).toEqual({ bannerUrl: '', title: 'A' });
        expect(snapshots).toEqual([raw]);
    });
});
