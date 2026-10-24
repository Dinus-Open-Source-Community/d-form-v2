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
    const query = ref<string>(filters?.q ?? '');
    const cooldownUntil = ref<number>(0);
    const currentTime = ref<number>(Date.now());

    let countdownTimer: number | null = null;
    let stopInvalidListener: (() => void) | null = null;

    const cooldownSeconds = computed<number>(() =>
        Math.max(0, Math.ceil((cooldownUntil.value - currentTime.value) / 1000)),
    );
    const isCoolingDown = computed<boolean>(() => cooldownSeconds.value > 0);

    /** Hentikan hitung mundur yang sedang berjalan. */
    function stopCountdown(): void {
        if (countdownTimer === null) return;

        window.clearInterval(countdownTimer);
        countdownTimer = null;
    }

    /** Jalankan hitung mundur per detik selama cooldown; berhenti sendiri saat habis. */
    function startCountdown(): void {
        stopCountdown();
        countdownTimer = window.setInterval(() => {
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

    /** Kirim request pencarian; kata kunci kosong mengirim tanpa `q`, 1–2 karakter ditahan. */
    function sendSearch(term: string): void {
        const q = term.trim();
        if (q.length > 0 && q.length < SEARCH_MIN_LENGTH) return;

        router.get(routes.landing.events.index, q ? { q } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['events', 'pagination', 'filters', 'searchError'],
        });
    }

    /** Tangkap respons 429 dari limiter lalu kunci pencarian sampai cooldown selesai. */
    function handleHttpException(event: THttpExceptionEvent): void {
        const response = event.detail.response;
        if (response.status !== 429) return;

        beginCooldown(readCooldownSeconds(response));
    }

    const requestDebouncedSearch = useDebounceFn((): void => {
        if (isCoolingDown.value) return;

        sendSearch(query.value);
    }, SEARCH_DEBOUNCE_MS);

    /** Kosongkan input lalu muat ulang daftar tanpa filter. */
    function resetSearch(): void {
        query.value = '';
        sendSearch('');
    }

    onMounted(() => {
        stopInvalidListener = router.on('httpException', handleHttpException);
    });

    onBeforeUnmount(() => {
        stopInvalidListener?.();
        stopInvalidListener = null;
        stopCountdown();
    });

    return { query, cooldownSeconds, isCoolingDown, requestDebouncedSearch, resetSearch };
}
