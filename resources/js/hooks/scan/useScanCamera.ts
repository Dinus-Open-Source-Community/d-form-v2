import type { Ref } from 'vue';
import type { Html5Qrcode as THtml5Qrcode } from 'html5-qrcode';
import { findElementById } from '@/adapters/browser/domQuery';
import { humanizeErrorMessage } from '@/lib/error-message';

const SCAN_COOLDOWN_MS = 2000;
const SCANNER_TEARDOWN_TIMEOUT_MS = 2000;

export interface IScanCameraOption {
    containerId: string;
    scanner: Ref<THtml5Qrcode | null>;
    cameras: Ref<Array<{ id: string; label: string }>>;
    selectedCameraId: Ref<string>;
    isCameraReady: Ref<boolean>;
    isStartingCamera: Ref<boolean>;
    permissionError: Ref<string>;
    isShutterActive: Ref<boolean>;
    onDecodedText: (decodedText: string) => void;
    showErrorToast: (message: string, options?: { description?: string }) => void;
    showSuccessToast: (message: string, options?: { description?: string }) => void;
    showWarningToast: (message: string) => void;
}

export interface IScanCamera {
    activate: () => void;
    deactivate: () => void;
    loadCameras: () => Promise<void>;
    start: () => Promise<void>;
    stop: () => Promise<void>;
    switchCamera: (cameraId: string | undefined) => Promise<void>;
    triggerShutter: () => void;
}

async function loadHtml5Qrcode(): Promise<typeof import('html5-qrcode').Html5Qrcode> {
    const { Html5Qrcode } = await import('html5-qrcode');

    return Html5Qrcode;
}

async function settleWithTimeout(operation: () => Promise<void>): Promise<void> {
    let timer: ReturnType<typeof setTimeout> | null = null;

    try {
        await Promise.race([
            operation(),
            new Promise<void>((resolve) => {
                timer = setTimeout(resolve, SCANNER_TEARDOWN_TIMEOUT_MS);
            }),
        ]);
    } catch {
        // Scanner teardown is best effort because html5-qrcode can reject invalid states.
    } finally {
        if (timer !== null) clearTimeout(timer);
    }
}

