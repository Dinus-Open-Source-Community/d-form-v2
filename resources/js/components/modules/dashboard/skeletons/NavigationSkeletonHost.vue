<script setup lang="ts">
import { onBeforeUnmount, onMounted, shallowRef, watch, type Component } from 'vue';
import type { TSkeletonKey } from '@/types/skeleton';
import SkeletonFallback from './SkeletonFallback.vue';
import { loadSkeletonScreen, preloadSkeletonScreens } from './skeletonRegistry';

const props = defineProps<{
    pending: boolean;
    skeletonKey: TSkeletonKey | null;
}>();

const screen = shallowRef<Component | null>(null);
let loadToken = 0;
let idleHandle: number | null = null;
let timeoutHandle: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.skeletonKey,
    async (key: TSkeletonKey | null): Promise<void> => {
        const token = ++loadToken;

        if (key === null) {
            screen.value = null;
            return;
        }

        const loaded = await loadSkeletonScreen(key);

        // Kunjungan yang lebih baru sudah menang; hasil lama dibuang.
        if (token === loadToken) screen.value = loaded;
    },
    { immediate: true }
);

/** Chunk skeleton dimuat saat browser idle agar siap sebelum pengguna berpindah halaman. */
function schedulePreload(): void {
    if (typeof requestIdleCallback === 'function') {
        idleHandle = requestIdleCallback(() => preloadSkeletonScreens(), { timeout: 2000 });
        return;
    }

    timeoutHandle = setTimeout(() => preloadSkeletonScreens(), 200);
}

onMounted(schedulePreload);

onBeforeUnmount(() => {
    if (idleHandle !== null) cancelIdleCallback(idleHandle);
    if (timeoutHandle !== null) clearTimeout(timeoutHandle);
});
</script>

<template>
    <div
        v-if="pending"
        data-testid="navigation-skeleton"
        :data-skeleton-key="skeletonKey ?? undefined"
        aria-busy="true"
        class="bg-background absolute inset-0 z-30 overflow-hidden p-4 md:p-6"
    >
        <span class="sr-only">Memuat halaman</span>
        <div aria-hidden="true" class="h-full">
            <component :is="screen ?? SkeletonFallback" />
        </div>
    </div>
</template>
