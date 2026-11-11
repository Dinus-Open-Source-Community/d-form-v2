import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import type { Html5Qrcode as THtml5Qrcode } from 'html5-qrcode';
import { isHttpRequestError } from '@/adapters/http/httpError';
import { fetchScanFeed } from '@/adapters/http/scan';
import { playScanFeedback } from '@/adapters/browser/feedback';
import { readStorage, writeStorage } from '@/adapters/browser/storage';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { createScanHistoryEntry, type IScanEntry, type IScanResult } from '@/lib/qrScanUi';
import { createScanFeedPolling, type IScanFeedPolling } from '@/hooks/scan/useScanFeedPolling';
import { useScanCamera, type IScanCamera } from '@/hooks/scan/useScanCamera';
import { useScanSubmission } from '@/hooks/scan/useScanSubmission';
import {
    formatGlobalScanEventTitle,
    formatScanFeedTime,
    normalizeScanMatchText,
    scanEventOptionLabel,
    scanIdentity,
    scanKindFromApiType,
    scanSessionMatchKey,
    scanSessionOptionLabel,
} from '@/lib/qrScanFormat';
import type { IScanFeedPayload, IScanFeedRow, IScanTargetEvent, IScanTargetSession } from '@/types/scan';

export interface IGlobalScanTargets {
    sessions: IScanTargetSession[];
    events: IScanTargetEvent[];
}

export interface IGlobalScanTargetOption {
    id: string;
    label: string;
    kind: 'event' | 'oprec';
    /**
     * Teks pembanding yang dipakai untuk mencocokkan opsi filter dengan
     * `eventTitle` pada entri riwayat scan. Untuk event = judul event; untuk
     * oprec = "Divisi · tanggal" seperti yang dikirim backend pada `eventTitle`
     * (mis. "Oprec · Divisi Acara · 2026-05-01").
     */
    matchKey: string;
}

export interface IGlobalScanSummary {
    total: number;
    success: number;
    already: number;
    invalid: number;
}

const DESK_STORAGE_KEY = 'scan-desk-id';
const SCAN_COOLDOWN_MS = 2000;
const FEED_POLL_TIMEOUT_MS = 8000;
/**
 * Jumlah id baris feed terakhir yang disimpan untuk dedupe. Server memakai
 * kursor keyset sehingga tidak pernah mengirim ulang baris yang sudah terkirim;
 * dedupe ini hanya menutup pengiriman ganda di sisi client (respons poll yang
 * sama diproses dua kali setelah retry/timeout), jadi satu halaman feed terbaru
 * sudah cukup dan Set tidak perlu tumbuh sepanjang sesi meja.
 */
const FEED_SEEN_ID_LIMIT = 500;
/**
 * Batas tunggu `stop()`/`clear()` html5-qrcode saat teardown. Library bisa menggantung
 * bila transaksi state tidak pernah settle (mis. `start()` masih pending saat unmount);
 * tanpa batas ini `isStartingCamera` bisa tertahan `true` selamanya.
 */

function resolveDeskId(): string {
    const existing = readStorage({ area: 'session', key: DESK_STORAGE_KEY });
    if (existing !== null && existing.trim().length > 0) {
        return existing;
    }

    const fresh =
        typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
            ? crypto.randomUUID().slice(0, 8)
            : Math.random().toString(16).slice(2, 10);
    writeStorage({ area: 'session', key: DESK_STORAGE_KEY, value: fresh });

    return fresh;
}

/**
 * State + efek halaman scan QR global: daur hidup kamera, cooldown scan, dan feed
 * lintas meja dengan backoff saat gagal. Semua pembersihan dilakukan di unmount.
 */
export interface IGlobalQrScanPageOptions {
    scannerContainerId: string;
    storeUrl: string;
    feedUrl: string;
    getTargets: () => IGlobalScanTargets;
}