/** Owns camera startup, cooldown, teardown, and stray MediaStream cleanup. */
export function useScanCamera(options: IScanCameraOption): IScanCamera {
    let shutterTimer: ReturnType<typeof setTimeout> | null = null;
    let pauseFailureNotified = false;
    let generation = 0;
    let mounted = false;

    const clearShutterTimer = (): void => {
        if (shutterTimer !== null) {
            clearTimeout(shutterTimer);
            shutterTimer = null;
        }
    };

    const hideScannerNotice = (): void => {
        const container = findElementById(options.containerId);
        container?.querySelectorAll<HTMLDivElement>(':scope > div').forEach((notice) => {
            notice.style.display = 'none';
        });
    };

    const releaseStrayCameraStream = (): void => {
        const container = findElementById(options.containerId);
        const video = container?.querySelector('video');
        if (video === null || video === undefined) return;
        const stream = video.srcObject;
        if (!(stream instanceof MediaStream)) return;
        stream.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    };

    const teardown = async (): Promise<void> => {
        clearShutterTimer();
        options.isShutterActive.value = false;
        pauseFailureNotified = false;
        const active = options.scanner.value;
        options.scanner.value = null;
        options.isCameraReady.value = false;
        if (active === null) return;
        await settleWithTimeout(() => active.stop());
        await settleWithTimeout(async () => active.clear());
    };

    const resumeAfterShutter = async (): Promise<void> => {
        const active = options.scanner.value;
        if (active === null || !options.isCameraReady.value) return;
        const resumeGeneration = generation;
        try {
            await active.resume();
        } catch {
            if (!mounted || generation !== resumeGeneration || options.scanner.value !== active) return;
            await teardown();
            options.showWarningToast('Kamera terhenti. Klik "Mulai kamera" untuk melanjutkan.');
        }
    };

    const triggerShutter = (): void => {
        const active = options.scanner.value;
        if (active === null || !options.isCameraReady.value) return;
        options.isShutterActive.value = true;
        try {
            active.pause(true);
            hideScannerNotice();
        } catch {
            if (!pauseFailureNotified) {
                pauseFailureNotified = true;
                options.showWarningToast('Kamera tidak dapat dijeda sementara.');
            }
        }
        clearShutterTimer();
        shutterTimer = setTimeout(() => {
            shutterTimer = null;
            options.isShutterActive.value = false;
            void resumeAfterShutter();
        }, SCAN_COOLDOWN_MS);
    };

    const loadCameras = async (): Promise<void> => {
        const loadGeneration = generation;
        try {
            const Html5Qrcode = await loadHtml5Qrcode();
            if (!mounted || generation !== loadGeneration) return;
            const discoveredCameras = await Html5Qrcode.getCameras();
            if (!mounted || generation !== loadGeneration) return;
            options.cameras.value = discoveredCameras.map((camera, index) => ({
                id: camera.id,
                label: camera.label || `Camera ${index + 1}`,
            }));
            const firstCamera = options.cameras.value[0];
            if (firstCamera !== undefined && options.selectedCameraId.value.length === 0) {
                options.selectedCameraId.value = firstCamera.id;
            }
            options.permissionError.value = '';
        } catch (error: unknown) {
            if (!mounted || generation !== loadGeneration) return;
            options.permissionError.value = humanizeErrorMessage(
                'Gagal membaca daftar kamera. Pastikan browser punya izin kamera.'
            );
            options.showErrorToast('Kamera tidak tersedia', {
                description:
                    error instanceof Error
                        ? humanizeErrorMessage(error.message)
                        : 'Terjadi kesalahan saat mengakses kamera.',
            });
        }
    };

    const start = async (): Promise<void> => {
        if (options.isCameraReady.value || options.isStartingCamera.value) return;
        if (!options.selectedCameraId.value) {
            options.showErrorToast('Pilih kamera terlebih dahulu.');
            return;
        }
        options.isStartingCamera.value = true;
        const startGeneration = ++generation;
        options.permissionError.value = '';
        pauseFailureNotified = false;
        if (options.scanner.value !== null) await teardown();
        if (!mounted || generation !== startGeneration) {
            options.isStartingCamera.value = false;
            return;
        }
        try {
            const Html5Qrcode = await loadHtml5Qrcode();
            if (!mounted || generation !== startGeneration) return;
            const instance = new Html5Qrcode(options.containerId);
            options.scanner.value = instance;
            await instance.start(
                options.selectedCameraId.value,
                {
                    fps: 10,
                    qrbox: (width: number, height: number): { width: number; height: number } => ({
                        width: Math.floor(width),
                        height: Math.floor(height),
                    }),
                    aspectRatio: 1,
                },
                options.onDecodedText,
                () => undefined
            );
            if (!mounted || generation !== startGeneration || options.scanner.value !== instance) {
                await settleWithTimeout(() => instance.stop());
                await settleWithTimeout(async () => instance.clear());
                return;
            }
            options.isCameraReady.value = true;
            options.showSuccessToast('Kamera aktif', {
                description: 'Arahkan QR ke area scanner untuk check-in otomatis.',
            });
        } catch (error: unknown) {
            await teardown();
            releaseStrayCameraStream();
            if (!mounted || generation !== startGeneration) return;
            options.permissionError.value = humanizeErrorMessage(
                'Izin kamera ditolak atau kamera sedang digunakan aplikasi lain.'
            );
            options.showErrorToast('Tidak bisa memulai kamera', {
                description:
                    error instanceof Error
                        ? humanizeErrorMessage(error.message)
                        : 'Coba pilih kamera lain atau muat ulang halaman.',
            });
        } finally {
            options.isStartingCamera.value = false;
        }
    };

    const stop = async (): Promise<void> => {
        generation += 1;
        await teardown();
    };

    const switchCamera = async (cameraId: string | undefined): Promise<void> => {
        if (cameraId === undefined) return;
        options.selectedCameraId.value = cameraId;
        if (!cameraId || !options.isCameraReady.value) return;
        await stop();
        await start();
    };

    return {
        activate: (): void => {
            mounted = true;
        },
        deactivate: (): void => {
            mounted = false;
            generation += 1;
            void stop();
        },
        loadCameras,
        start,
        stop,
        switchCamera,
        triggerShutter,
    };
}
