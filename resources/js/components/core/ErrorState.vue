<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppStatePanel from '@/components/core/AppStatePanel.vue';
import { Button } from '@/components/ui/button';
import { goBack as goBackInHistory } from '@/adapters/browser/navigation';
import { routes } from '@/lib/routes';
import { resolveErrorStateContent } from '@/lib/stateContent';
import type { IStateContent } from '@/types/state';

const props = defineProps<{
    status: number;
}>();

const content = computed<IStateContent>(() => resolveErrorStateContent(props.status));

/** Kembali ke riwayat bila ada; selain itu buka beranda. */
function goBack(): void {
    if (goBackInHistory()) return;
    router.visit(routes.home);
}
</script>

<template>
    <AppStatePanel
        illustration="error"
        variant="page"
        heading-tag="h1"
        :title="content.title"
        :description="content.description"
    >
        <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
            <Button type="button" size="lg" class="min-w-[11rem]" @click="goBack">Kembali</Button>
            <a
                :href="routes.home"
                class="text-primary inline-flex h-11 items-center px-2 text-sm font-medium underline-offset-4 hover:underline"
            >
                Buka beranda
            </a>
        </div>
    </AppStatePanel>
</template>
