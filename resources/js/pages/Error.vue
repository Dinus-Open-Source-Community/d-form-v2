<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ErrorState from '@/components/core/ErrorState.vue';
import NavigationSkeletonHost from '@/components/modules/dashboard/skeletons/NavigationSkeletonHost.vue';
import { useNavigationSkeleton } from '@/hooks/useNavigationSkeleton';
import { resolveErrorStateContent } from '@/lib/stateContent';
import type { ISkeletonContext } from '@/types/skeleton';

const props = defineProps<{
    status: number;
}>();

const headTitle = computed<string>(() => `${props.status} · ${resolveErrorStateContent(props.status).title}`);

/**
 * Halaman error tidak memakai layout dashboard, jadi host skeleton dipasang di sini.
 * Tanpa ini, navigasi berikutnya dari halaman error tidak menampilkan skeleton.
 */
const page = usePage();
const { pending, skeletonKey } = useNavigationSkeleton({
    context: (): ISkeletonContext => ({
        canManageEvents: page.props.auth.user?.can_manage_events === true,
    }),
});
</script>

<template>
    <Head :title="headTitle" />

    <div class="relative min-h-svh">
        <main
            class="bg-background text-foreground flex min-h-svh flex-col items-center justify-center px-6 py-16"
            :aria-label="`Error ${status}`"
        >
            <ErrorState :status="status" />
        </main>

        <NavigationSkeletonHost :pending="pending" :skeleton-key="skeletonKey" />
    </div>
</template>