export function useGlobalQrScanPage(options: IGlobalQrScanPageOptions) {
    const { showErrorToast, showSuccessToast, showToast, showWarningToast } = useToastNotifications();
    const deskId = resolveDeskId();

    const scanner = shallowRef<THtml5Qrcode | null>(null);
    const cameras = ref<Array<{ id: string; label: string }>>([]);
    const selectedCameraId = ref('');
    const isCameraReady = ref(false);
    const isStartingCamera = ref(false);
    const permissionError = ref('');
    const registrationCodeInput = ref('');
    const scanResult = ref<IScanResult | null>(null);
    const scanHistory = ref<IScanEntry[]>([]);
    const lastRaw = ref('');
    const lastAt = ref(0);
    const scanBusy = ref(false);
    const selectedTarget = ref('all');
    const logExpanded = ref(false);
    const logQuery = ref('');
    const isShutterActive = ref(false);
    const feedState = ref<'live' | 'stale'>('live');
    const feedFailureCount = ref(0);

    const scanEntryEpochMs = new Map<string, number>();
    const rawCodeByEntryId = new Map<string, string>();
    const localEntryIdentities = new Set<string>();
    const seenFeedIds = new Set<string>();
    const seenFeedIdOrder: string[] = [];
    let feedCursor = '';

    function isTodayEntry(entry: IScanEntry): boolean {
        const epoch: number | undefined = scanEntryEpochMs.get(entry.id);
        if (epoch === undefined) {
            return true;
        }

        return new Date(epoch).toDateString() === new Date().toDateString();
    }

    const targetOptions = computed<IGlobalScanTargetOption[]>(() => {
        const targets = options.getTargets();

        return [
            ...targets.sessions.map((session) => ({
                id: String(session.id),
                label: scanSessionOptionLabel(session),
                kind: 'oprec' as const,
                matchKey: scanSessionMatchKey(session),
            })),
            ...targets.events.map((event) => ({
                id: String(event.id),
                label: scanEventOptionLabel(event),
                kind: 'event' as const,
                matchKey: scanEventOptionLabel(event),
            })),
        ];
    });

    const selectedTargetOption = computed<IGlobalScanTargetOption | null>(() => {
        if (selectedTarget.value === 'all') {
            return null;
        }

        return targetOptions.value.find((candidate) => candidate.id === selectedTarget.value) ?? null;
    });

    /** Nama acara yang sedang dipilih, atau "Semua acara" saat filter netral. */
    const selectedTargetLabel = computed<string>(() => selectedTargetOption.value?.label ?? 'Semua acara');

    /**
     * Filter acara bersifat global: KPI, hero "Hasil Scan Terakhir", dan riwayat
     * semuanya membaca dari `targetEntries`. Saat filter "Semua acara", perilaku
     * kembali ke seluruh riwayat. Pencarian `logQuery` tetap diterapkan terpisah di
     * QrScanSidebar sehingga filter acara dan pencarian bisa dipakai bersamaan.
     */
    const targetEntries = computed<IScanEntry[]>(() => {
        const option = selectedTargetOption.value;
        if (option === null) {
            return scanHistory.value;
        }

        const wanted = normalizeScanMatchText(option.matchKey);
        if (wanted.length === 0) {
            return [];
        }

        return scanHistory.value.filter((entry) => {
            if (entry.eventKind !== option.kind) {
                return false;
            }

            const haystack = normalizeScanMatchText(entry.eventTitle);
            if (haystack.length === 0) {
                return false;
            }

            // Event: judul harus sama persis. Oprec: `eventTitle` backend
            // berformat "Oprec · Divisi · tanggal", cukup dicocokkan sebagian.
            return option.kind === 'event' ? haystack === wanted : haystack.includes(wanted);
        });
    });

    const logEntries = computed<IScanEntry[]>(() => targetEntries.value);

    const todayEntries = computed<IScanEntry[]>(() => targetEntries.value.filter(isTodayEntry));
    const successfulScansCount = computed(
        () => todayEntries.value.filter((entry) => entry.status === 'success').length
    );
    const duplicateScansCount = computed(() => todayEntries.value.filter((entry) => entry.status === 'already').length);
    const invalidScansCount = computed(() => todayEntries.value.filter((entry) => entry.status === 'invalid').length);

    const summary = computed<IGlobalScanSummary>(() => ({
        total: todayEntries.value.length,
        success: successfulScansCount.value,
        already: duplicateScansCount.value,
        invalid: invalidScansCount.value,
    }));

    function toHeroResult(entry: IScanEntry): IScanResult {
        return {
            name: entry.name,
            email: entry.email,
            status: entry.status,
            source: entry.source,
            rawCode: rawCodeByEntryId.get(entry.id) ?? entry.email,
            eventKind: entry.eventKind,
            eventTitle: entry.eventTitle,
            queueNumber: entry.queueNumber,
        };
    }

    /**
     * Hero "Hasil Scan Terakhir" mengikuti filter yang sama dengan KPI dan riwayat.
     * Saat "Semua acara" tetap menampilkan scan terakhir yang baru diproses; saat
     * target dipilih, hero menampilkan scan terakhir yang cocok target tersebut
     * (bisa null meski ada scan lain di acara lain).
     */
    const heroResult = computed<IScanResult | null>(() => {
        if (selectedTargetOption.value === null) {
            return scanResult.value;
        }

        const latest = targetEntries.value[0];
        if (latest === undefined) {
            return null;
        }

        return toHeroResult(latest);
    });

    const eventLabel = computed(() => {
        const current = scanResult.value;
        if (current !== null && current.eventTitle !== '' && current.eventTitle !== '-') {
            return `${current.eventKind === 'oprec' ? 'OPREC' : 'EVENT'} · ${current.eventTitle}`;
        }

        return 'Siap — mode Semua';
    });

    function pushResult(result: IScanResult): void {
        localEntryIdentities.add(
            scanIdentity({
                kind: result.eventKind,
                identifier: result.email,
                queueNumber: result.queueNumber,
                eventTitle: result.eventTitle,
            })
        );
        scanResult.value = result;
        const entry: IScanEntry = createScanHistoryEntry(result);
        scanEntryEpochMs.set(entry.id, Date.now());
        rawCodeByEntryId.set(entry.id, result.rawCode);
        scanHistory.value.unshift(entry);
        playScanFeedback(result.status);
    }

    function ingestFeedRow(row: IScanFeedRow): void {
        if (seenFeedIds.has(row.id)) {
            return;
        }

        seenFeedIds.add(row.id);
        seenFeedIdOrder.push(row.id);

        if (seenFeedIdOrder.length > FEED_SEEN_ID_LIMIT) {
            const expired = seenFeedIdOrder.shift();

            if (expired !== undefined) {
                seenFeedIds.delete(expired);
            }
        }

        const kind = scanKindFromApiType(row.type);
        const name = row.name.trim().length > 0 ? row.name.trim() : 'Tanpa nama';
        const identifier = row.identifier.trim().length > 0 ? row.identifier.trim() : '-';
        const eventTitle = formatGlobalScanEventTitle(kind, row.eventTitle);

        if (localEntryIdentities.has(scanIdentity({ kind, identifier, queueNumber: row.queueNumber, eventTitle }))) {
            return;
        }

        const parsed = new Date(row.ts);
        const epoch = Number.isNaN(parsed.getTime()) ? Date.now() : parsed.getTime();

        const entry: IScanEntry = {
            id: row.id,
            name,
            email: identifier,
            time: formatScanFeedTime(row.ts),
            status: 'success',
            source: 'camera',
            eventKind: kind,
            eventTitle,
            queueNumber: row.queueNumber,
        };
        scanEntryEpochMs.set(entry.id, epoch);
        rawCodeByEntryId.set(entry.id, identifier);
        scanHistory.value.unshift(entry);
    }

    function applyFeed(payload: IScanFeedPayload): void {
        if (payload.cursor.length > 0) {
            feedCursor = payload.cursor;
        }

        for (const row of payload.rows) {
            ingestFeedRow(row);
        }
    }

    const feedPolling: IScanFeedPolling = createScanFeedPolling({
        fetchFeed: (signal: AbortSignal) =>
            fetchScanFeed({ url: options.feedUrl, cursor: feedCursor, signal, timeoutMs: FEED_POLL_TIMEOUT_MS }),
        onPayload: applyFeed,
        onSuccess: () => {
            feedFailureCount.value = 0;
            feedState.value = 'live';
        },
        onFailure: () => {
            feedFailureCount.value += 1;
            feedState.value = 'stale';
        },
        isAbortError: (error: unknown) => isHttpRequestError(error) && error.kind === 'aborted',
    });

    const submitScanPayload = useScanSubmission({
        storeUrl: options.storeUrl,
        deskId,
        selectedTarget: selectedTargetOption,
        latestResult: scanResult,
        registrationCodeInput,
        scanBusy,
        onResult: pushResult,
        showErrorToast,
        showSuccessToast,
        showWarningToast,
    });

    const camera: IScanCamera = useScanCamera({
        containerId: options.scannerContainerId,
        scanner,
        cameras,
        selectedCameraId,
        isCameraReady,
        isStartingCamera,
        permissionError,
        isShutterActive,
        onDecodedText: (decodedText: string): void => processScan(decodedText, 'camera'),
        showErrorToast,
        showSuccessToast,
        showWarningToast,
    });

    function processScan(decodedText: string, source: 'camera' | 'manual'): void {
        if (source === 'camera' && (!isCameraReady.value || isShutterActive.value)) return;
        const now = Date.now();
        const key = decodedText.trim();
        if (key.length > 0 && key === lastRaw.value && now - lastAt.value < SCAN_COOLDOWN_MS) return;
        lastRaw.value = key;
        lastAt.value = now;
        if (source === 'camera') camera.triggerShutter();
        void submitScanPayload(key, source);
    }

    async function startCameraScanner(): Promise<void> {
        await camera.start();
    }

    async function stopCameraScanner(): Promise<void> {
        await camera.stop();
    }

    async function switchCamera(nextCameraId: string | undefined): Promise<void> {
        await camera.switchCamera(nextCameraId);
    }

    function submitManualCode(): void {
        const code = registrationCodeInput.value.trim();

        if (code.length === 0) {
            showErrorToast('Isi kode registrasi terlebih dahulu.');

            return;
        }

        void submitScanPayload(code, 'manual');
    }

    function selectTarget(id: string): void {
        selectedTarget.value = id;
    }

    function clearHistory(): void {
        scanHistory.value = [];
        scanEntryEpochMs.clear();
        rawCodeByEntryId.clear();
        scanResult.value = null;
        showToast('Riwayat scan dibersihkan');
    }

    onMounted(() => {
        camera.activate();
        void camera.loadCameras();
    });
    onMounted(feedPolling.start);
    onBeforeUnmount(camera.deactivate);
    onBeforeUnmount(feedPolling.stop);

    return {
        scannerContainerId: options.scannerContainerId,
        cameras,
        selectedCameraId,
        isCameraReady,
        isStartingCamera,
        permissionError,
        registrationCodeInput,
        scanResult,
        heroResult,
        scanHistory,
        logEntries,
        eventLabel,
        successfulScansCount,
        duplicateScansCount,
        invalidScansCount,
        summary,
        selectedTarget,
        selectTarget,
        selectedTargetOption,
        selectedTargetLabel,
        logExpanded,
        logQuery,
        isShutterActive,
        feedState,
        feedFailureCount,
        targetOptions,
        scanBusy,
        processScan,
        submitScanPayload,
        startCameraScanner,
        stopCameraScanner,
        switchCamera,
        submitManualCode,
        clearHistory,
    };
}
