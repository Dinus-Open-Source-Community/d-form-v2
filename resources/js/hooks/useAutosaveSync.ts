import { getCurrentInstance, onUnmounted, ref, watch, type Ref } from 'vue';
import { onPageHide } from '@/adapters/browser/pageLifecycle';
import { sanitizeBuilderSnapshot } from '@/lib/builderImageSanitizer';

export type TAutosaveStatus = 'idle' | 'saving' | 'saved' | 'uploading' | 'error';

export interface IAutosaveStorage {
    read(key: string): string | null;
    write(key: string, value: string): void;
    remove(key: string): void;
}

export interface IUseAutosaveSyncOptions {
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
    storage?: IAutosaveStorage;
    storageKey?: string;
    /** Kirim snapshot final saat halaman ditinggalkan bila masih ada perubahan belum tersimpan. */
    saveOnUnload?: (snapshot: string) => void;
}

export interface IUseAutosaveSyncResult {
    status: Ref<TAutosaveStatus>;
    /** True bila ada edit yang belum sukses tersimpan ke server. */
    hasUnsavedChanges: Ref<boolean>;
    /** Jadwalkan simpan remote setelah debounce; tulisan `storage` lokal langsung dieksekusi. */
    schedule: () => void;
    /** Jalankan save remote sekarang, satu request aktif; pemanggilan beruntun terkoalesasi. */
    flush: () => Promise<void>;
    /** Tunggu save yang sedang berjalan selesai tanpa memulai save baru atau putaran tambahan. */
    waitForIdle: () => Promise<void>;
    /**
     * Coba lagi setelah status `error`; setara `flush`. Panggil dari aksi user
     * (klik), bukan microtask yang berjalan sebelum drain gagal selesai, karena
     * putaran yang diantre saat save sedang gagal dibuang.
     */
    retry: () => Promise<void>;
    /** Tandai state sudah tersimpan di luar hook (mis. manual save sukses). */
    markSaved: () => void;
    /** Batalkan debounce yang belum jalan; tidak membatalkan request yang sedang berjalan. */
    cancel: () => void;
    /**
     * Blokir save remote sementara (mis. saat manual save); tulisan `storage` tetap
     * jalan. Debounce yang belum jalan dibatalkan tanpa ditandai tertunda — pemanggil
     * bertanggung jawab menyimpan state saat itu (manual save) atau memicu `flush`.
     */
    suspend: () => void;
    /** Buka blokir; jalankan satu save terkoalesasi bila ada snapshot tertunda. */
    resume: () => void;
    /**
     * Hentikan autosave permanen untuk halaman ini (target sudah tidak ada: 404/403/419).
     * Membatalkan debounce, menutup antrean, dan mencegah keepalive mengirim ulang.
     */
    fail: () => void;
}

/**
 * Auto-sync global: optimistik (UI berubah duluan) + debounce (remote belakangan).
 * Halaman menyuplai `source` (snapshot serial) dan `save` (cara menyimpan).
 *
 * Kepemilikan sanitasi (satu lapisan, tidak dobel):
 * - `source()` mengembalikan snapshot **mentah** (JSON) milik halaman.
 * - Hook menyanitasi hanya saat snapshot ditulis ke draft storage (`writeDraftSnapshot`).
 * - Payload remote/keepalive dibangun halaman dari state bertipe lalu disanitasi di
 *   boundary pemetaan (`toBackendFields` → `cleanStoredImageUrl`, settings `banner_url`),
 *   jadi `save`/`saveOnUnload` menerima snapshot mentah dan boleh mengabaikannya.
 *
 * Serialisasi: hanya satu `save` aktif. `flush` saat save berjalan menandai satu
 * putaran tambahan yang membaca snapshot terbaru (last write wins) tanpa abort
 * request yang sudah dikirim. Kegagalan menghentikan loop dan menunggu retry
 * eksplisit agar server yang sedang gagal tidak dihantam berulang. `flush` tanpa
 * edit tertunda tidak memulai putaran baru.
 *
 * `hasUnsavedChanges` + `saveOnUnload` menjaga edit terakhir saat halaman
 * ditinggalkan: `pagehide` mengirim snapshot lewat jalur `keepalive`, karena
 * XHR yang sedang berjalan dibatalkan browser pada reload/close.
 */
