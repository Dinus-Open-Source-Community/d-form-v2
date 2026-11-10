import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import type { Html5Qrcode as THtml5Qrcode } from 'html5-qrcode';
import { isHttpRequestError, toHttpErrorKind } from '@/adapters/http/httpError';
import { fetchScanFeed, submitScan } from '@/adapters/http/scan';
import { playScanFeedback } from '@/adapters/browser/feedback';
import { findElementById } from '@/adapters/browser/domQuery';
import { readStorage, writeStorage } from '@/adapters/browser/storage';
import { humanizeErrorMessage, parseApiErrorMessage, showErrorToast } from '@/lib/error-message';
import { describeRequestError } from '@/lib/requestErrorText';
import {
    createScanHistoryEntry,
    extractQrCandidate,
    FEED_POLL_MS,
    nextFeedDelayMs,
    type IScanEntry,
    type IScanResult,
} from '@/lib/qrScanUi';
import type {
    IScanEnvelope,
    IScanErrorBody,
    IScanFeedPayload,
    IScanFeedRow,
    IScanTargetEvent,
    IScanTargetSession,
} from '@/types/scan';
import { formatDateTime } from '@/lib/dateTime';

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

type TFeedPollResult = 'success' | 'failed' | 'skipped';

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
const SCANNER_TEARDOWN_TIMEOUT_MS = 2000;

/** Jalankan operasi yang mungkin menggantung dengan batas waktu; kegagalan ditelan. */
async function settleWithTimeout(operation: () => Promise<void>, timeoutMs: number): Promise<void> {
    let timer: number | null = null;

    try {
        await Promise.race([
            operation(),
            new Promise<void>((resolve) => {
                timer = setTimeout(resolve, timeoutMs);
            }),
        ]);
    } catch {
        // Library melempar saat state tidak valid; teardown tetap dianggap best-effort.
    } finally {
        if (timer !== null) {
            clearTimeout(timer);
        }
    }
}

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

