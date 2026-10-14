import { ref, watch, type Ref } from 'vue';
import { sanitizeBuilderSnapshot } from '@/utils/composables/useBuilderImageUpload';

export type AutosaveStatus = 'idle' | 'saving' | 'saved' | 'uploading';

export interface AutosaveStorage {
    read(key: string): string | null;
    write(key: string, value: string): void;
    remove(key: string): void;
}

export interface UseAutosaveSyncOptions {
    debounceMs?: number;
    enabled?: Ref<boolean> | boolean;
    /**
     * True bila penyimpanan harus ditunda (mis. upload gambar builder berjalan).
     * `schedule` menampilkan status `uploading` + mencoba lagi; `flush` menunggu
     * hingga tidak terblokir sebelum menyimpan.
     */
    isBlocked?: Ref<boolean> | (() => boolean) | boolean;
    /** Jeda antar percobaan ulang saat terblokir (default ikut debounceMs). */
    blockedRetryMs?: number;
    /** Batas tunggu `flush` saat terblokir (default 30 detik). */
    blockedWaitTimeoutMs?: number;
    onError?: (message: string) => void;
    storage?: AutosaveStorage;
    storageKey?: string;
}

export interface UseAutosaveSyncResult {
    status: Ref<AutosaveStatus>;
    schedule: () => void;
    flush: () => Promise<void>;
    cancel: () => void;
}

/**
 * Auto-sync global: optimistik (UI berubah duluan) + debounce (remote belakangan).
 * Halaman menyuplai `source` (snapshot serial) dan `save` (cara menyimpan).
 * Snapshot selalu disanitasi (strip `data:`/`blob:` URL) agar DB tidak menerima base64.
 */
export function useAutosaveSync(
    source: () => string,
    save: (snapshot: string) => Promise<void>,
    opts: UseAutosaveSyncOptions = {},
): UseAutosaveSyncResult {
    const debounceMs = opts.debounceMs ?? 800;
    const blockedRetryMs = opts.blockedRetryMs ?? debounceMs;
    const blockedWaitTimeoutMs = opts.blockedWaitTimeoutMs ?? 30_000;
    const status = ref<AutosaveStatus>('idle');
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let saveSeq = 0;

    function isEnabled(): boolean {
        if (typeof opts.enabled === 'boolean') return opts.enabled;
        if (opts.enabled) return opts.enabled.value;
        return true;
    }

    function isBlocked(): boolean {
        if (typeof opts.isBlocked === 'boolean') return opts.isBlocked;
        if (typeof opts.isBlocked === 'function') return opts.isBlocked();
        if (opts.isBlocked) return opts.isBlocked.value;
        return false;
    }

    /** Snapshot bersih: `data:`/`blob:` URL (preview lokal) tidak ikut tersimpan. */
    function cleanSnapshot(): string {
        return sanitizeBuilderSnapshot(source());
    }

    function clearTimer(): void {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
    }

    function schedule(): void {
        if (!isEnabled()) return;
        if (opts.storage && opts.storageKey) {
            opts.storage.write(opts.storageKey, cleanSnapshot());
        }
        clearTimer();
        if (isBlocked()) {
            status.value = 'uploading';
            debounceTimer = setTimeout(() => {
                void flush();
            }, blockedRetryMs);
            return;
        }
        debounceTimer = setTimeout(() => {
            void flush();
        }, debounceMs);
    }

    /** Tunggu hingga tidak terblokir (upload selesai) atau timeout. */
    function waitForUnblocked(): Promise<void> {
        if (!isBlocked()) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const startedAt = Date.now();
            const timer = setInterval(() => {
                if (!isBlocked()) {
                    clearInterval(timer);
                    resolve();
                    return;
                }
                if (Date.now() - startedAt >= blockedWaitTimeoutMs) {
                    clearInterval(timer);
                    reject(new Error('Masih ada unggahan gambar yang berjalan. Coba lagi.'));
                }
            }, 150);
        });
    }

    async function flush(): Promise<void> {
        clearTimer();
        if (!isEnabled()) return;
        const seq = ++saveSeq;
        if (isBlocked()) {
            status.value = 'uploading';
            try {
                await waitForUnblocked();
            } catch (err) {
                if (seq === saveSeq) {
                    status.value = 'idle';
                    (opts.onError ?? (() => {}))(err instanceof Error ? err.message : 'Gagal menyimpan otomatis.');
                }
                return;
            }
            if (!isEnabled()) return;
        }
        status.value = 'saving';
        try {
            await save(cleanSnapshot());
            if (seq === saveSeq) status.value = 'saved';
        } catch (err) {
            if (seq === saveSeq) {
                status.value = 'idle';
                (opts.onError ?? (() => {}))(err instanceof Error ? err.message : 'Gagal menyimpan otomatis.');
            }
        }
    }

    function cancel(): void {
        clearTimer();
    }

    watch(source, () => {
        schedule();
    });

    return { status, schedule, flush, cancel };
}
