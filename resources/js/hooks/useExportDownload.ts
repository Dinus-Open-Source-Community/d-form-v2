import { computed, onUnmounted, ref, type ComputedRef, type Ref } from 'vue';
import { downloadWithProgress } from '@/adapters/browser/downloadProgress';

/** Fase unduhan satu batch export: `idle → running → completed | error`. */
export type TExportPhase = 'idle' | 'running' | 'completed' | 'error';

/** Jeda antar-unduhan pada satu batch; browser memblokir unduhan beruntun tanpa jeda. */
const BETWEEN_DOWNLOADS_MS = 400;

/** Hasil satu batch: file yang lewat stream dan file yang diserahkan ke browser. */
export interface IExportBatchSummary {
    streamCount: number;
    directCount: number;
}

export interface IUseExportDownload {
    phase: Ref<TExportPhase>;
    /** Persentase byte file aktif; `0` saat `indeterminate`. */
    progress: Ref<number>;
    /** `true` bila server tidak mengirim `Content-Length` (progres byte saja). */
    indeterminate: Ref<boolean>;
    /** Byte yang sudah diterima untuk file aktif. */
    loadedBytes: Ref<number>;
    /** Indeks file aktif pada batch (0-based) dan jumlah file batch. */
    currentIndex: Ref<number>;
    totalFiles: Ref<number>;
    /** Jumlah file yang diserahkan ke browser (`<a download>`), tanpa progres byte. */
    directCount: Ref<number>;
    errorMessage: Ref<string>;
    isRunning: ComputedRef<boolean>;
    /**
     * Unduh daftar URL berurutan lewat `downloadWithProgress`. Kembalian `null`
     * berarti batch dibatalkan atau gagal (lihat `phase`/`errorMessage`).
     */
    start: (urls: string[]) => Promise<IExportBatchSummary | null>;
    /** Batalkan unduhan aktif; state kembali `idle` tanpa error. */
    cancel: () => void;
}

function delay(ms: number): Promise<void> {
    return new Promise<void>((resolve): void => {
        globalThis.setTimeout(resolve, ms);
    });
}

function isAbortError(error: unknown): boolean {
    return error instanceof Error && error.name === 'AbortError';
}

/**
 * State unduhan tombol export: progres byte per file, pembatalan lewat
 * `AbortSignal`, dan batch berurutan (satu file per endpoint, mis. export scan
 * multi-acara). Nama file dan keputusan `stream`/`direct` tetap milik
 * `downloadWithProgress`; hook ini tidak pernah membaca isi file.
 *
 * Progres byte dihitung dari `loaded`/`total` adapter. `total` kosong (server
 * tidak mengirim `Content-Length`, seperti keempat endpoint export) berarti
 * unduhan **indeterminate**: UI menampilkan byte terkirim, bukan persentase palsu.
 */
export function useExportDownload(): IUseExportDownload {
    const phase = ref<TExportPhase>('idle');
    const progress = ref<number>(0);
    const indeterminate = ref<boolean>(false);
    const loadedBytes = ref<number>(0);
    const currentIndex = ref<number>(0);
    const totalFiles = ref<number>(0);
    const directCount = ref<number>(0);
    const errorMessage = ref<string>('');

    const isRunning = computed<boolean>(() => phase.value === 'running');

    let controller: AbortController | null = null;
    /** Token generasi: hasil batch lama diabaikan setelah batal/batch baru. */
    let generation = 0;

    function cancel(): void {
        controller?.abort();
        controller = null;
        generation += 1;
        phase.value = 'idle';
        progress.value = 0;
        indeterminate.value = false;
        loadedBytes.value = 0;
        currentIndex.value = 0;
        totalFiles.value = 0;
        directCount.value = 0;
        errorMessage.value = '';
    }

    async function start(urls: string[]): Promise<IExportBatchSummary | null> {
        const targets = urls.filter((url: string): boolean => url.trim() !== '');
        if (targets.length === 0) return null;

        // Batch sebelumnya dibatalkan; hanya satu batch aktif per tombol.
        cancel();
        const activeGeneration = generation;
        const activeController = new AbortController();
        controller = activeController;

        phase.value = 'running';
        currentIndex.value = 0;
        totalFiles.value = targets.length;

        let streamCount = 0;

        try {
            for (let index = 0; index < targets.length; index += 1) {
                if (index > 0) await delay(BETWEEN_DOWNLOADS_MS);
                if (generation !== activeGeneration) return null;

                currentIndex.value = index;
                progress.value = 0;
                indeterminate.value = false;
                loadedBytes.value = 0;

                const result = await downloadWithProgress(targets[index], {
                    signal: activeController.signal,
                    onProgress: (transfer): void => {
                        if (generation !== activeGeneration) return;

                        loadedBytes.value = transfer.loaded;

                        if (transfer.total === null) {
                            indeterminate.value = true;
                            progress.value = 0;

                            return;
                        }

                        indeterminate.value = false;
                        progress.value = Math.min(
                            100,
                            Math.max(0, Math.round((transfer.loaded / transfer.total) * 100))
                        );
                    },
                });

                if (generation !== activeGeneration) return null;

                if (result.mode === 'direct') {
                    directCount.value += 1;
                    indeterminate.value = false;
                    progress.value = 0;
                } else {
                    streamCount += 1;
                    indeterminate.value = result.indeterminate;
                }
            }

            phase.value = 'completed';

            return { streamCount, directCount: directCount.value };
        } catch (error) {
            if (generation !== activeGeneration) return null;

            if (isAbortError(error)) {
                cancel();
                return null;
            }

            phase.value = 'error';
            indeterminate.value = false;
            errorMessage.value =
                error instanceof Error && error.message.trim() !== '' ? error.message : 'Unduhan gagal.';

            return null;
        } finally {
            if (generation === activeGeneration) controller = null;
        }
    }

    onUnmounted(() => {
        controller?.abort();
        controller = null;
        generation += 1;
    });

    return {
        phase,
        progress,
        indeterminate,
        loadedBytes,
        currentIndex,
        totalFiles,
        directCount,
        errorMessage,
        isRunning,
        start,
        cancel,
    };
}