function formatGlobalEventTitle(kind: 'event' | 'oprec', rawTitle: string): string {
    const title = rawTitle.trim();
    if (title.length === 0) {
        return '-';
    }

    if (kind !== 'oprec') {
        return title;
    }

    return title.replace(/(\d{4}-\d{2}-\d{2})[T ]\d{2}:\d{2}(?::\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?/g, '$1');
}

function formatSessionDate(raw: string): string {
    const text: string = raw.trim();
    if (text.length === 0) {
        return '';
    }

    return formatDateTime({ value: text, variant: 'date-long' }) || text;
}

function sessionDivisionName(session: IScanTargetSession): string {
    const name = typeof session.division?.name === 'string' ? session.division.name.trim() : '';
    if (name.length > 0) {
        return name;
    }

    return 'Interview';
}

function sessionOptionLabel(session: IScanTargetSession): string {
    const divisionName = sessionDivisionName(session);
    const rawDate = typeof session.session_date === 'string' ? session.session_date.trim() : '';
    const date = formatSessionDate(rawDate);

    return date.length > 0 ? `${divisionName} · ${date}` : divisionName;
}

/**
 * Backend menyusun `eventTitle` oprec sebagai
 * `Oprec · {division.name} · {session_date}`. Kunci ini meniru bagian setelah
 * prefix "Oprec · " agar cocok dengan entri riwayat hasil scan.
 */
function sessionMatchKey(session: IScanTargetSession): string {
    const divisionName = sessionDivisionName(session);
    const rawDate = typeof session.session_date === 'string' ? session.session_date.trim() : '';

    return `${divisionName} · ${rawDate}`;
}

function eventOptionLabel(event: IScanTargetEvent): string {
    const title = typeof event.title === 'string' ? event.title.trim() : '';

    return title.length > 0 ? title : `Event ${String(event.id)}`;
}

function normalizeMatchText(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

function formatFeedTime(ts: string): string {
    const parsed = new Date(ts);
    if (Number.isNaN(parsed.getTime())) {
        return formatDateTime({ value: new Date().toISOString(), variant: 'time' });
    }

    return formatDateTime({ value: ts, variant: 'time' });
}

function scanIdentity(
    kind: 'event' | 'oprec',
    identifier: string,
    queueNumber: number | null,
    eventTitle: string
): string {
    return `${kind}|${eventTitle}|${identifier}|${queueNumber === null ? '-' : String(queueNumber)}`;
}

/** Memuat konstruktor html5-qrcode saat scanner dipakai agar tidak masuk bundel entry. */
async function loadHtml5Qrcode(): Promise<typeof import('html5-qrcode').Html5Qrcode> {
    const { Html5Qrcode } = await import('html5-qrcode');

    return Html5Qrcode;
}

/**
 * State + efek halaman scan QR global: daur hidup kamera, cooldown scan, dan feed
 * lintas meja dengan backoff saat gagal. Semua pembersihan dilakukan di unmount.
 */
export function useGlobalQrScanPage(
    scannerContainerId: string,
    storeUrl: string,
    feedUrl: string,
    getTargets: () => IGlobalScanTargets
) {
    const deskId = resolveDeskId();

    const scanner = ref<THtml5Qrcode | null>(null);
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
    let pollTimer: number | null = null;
    let pollAbort: AbortController | null = null;
    let feedPolling = false;
    let pauseFailureNotified = false;
    let shutterTimer: number | null = null;

    function isTodayEntry(entry: IScanEntry): boolean {
        const epoch: number | undefined = scanEntryEpochMs.get(entry.id);
        if (epoch === undefined) {
            return true;
        }

        return new Date(epoch).toDateString() === new Date().toDateString();
    }

    const targetOptions = computed<IGlobalScanTargetOption[]>(() => {
        const targets = getTargets();

        return [
            ...targets.sessions.map((session) => ({
                id: String(session.id),
                label: sessionOptionLabel(session),
                kind: 'oprec' as const,
                matchKey: sessionMatchKey(session),
            })),
            ...targets.events.map((event) => ({
                id: String(event.id),
                label: eventOptionLabel(event),
                kind: 'event' as const,
                matchKey: eventOptionLabel(event),
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

        const wanted = normalizeMatchText(option.matchKey);
        if (wanted.length === 0) {
            return [];
        }

        return scanHistory.value.filter((entry) => {
            if (entry.eventKind !== option.kind) {
                return false;
            }

            const haystack = normalizeMatchText(entry.eventTitle);
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

    function mapEnvelopeKind(type: string | undefined): 'event' | 'oprec' {
        return type === 'recruitment' ? 'oprec' : 'event';
    }

    function pushResult(result: IScanResult): void {
        localEntryIdentities.add(scanIdentity(result.eventKind, result.email, result.queueNumber, result.eventTitle));
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

        const kind: 'event' | 'oprec' = row.type === 'recruitment' ? 'oprec' : 'event';
        const name = row.name.trim().length > 0 ? row.name.trim() : 'Tanpa nama';
        const identifier = row.identifier.trim().length > 0 ? row.identifier.trim() : '-';
        const eventTitle = formatGlobalEventTitle(kind, row.eventTitle);

        if (localEntryIdentities.has(scanIdentity(kind, identifier, row.queueNumber, eventTitle))) {
            return;
        }

        const parsed = new Date(row.ts);
        const epoch = Number.isNaN(parsed.getTime()) ? Date.now() : parsed.getTime();

        const entry: IScanEntry = {
            id: row.id,
            name,
            email: identifier,
            time: formatFeedTime(row.ts),
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

    async function pollFeed(): Promise<TFeedPollResult> {
        if (pollAbort !== null) {
            return 'skipped';
        }

        const controller = new AbortController();
        pollAbort = controller;

        try {
            const payload = await fetchScanFeed({
                url: feedUrl,
                cursor: feedCursor,
                signal: controller.signal,
                timeoutMs: FEED_POLL_TIMEOUT_MS,
            });

            applyFeed(payload);
            feedFailureCount.value = 0;
            feedState.value = 'live';

            return 'success';
        } catch (error) {
            // Feed gagal sesaat tidak menghentikan polling; abort saat unmount bukan kegagalan.
            if (!(isHttpRequestError(error) && error.kind === 'aborted')) {
                feedFailureCount.value += 1;
                feedState.value = 'stale';
            }

            return 'failed';
        } finally {
            if (pollAbort === controller) {
                pollAbort = null;
            }
        }
    }

    /** Jadwalkan percobaan feed berikutnya; timer lama dibuang agar tidak ada dua rantai. */
    function scheduleFeedPoll(delayMs: number): void {
        if (pollTimer !== null) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }

        if (!feedPolling) {
            return;
        }

        pollTimer = setTimeout(() => {
            pollTimer = null;
            void runFeedPoll();
        }, delayMs);
    }

    async function runFeedPoll(): Promise<void> {
        if (!feedPolling) {
            return;
        }

        const outcome = await pollFeed();
        if (!feedPolling || outcome === 'skipped') {
            return;
        }

        scheduleFeedPoll(outcome === 'success' ? FEED_POLL_MS : nextFeedDelayMs(feedFailureCount.value));
    }

    function startFeedPolling(): void {
        if (feedPolling) {
            return;
        }

        feedPolling = true;
        void runFeedPoll();
    }

    function stopFeedPolling(): void {
        feedPolling = false;

        if (pollTimer !== null) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }

        if (pollAbort !== null) {
            pollAbort.abort();
            pollAbort = null;
        }
    }

    async function submitScanPayload(raw: string, source: 'camera' | 'manual'): Promise<void> {
        if (scanBusy.value) {
            return;
        }

        const trimmed = raw.trim();
        if (trimmed.length === 0) {
            showErrorToast('Isi kode registrasi terlebih dahulu.');

            return;
        }

        scanBusy.value = true;
        const rawDisplay = extractQrCandidate(trimmed);

        /** Catat satu kegagalan scan nyata; bentuk `IScanEntry` dan status `invalid` dipertahankan. */
        const pushFailureResult = (): void => {
            pushResult({
                name: 'Kesalahan jaringan',
                email: '-',
                status: 'invalid',
                source,
                rawCode: rawDisplay,
                eventKind: scanResult.value?.eventKind ?? 'event',
                eventTitle: scanResult.value?.eventTitle ?? '-',
                queueNumber: null,
            });
        };

        /** Tampilkan pesan spesifik bila ada; fallback generik hanya untuk error di luar kontrak HTTP. */
        const showFailureToast = (message: string | null): void => {
            if (message === null) {
                showErrorToast('Permintaan gagal', {
                    description: 'Coba lagi dalam beberapa saat.',
                });

                return;
            }

            showErrorToast(message);
        };

        /** `aborted` bukan kegagalan: scan yang dibatalkan tidak boleh membuat entri/toast palsu. */
        const pushGenericScanError = (error: unknown): void => {
            if (isHttpRequestError(error) && error.kind === 'aborted') {
                return;
            }

            pushFailureResult();
            showFailureToast(
                isHttpRequestError(error)
                    ? describeRequestError({
                          kind: error.kind,
                          status: error.status,
                          retryAfterSeconds: error.retryAfterSeconds,
                      })
                    : null
            );
        };

        const handleScanStatusError = (
            status: number,
            body: IScanErrorBody | null,
            retryAfterSeconds: number | null
        ): boolean => {
            if (status === 409) {
                const kind = mapEnvelopeKind(body?.type);
                const title = formatGlobalEventTitle(kind, body?.eventTitle ?? '');
                const fallbackQueue = scanResult.value?.eventKind === kind ? scanResult.value.queueNumber : null;
                const msg = humanizeErrorMessage(body?.message ?? 'Peserta sudah pernah scan.');

                if (kind === 'oprec') {
                    const identifier = body?.attendee?.registration_number?.trim() || '-';
                    const name = body?.attendee?.name?.trim() || 'Sudah terdaftar hadir';
                    pushResult({
                        name,
                        email: identifier,
                        status: 'already',
                        source,
                        rawCode: rawDisplay,
                        eventKind: kind,
                        eventTitle: title,
                        queueNumber: body?.attendee?.queue_number ?? fallbackQueue,
                    });
                    toast.warning(msg, {
                        description: `${name} · ${identifier}`,
                    });
                } else {
                    const email = body?.attendee?.email?.trim() || '-';
                    const name = body?.attendee?.name?.trim() || 'Sudah terdaftar hadir';
                    pushResult({
                        name,
                        email,
                        status: 'already',
                        source,
                        rawCode: rawDisplay,
                        eventKind: kind,
                        eventTitle: title,
                        queueNumber: null,
                    });
                    toast.warning(msg, {
                        description: email !== '-' ? `${name} · ${email}` : name,
                    });
                }

                return true;
            }

            if (status === 422) {
                const msg = parseApiErrorMessage(body, 'Data tidak valid.', status);
                pushResult({
                    name: 'Tidak dapat diproses',
                    email: '-',
                    status: 'invalid',
                    source,
                    rawCode: rawDisplay,
                    eventKind: scanResult.value?.eventKind ?? 'event',
                    eventTitle: scanResult.value?.eventTitle ?? '-',
                    queueNumber: null,
                });
                showErrorToast(msg);

                return true;
            }

            if (status === 429) {
                pushResult({
                    name: 'Terlalu banyak scan',
                    email: '-',
                    status: 'invalid',
                    source,
                    rawCode: rawDisplay,
                    eventKind: scanResult.value?.eventKind ?? 'event',
                    eventTitle: scanResult.value?.eventTitle ?? '-',
                    queueNumber: null,
                });
                showErrorToast(
                    describeRequestError({ kind: 'rate_limited', status, retryAfterSeconds }) ?? 'Terlalu banyak scan',
                    { description: 'Tunggu sebentar sebelum memindai lagi.' }
                );

                return true;
            }

            return false;
        };

        try {
            const selected = selectedTargetOption.value;
            const payload: Record<string, string> = { raw: trimmed, desk: deskId };
            if (selected?.kind === 'oprec') {
                payload.recruitment_session_id = selected.id;
            }

            const submitResult = await submitScan({ url: storeUrl, payload });
            const envelope = submitResult.envelope;

            if (submitResult.status >= 200 && submitResult.status < 300) {
                if (envelope === null) {
                    pushResult({
                        name: 'Respons tidak valid',
                        email: '-',
                        status: 'invalid',
                        source,
                        rawCode: rawDisplay,
                        eventKind: scanResult.value?.eventKind ?? 'event',
                        eventTitle: scanResult.value?.eventTitle ?? '-',
                        queueNumber: null,
                    });
                    showErrorToast('Respons server tidak sesuai kontrak.', {
                        description: 'Coba lagi atau hubungi admin.',
                    });

                    return;
                }

                const data: IScanEnvelope = envelope;
                const kind = mapEnvelopeKind(data.type);
                const title = formatGlobalEventTitle(kind, data.eventTitle ?? '');

                let result: IScanResult;

                if (kind === 'oprec') {
                    const identifier = data.attendee.registration_number?.trim() || '-';
                    const queueNumber = null;
                    result = {
                        name: data.attendee.name?.trim() || 'Tanpa nama',
                        email: identifier,
                        status: 'success',
                        source,
                        rawCode: rawDisplay,
                        eventKind: kind,
                        eventTitle: title,
                        queueNumber,
                    };
                    const statusLabel = data.attendee.interview_status_label ?? 'ruang tunggu';
                    toast.success(data.attendee.name?.trim() || 'Check-in berhasil.', {
                        description: `${statusLabel} — arahkan ke ruang tunggu`,
                    });
                } else {
                    const email = data.attendee.email?.trim() || '-';
                    result = {
                        name: data.attendee.name?.trim() || 'Tanpa nama',
                        email,
                        status: 'success',
                        source,
                        rawCode: rawDisplay,
                        eventKind: kind,
                        eventTitle: title,
                        queueNumber: null,
                    };
                    toast.success(data.attendee.name?.trim() || 'Check-in berhasil.', {
                        description: 'Boleh masuk — tiket dikirim ke email',
                    });
                }

                pushResult(result);

                if (source === 'manual') {
                    registrationCodeInput.value = '';
                }

                return;
            }

            if (handleScanStatusError(submitResult.status, submitResult.errorBody, submitResult.retryAfterSeconds)) {
                return;
            }

            pushFailureResult();
            showFailureToast(
                describeRequestError({
                    kind: toHttpErrorKind(submitResult.status),
                    status: submitResult.status,
                    retryAfterSeconds: submitResult.retryAfterSeconds,
                })
            );
        } catch (error) {
            pushGenericScanError(error);
        } finally {
            scanBusy.value = false;
        }
    }

    function clearShutterTimer(): void {
        if (shutterTimer !== null) {
            clearTimeout(shutterTimer);
            shutterTimer = null;
        }
    }

    function hideScannerNotice(): void {
        const container = findElementById(scannerContainerId);
        if (container === null) {
            return;
        }

        container.querySelectorAll<HTMLDivElement>(':scope > div').forEach((notice) => {
            notice.style.display = 'none';
        });
    }

    /**
     * Tutup instance scanner tanpa melempar; aman dipanggil saat unmount maupun
     * setelah resume gagal. `stop()` melempar bila scanner paused/berhenti, jadi
     * `stop()` dan `clear()` dibungkus terpisah.
     */
    async function teardownScanner(): Promise<void> {
        clearShutterTimer();
        isShutterActive.value = false;
        pauseFailureNotified = false;

        const active = scanner.value;
        // Null sebelum await: pemanggil berikutnya tidak boleh menganggap instance ini
        // masih aktif, walau stop() menggantung.
        scanner.value = null;
        isCameraReady.value = false;

        if (active === null) {
            return;
        }

        await settleWithTimeout(async () => {
            await active.stop();
        }, SCANNER_TEARDOWN_TIMEOUT_MS);

        await settleWithTimeout(async () => {
            active.clear();
        }, SCANNER_TEARDOWN_TIMEOUT_MS);
    }

    async function resumeScannerAfterShutter(): Promise<void> {
        const active = scanner.value;
        if (active === null || !isCameraReady.value) {
            return;
        }

        try {
            await active.resume();
        } catch {
            // Resume gagal berarti kamera berhenti; teardown nyata agar instance
            // tidak menggantung dan "Mulai kamera" bisa membuat sesi baru.
            await teardownScanner();
            toast.warning('Kamera terhenti. Klik "Mulai kamera" untuk melanjutkan.');
        }
    }

    function triggerShutter(): void {
        const active = scanner.value;
        if (active === null || !isCameraReady.value) {
            return;
        }

        isShutterActive.value = true;

        try {
            active.pause(true);
            hideScannerNotice();
        } catch {
            // Pause gagal bukan fatal: cooldown tetap berjalan. Beri tahu sekali per
            // sesi agar kegagalan tidak senyap tanpa membanjiri toast tiap scan.
            if (!pauseFailureNotified) {
                pauseFailureNotified = true;
                toast.warning('Kamera tidak dapat dijeda sementara.');
            }
        }

        clearShutterTimer();
        shutterTimer = setTimeout(() => {
            shutterTimer = null;
            isShutterActive.value = false;
            void resumeScannerAfterShutter();
        }, SCAN_COOLDOWN_MS);
    }

    function processScan(decodedText: string, source: 'camera' | 'manual'): void {
        if (source === 'camera' && (!isCameraReady.value || isShutterActive.value)) {
            return;
        }

        const now = Date.now();
        const key = decodedText.trim();
        if (key.length > 0 && key === lastRaw.value && now - lastAt.value < SCAN_COOLDOWN_MS) {
            return;
        }

        lastRaw.value = key;
        lastAt.value = now;

        if (source === 'camera') {
            triggerShutter();
        }

        void submitScanPayload(key, source);
    }

    /** Mengisi daftar kamera yang tersedia; memuat html5-qrcode secara lazy. */
    async function loadCameras(): Promise<void> {
        try {
            const Html5Qrcode = await loadHtml5Qrcode();
            const discoveredCameras = await Html5Qrcode.getCameras();
            cameras.value = discoveredCameras.map((camera, index) => ({
                id: camera.id,
                label: camera.label || `Camera ${index + 1}`,
            }));

            if (cameras.value.length > 0 && selectedCameraId.value.length === 0) {
                selectedCameraId.value = cameras.value[0].id;
            }

            permissionError.value = '';
        } catch (error) {
            permissionError.value = humanizeErrorMessage(
                'Gagal membaca daftar kamera. Pastikan browser punya izin kamera.'
            );
            showErrorToast('Kamera tidak tersedia', {
                description:
                    error instanceof Error
                        ? humanizeErrorMessage(error.message)
                        : 'Terjadi kesalahan saat mengakses kamera.',
            });
        }
    }

    /**
     * Mitigasi keterbatasan html5-qrcode: bila `start()` reject setelah `getUserMedia`,
     * `<video>` yatim bisa tetap memegang MediaStream. Hentikan track-nya best-effort.
     */
    function releaseStrayCameraStream(): void {
        const container = findElementById(scannerContainerId);
        const video = container?.querySelector('video');
        if (!video) {
            return;
        }

        const stream = video.srcObject;
        if (!(stream instanceof MediaStream)) {
            return;
        }

        for (const track of stream.getTracks()) {
            track.stop();
        }
        video.srcObject = null;
    }

    /** Memulai scanner kamera; html5-qrcode dimuat lazy pada titik ini. */
    async function startCameraScanner(): Promise<void> {
        if (isCameraReady.value || isStartingCamera.value) {
            return;
        }

        if (!selectedCameraId.value) {
            showErrorToast('Pilih kamera terlebih dahulu.');

            return;
        }

        isStartingCamera.value = true;
        permissionError.value = '';
        pauseFailureNotified = false;

        if (scanner.value !== null) {
            // Instance lama (mis. setelah resume gagal) harus ditutup sebelum membuat baru.
            await teardownScanner();
        }

        try {
            const Html5Qrcode = await loadHtml5Qrcode();
            const instance = new Html5Qrcode(scannerContainerId);
            scanner.value = instance;
            await instance.start(
                selectedCameraId.value,
                {
                    fps: 10,
                    qrbox: (viewfinderWidth: number, viewfinderHeight: number): { width: number; height: number } => ({
                        width: Math.floor(viewfinderWidth),
                        height: Math.floor(viewfinderHeight),
                    }),
                    aspectRatio: 1,
                },
                (decodedText) => processScan(decodedText, 'camera'),
                () => {}
            );

            if (scanner.value !== instance) {
                // Unmount/switch menutup instance ini saat start() masih pending:
                // tutup lagi agar track kamera tidak hidup tanpa handle.
                await settleWithTimeout(async () => {
                    await instance.stop();
                }, SCANNER_TEARDOWN_TIMEOUT_MS);
                await settleWithTimeout(async () => {
                    instance.clear();
                }, SCANNER_TEARDOWN_TIMEOUT_MS);

                return;
            }

            isCameraReady.value = true;
            toast.success('Kamera aktif', {
                description: 'Arahkan QR ke area scanner untuk check-in otomatis.',
            });
        } catch (error) {
            await teardownScanner();
            releaseStrayCameraStream();
            permissionError.value = humanizeErrorMessage(
                'Izin kamera ditolak atau kamera sedang digunakan aplikasi lain.'
            );
            showErrorToast('Tidak bisa memulai kamera', {
                description:
                    error instanceof Error
                        ? humanizeErrorMessage(error.message)
                        : 'Coba pilih kamera lain atau muat ulang halaman.',
            });
        } finally {
            isStartingCamera.value = false;
        }
    }

    async function stopCameraScanner(): Promise<void> {
        await teardownScanner();
    }

    async function switchCamera(nextCameraId: string | undefined): Promise<void> {
        if (nextCameraId === undefined) {
            return;
        }

        selectedCameraId.value = nextCameraId;

        if (!nextCameraId) {
            return;
        }

        if (!isCameraReady.value) {
            return;
        }

        await stopCameraScanner();
        await startCameraScanner();
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
        toast('Riwayat scan dibersihkan');
    }

    onMounted(loadCameras);
    onMounted(startFeedPolling);
    onBeforeUnmount(stopCameraScanner);
    onBeforeUnmount(stopFeedPolling);

    return {
        scannerContainerId,
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
