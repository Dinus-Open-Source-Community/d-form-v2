import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { HttpExceptionResponse } from '@inertiajs/core';
import { useDebounceFn } from '@vueuse/core';
import { routes } from '@/lib/routes';
import { parseRetryAfterSeconds } from '@/lib/http-retry-after';

/** Jeda ketik sebelum request dikirim, supaya setiap huruf tidak memicu request baru. */
const SEARCH_DEBOUNCE_MS = 350;
/** Backend mengabaikan kata kunci di bawah 3 karakter, jadi request sependek itu ditahan. */
const SEARCH_MIN_LENGTH = 3;
/** Cooldown cadangan saat respons 429 tidak membawa `Retry-After` yang bisa dibaca. */
const COOLDOWN_FALLBACK_SECONDS = 60;
/** Resolusi hitung mundur yang ditampilkan ke pengguna. */
const COOLDOWN_TICK_MS = 1000;

type THttpExceptionEvent = CustomEvent<{ response: HttpExceptionResponse }>;

export interface IEventSearchFilters {
    q: string | null;
}

export interface IEventSearchState {
    query: Ref<string>;
    cooldownSeconds: ComputedRef<number>;
    isCoolingDown: ComputedRef<boolean>;
    requestDebouncedSearch: () => void;
    resetSearch: () => void;
}

/** Baca durasi cooldown dari header `Retry-After`; pakai fallback bila kosong atau tidak valid. */
function readCooldownSeconds(response: HttpExceptionResponse): number {
    const header = response.headers['retry-after'];
    const raw = typeof header === 'string' ? header : null;
    const seconds = parseRetryAfterSeconds(raw);

    return seconds !== null && seconds > 0 ? seconds : COOLDOWN_FALLBACK_SECONDS;
}

/**
 * Kelola pencarian acara: input, debounce, dan cooldown saat limiter menolak (429).
 * Dipakai `EventList.vue` agar request tidak menumpuk selama rate limit aktif.
 */
export function useEventSearch(filters?: IEventSearchFilters | null): IEventSearchState {
    const initialQuery: string = filters?.q ?? '';
    const initialTerm: string = initialQuery.trim();
    const query = ref<string>(initialQuery);
    const cooldownUntil = ref<number>(0);
    const currentTime = ref<number>(Date.now());

    let countdownTimer: ReturnType<typeof setInterval> | null = null;
    let stopInvalidListener: (() => void) | null = null;
    /**
     * Term terakhir yang sudah dikirim. `null` berarti hook ini belum mengirim apa pun,
     * sehingga reset dari halaman tanpa filter tetap mengirim satu request.
     */
    let lastSentTerm: string | null = initialTerm === '' ? null : initialTerm;

    const cooldownSeconds = computed<number>(() =>
        Math.max(0, Math.ceil((cooldownUntil.value - currentTime.value) / 1000)),
    );
    const isCoolingDown = computed<boolean>(() => cooldownSeconds.value > 0);

    /** Hentikan hitung mundur yang sedang berjalan. */
    function stopCountdown(): void {
        if (countdownTimer === null) return;

        clearInterval(countdownTimer);
        countdownTimer = null;
    }

    /** Jalankan hitung mundur per detik selama cooldown; berhenti sendiri saat habis. */
    function startCountdown(): void {
        stopCountdown();
        countdownTimer = setInterval(() => {
            currentTime.value = Date.now();
            if (cooldownSeconds.value === 0) stopCountdown();
        }, COOLDOWN_TICK_MS);
    }

    /** Mulai cooldown sejumlah detik dan segarkan tampilan hitung mundurnya. */
    function beginCooldown(seconds: number): void {
        currentTime.value = Date.now();
        cooldownUntil.value = currentTime.value + seconds * 1000;
        startCountdown();
    }

    /**
     * Kirim request pencarian; kata kunci kosong mengirim tanpa `q`, 1–2 karakter ditahan.
     * Term yang sama dengan kiriman terakhir dilewati agar reset ganda tidak menggandakan request.
     */
    function sendSearch(term: string): void {
        const q = term.trim();
        if (q.length > 0 && q.length < SEARCH_MIN_LENGTH) return;
        if (q === lastSentTerm) return;

        lastSentTerm = q;

        router.get(routes.landing.events.index, q ? { q } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['events', 'pagination', 'filters', 'searchError'],
            // Visit yang gagal tidak boleh mengunci term yang sama: pengguna harus bisa
            // mencoba ulang kata kunci yang sama setelah gangguan jaringan/validasi.
            onError: (): void => {
                lastSentTerm = null;
            },
            onNetworkError: (): void => {
                lastSentTerm = null;
            },
        });
    }

    /** Tangkap respons 4xx/5xx agar term yang ditolak bisa dicoba ulang, lalu kunci saat 429. */
    function handleHttpException(event: THttpExceptionEvent): void {
        const response = event.detail.response;
        if (response.status < 400) return;

        // Server menolak/gagal sebelum hasil pencarian diproses: term ini belum
        // dianggap terkirim, sehingga percobaan ulang dengan term sama harus jalan.
        lastSentTerm = null;

        if (response.status !== 429) return;

        beginCooldown(readCooldownSeconds(response));
    }

    const requestDebouncedSearch = useDebounceFn((): void => {
        if (isCoolingDown.value) return;

        sendSearch(query.value);
    }, SEARCH_DEBOUNCE_MS);

    /** Batalkan debounce tertunda lalu muat ulang daftar tanpa filter. */
    function resetSearch(): void {
        requestDebouncedSearch.cancel();
        query.value = '';
        sendSearch('');
    }

    onMounted(() => {
        stopInvalidListener = router.on('httpException', handleHttpException);
    });

    onBeforeUnmount(() => {
        // Timer debounce yang masih hidup bisa menumpang navigasi dan menimpa halaman
        // tujuan setelah komponen ini dilepas.
        requestDebouncedSearch.cancel();
        stopInvalidListener?.();
        stopInvalidListener = null;
        stopCountdown();
    });

    return { query, cooldownSeconds, isCoolingDown, requestDebouncedSearch, resetSearch };
}
