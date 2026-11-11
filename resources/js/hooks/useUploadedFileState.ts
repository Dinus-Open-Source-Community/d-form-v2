import { onUnmounted, ref, type Ref } from 'vue';

export type TUploadedFilePhase = 'idle' | 'uploading' | 'completed' | 'error';

/** Hasil upload yang dibutuhkan kartu: nama/ukuran + URL server (bila ada). */
export interface IUploadedFileResult {
    fileName: string;
    fileSize: number;
    mime: string;
    /** URL server untuk pratinjau gambar (bukan object URL). */
    previewUrl?: string;
    /** Nilai yang disimpan pemanggil (path/id) — diteruskan apa adanya. */
    storedValue?: string;
}

/** Fungsi upload yang disediakan pemanggil (adapter yang sudah ada). */
export type TUploader = (
    file: File,
    options: { signal: AbortSignal; onProgress: (percent: number) => void }
) => Promise<IUploadedFileResult>;

export interface IUseUploadedFileStateOptions {
    upload: TUploader;
    /** Batas ukuran (KB) yang diperiksa sebelum request dibuat. */
    maxSizeKb?: number;
    onCompleted?: (result: IUploadedFileResult) => void;
    onError?: (message: string) => void;
}

export interface IUseUploadedFileStateResult {
    phase: Ref<TUploadedFilePhase>;
    progress: Ref<number>;
    fileName: Ref<string>;
    fileSize: Ref<number>;
    mime: Ref<string>;
    previewUrl: Ref<string>;
    errorMessage: Ref<string>;
    /** Mulai unggah; file yang sedang berjalan dibatalkan lebih dulu. */
    start: (file: File) => Promise<void>;
    /** Batalkan unggah aktif; state kembali `idle`. */
    cancel: () => void;
    /** Kosongkan state tanpa request (mis. tombol hapus). */
    reset: () => void;
}

/**
 * State machine satu file untuk kartu unggah: `idle → uploading → completed | error`.
 * - Ukuran dicek dari metadata `File` (tanpa membaca isi) sebelum request.
 * - Batalkan memakai `AbortController`; file yang sama tidak dibaca ulang.
 * - Tidak membuat `FileReader`/base64/object URL; pratinjau memakai URL server.
 */
export function useUploadedFileState(options: IUseUploadedFileStateOptions): IUseUploadedFileStateResult {
    const phase = ref<TUploadedFilePhase>('idle');
    const progress = ref<number>(0);
    const fileName = ref<string>('');
    const fileSize = ref<number>(0);
    const mime = ref<string>('');
    const previewUrl = ref<string>('');
    const errorMessage = ref<string>('');

    let controller: AbortController | null = null;
    /** Token generasi: hasil upload lama diabaikan saat file diganti/dibatalkan. */
    let generation = 0;

    function reset(): void {
        controller?.abort();
        controller = null;
        generation += 1;
        phase.value = 'idle';
        progress.value = 0;
        fileName.value = '';
        fileSize.value = 0;
        mime.value = '';
        previewUrl.value = '';
        errorMessage.value = '';
    }

    function cancel(): void {
        if (controller === null) {
            reset();
            return;
        }

        controller.abort();
        reset();
    }

    async function start(file: File): Promise<void> {
        // Unggahan sebelumnya dibatalkan; hanya satu file aktif per kartu.
        controller?.abort();
        const currentGeneration = generation + 1;

        generation = currentGeneration;
        controller = new AbortController();
        phase.value = 'uploading';
        progress.value = 0;
        fileName.value = file.name;
        fileSize.value = file.size;
        mime.value = file.type;
        previewUrl.value = '';
        errorMessage.value = '';

        if (options.maxSizeKb !== undefined && file.size > options.maxSizeKb * 1024) {
            phase.value = 'error';
            errorMessage.value = `Ukuran berkas melebihi batas ${options.maxSizeKb} KB.`;
            options.onError?.(errorMessage.value);
            controller = null;
            return;
        }

        try {
            const result = await options.upload(file, {
                signal: controller.signal,
                onProgress: (percent: number): void => {
                    if (generation !== currentGeneration) return;
                    progress.value = Math.min(100, Math.max(0, Math.round(percent)));
                },
            });

            if (generation !== currentGeneration) return;

            phase.value = 'completed';
            progress.value = 100;
            previewUrl.value = result.previewUrl ?? '';
            options.onCompleted?.(result);
        } catch (error) {
            if (generation !== currentGeneration) return;

            // Abort disengaja (Batalkan/ganti file) bukan error yang ditampilkan.
            if (error instanceof Error && error.name === 'AbortError') {
                reset();
                return;
            }

            phase.value = 'error';
            errorMessage.value =
                error instanceof Error && error.message !== '' ? error.message : 'Berkas gagal diunggah.';
            options.onError?.(errorMessage.value);
        } finally {
            if (generation === currentGeneration) controller = null;
        }
    }

    onUnmounted(() => {
        controller?.abort();
        controller = null;
    });

    return { phase, progress, fileName, fileSize, mime, previewUrl, errorMessage, start, cancel, reset };
}