export function useAutosaveSync(
    source: () => string,
    save: (snapshot: string) => Promise<void>,
    opts: IUseAutosaveSyncOptions = {}
): IUseAutosaveSyncResult {
    const debounceMs = opts.debounceMs ?? 800;
    const blockedRetryMs = opts.blockedRetryMs ?? debounceMs;
    const blockedWaitTimeoutMs = opts.blockedWaitTimeoutMs ?? 30_000;
    const status = ref<TAutosaveStatus>('idle');
    const hasUnsavedChanges = ref(false);
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let runner: Promise<void> | null = null;
    let runAgain = false;
    let suspended = false;
    let terminal = false;
    let pendingWhileSuspended = false;
    let storageWriteFailed = false;
    let snapshotPrepareFailed = false;

    function isEnabled(): boolean {
        if (terminal) return false;
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

    function notifyError(message: string): void {
        (opts.onError ?? (() => {}))(message);
    }

    function reportError(err: unknown): void {
        notifyError(err instanceof Error ? err.message : 'Gagal menyimpan otomatis.');
    }

    /**
     * Tulis snapshot ke storage lokal. Sanitasi hanya di sini karena inilah satu-satunya
     * konsumen yang membutuhkannya: payload remote dibangun dari state bertipe dan
     * disanitasi di boundary pemetaan (`toBackendFields`/`cleanStoredImageUrl`), sehingga
     * tidak ada deep walk ganda yang hasilnya dibuang.
     */
    function writeDraftSnapshot(): void {
        if (!opts.storage || !opts.storageKey) return;

        let snapshot: string;
        try {
            snapshot = sanitizeBuilderSnapshot(source());
            snapshotPrepareFailed = false;
        } catch {
            status.value = 'idle';
            if (snapshotPrepareFailed) return;
            snapshotPrepareFailed = true;
            notifyError('Gagal menyiapkan data yang akan disimpan.');
            return;
        }

        try {
            opts.storage.write(opts.storageKey, snapshot);
            storageWriteFailed = false;
        } catch (err) {
            status.value = 'idle';
            if (storageWriteFailed) return;
            storageWriteFailed = true;
            reportError(err);
        }
    }

    /** Snapshot mentah dari halaman; sanitasi draft lokal ditangani `writeDraftSnapshot`. */
    function rawSnapshot(): string {
        return source();
    }

    function clearTimer(): void {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
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

    /** Satu putaran save. `false` berarti loop harus berhenti (gagal/disabled). */
    async function runOnce(): Promise<boolean> {
        if (!isEnabled()) return false;
        if (isBlocked()) {
            status.value = 'uploading';
            try {
                await waitForUnblocked();
            } catch (err) {
                status.value = 'error';
                reportError(err);
                return false;
            }
            if (!isEnabled()) {
                status.value = 'idle';
                return false;
            }
        }
        status.value = 'saving';
        let snapshot: string;
        try {
            snapshot = rawSnapshot();
        } catch {
            status.value = 'error';
            notifyError('Gagal menyiapkan data yang akan disimpan.');
            return false;
        }
        try {
            await save(snapshot);
            status.value = 'saved';
            return true;
        } catch (err) {
            status.value = 'error';
            reportError(err);
            return false;
        }
    }

    /** Loop single-flight: proses permintaan tambahan satu kali setelah save aktif selesai. */
    async function drain(): Promise<void> {
        try {
            while (true) {
                runAgain = false;
                const succeeded = await runOnce();
                if (!succeeded) break;
                if (!runAgain) {
                    // Save terakhir sukses: bersih hanya bila tidak ada edit yang menunggu debounce.
                    hasUnsavedChanges.value = debounceTimer !== null;
                    break;
                }
                if (suspended) {
                    // Putaran tambahan yang diantre sebelum suspend tidak boleh jalan;
                    // serahkan ke resume() agar tetap tersimpan setelah manual save.
                    runAgain = false;
                    pendingWhileSuspended = true;
                    break;
                }
            }
        } finally {
            runner = null;
        }
    }

    function requestRun(): Promise<void> {
        if (runner) {
            runAgain = true;
            return runner;
        }
        // Jalankan body drain di microtask agar `runner` sudah terisi sebelum
        // `save()` dipanggil; `flush()` sinkron dari dalam `save()` tidak memulai
        // drain kedua.
        runner = Promise.resolve().then(drain);
        return runner;
    }

    function schedule(): void {
        if (!isEnabled()) return;
        hasUnsavedChanges.value = true;
        writeDraftSnapshot();
        clearTimer();
        if (suspended) {
            pendingWhileSuspended = true;
            return;
        }
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

    function flush(): Promise<void> {
        const hasPendingWork = debounceTimer !== null || hasUnsavedChanges.value;
        clearTimer();
        if (!isEnabled()) return Promise.resolve();
        if (suspended) {
            if (hasPendingWork) pendingWhileSuspended = true;
            return Promise.resolve();
        }
        // Tidak ada edit tertunda → jangan mulai putaran save baru. Ini yang membuat
        // flush eksplisit saat navigasi tidak diikuti save duplikat dari unmount.
        if (!hasPendingWork) return Promise.resolve();
        hasUnsavedChanges.value = true;
        return requestRun();
    }

    function retry(): Promise<void> {
        return flush();
    }

    function markSaved(): void {
        hasUnsavedChanges.value = false;
        if (status.value === 'error') status.value = 'saved';
    }

    /** Tunggu save aktif selesai tanpa memulai save baru atau menandai putaran tambahan. */
    function waitForIdle(): Promise<void> {
        return runner ?? Promise.resolve();
    }

    function cancel(): void {
        clearTimer();
    }

    /** Kill switch: tidak ada save/keepalive berikutnya untuk halaman ini. */
    function fail(): void {
        terminal = true;
        clearTimer();
        runAgain = false;
        hasUnsavedChanges.value = false;
        status.value = 'error';
    }

    function suspend(): void {
        suspended = true;
        clearTimer();
    }

    function resume(): void {
        if (!suspended) return;
        suspended = false;
        if (pendingWhileSuspended) {
            pendingWhileSuspended = false;
            hasUnsavedChanges.value = true;
            void requestRun();
        }
    }

    watch(source, () => {
        schedule();
    });

    let stopPageHide: (() => void) | null = null;
    if (opts.saveOnUnload !== undefined) {
        const saveOnUnload = opts.saveOnUnload;
        stopPageHide = onPageHide((): void => {
            if (!hasUnsavedChanges.value || !isEnabled()) return;

            // Debounce yang menunggu dibatalkan: snapshot yang sama sudah dikirim lewat
            // jalur keepalive, sehingga restore bfcache tidak mengirim putaran ganda.
            clearTimer();

            try {
                saveOnUnload(rawSnapshot());
            } catch {
                // Snapshot gagal disiapkan saat halaman sudah ditinggalkan: tidak ada UI tersisa.
            }
        });

        const instance = getCurrentInstance();
        if (instance !== null) {
            onUnmounted((): void => {
                stopPageHide?.();
                stopPageHide = null;
            });
        }
    }

    return {
        status,
        hasUnsavedChanges,
        schedule,
        flush,
        waitForIdle,
        retry,
        markSaved,
        cancel,
        suspend,
        resume,
        fail,
    };
}
