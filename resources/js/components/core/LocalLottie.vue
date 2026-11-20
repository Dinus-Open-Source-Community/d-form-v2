<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onWindowEvent } from '@/adapters/browser/domEvents';
import { readDocument } from '@/adapters/browser/environment';
import { matchesMediaQuery } from '@/adapters/browser/mediaQuery';
import { lotties, type TLottieName } from '@/lib/lotties';
import type { ILocalLottieProps } from '@/types/lottie';

// vue3-lottie dimuat terpisah agar tidak ikut bundel entry halaman lain.
const Vue3Lottie = defineAsyncComponent(() => import('vue3-lottie').then((module) => module.Vue3Lottie));

const props = withDefaults(defineProps<Omit<ILocalLottieProps, 'name'> & { name?: TLottieName }>(), {
    name: undefined,
    src: undefined,
    animationLink: '',
    height: 200,
    width: 200,
    loop: true,
    autoPlay: true,
    speed: 1,
    lazy: true,
});

const root = ref<HTMLElement | null>(null);
const visible = ref(false);
const playerAllowed = ref(true);
let observer: IntersectionObserver | null = null;
let idleHandle: number | null = null;
let idleTimeout: ReturnType<typeof setTimeout> | null = null;
let loadListenerRelease: (() => void) | null = null;

/** Tampilkan animasi sekarang; dipakai jalur eager setelah idle. */
function revealNow(): void {
    visible.value = true;
}

/** Idle callback atau fallback timer untuk jalur eager. */
function scheduleIdleReveal(): void {
    if (typeof requestIdleCallback === 'function') {
        idleHandle = requestIdleCallback(revealNow, { timeout: 2000 });
        return;
    }
    idleTimeout = setTimeout(revealNow, 300);
}

/** Tunggu load event agar Lottie tidak berebut main thread dengan paint LCP. */
function scheduleEagerReveal(): void {
    if (readDocument()?.readyState === 'complete') {
        scheduleIdleReveal();
        return;
    }
    loadListenerRelease = onWindowEvent(
        'load',
        () => {
            loadListenerRelease = null;
            scheduleIdleReveal();
        },
        { once: true }
    );
}

const resolvedLink = computed<string>(() => {
    if (props.animationLink) return props.animationLink;
    if (props.src) return props.src;
    if (props.name && lotties[props.name]) return lotties[props.name].src;
    return '';
});

const ariaLabel = computed<string>(() => {
    if (props.name && lotties[props.name]) return lotties[props.name].label;
    return 'Animated illustration';
});

const sizeStyle = computed(() => {
    const toCss = (v: number | string): string => (typeof v === 'number' ? `${v}px` : v);
    return {
        width: toCss(props.width),
        height: toCss(props.height),
    };
});

onMounted(() => {
    playerAllowed.value = !matchesMediaQuery('(max-width: 767px)');
    if (!props.lazy) {
        scheduleEagerReveal();
        return;
    }
    if (typeof IntersectionObserver === 'undefined') {
        visible.value = true;
        return;
    }
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    visible.value = true;
                    observer?.disconnect();
                    observer = null;
                    break;
                }
            }
        },
        { rootMargin: '120px' }
    );
    if (root.value) observer.observe(root.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
    if (loadListenerRelease !== null) {
        loadListenerRelease();
        loadListenerRelease = null;
    }
    if (idleHandle !== null) {
        cancelIdleCallback(idleHandle);
        idleHandle = null;
    }
    if (idleTimeout !== null) {
        clearTimeout(idleTimeout);
        idleTimeout = null;
    }
});

watch(
    () => props.lazy,
    (v) => {
        if (!v) visible.value = true;
    }
);
</script>

<template>
    <div
        ref="root"
        :style="sizeStyle"
        :aria-label="ariaLabel"
        role="img"
        class="relative inline-flex items-center justify-center"
    >
        <Vue3Lottie
            v-if="visible && resolvedLink && playerAllowed"
            :animation-link="resolvedLink"
            :height="height"
            :width="width"
            :loop="loop"
            :auto-play="autoPlay"
            :speed="speed"
            :no-margin="true"
        />
        <div
            v-else
            class="from-muted to-muted/40 h-full w-full animate-pulse rounded-2xl bg-gradient-to-br"
            aria-hidden="true"
        />
    </div>
</template>
