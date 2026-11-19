<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ErrorState from '@/components/core/ErrorState.vue';
import { resolveErrorStateContent } from '@/lib/stateContent';

const props = defineProps<{
    status: number;
}>();

const headTitle = computed<string>(() => `${props.status} · ${resolveErrorStateContent(props.status).title}`);
</script>

<template>
    <Head :title="headTitle" />

    <main
        class="bg-background text-foreground flex min-h-svh flex-col items-center justify-center px-6 py-16"
        :aria-label="`Error ${status}`"
    >
        <ErrorState :status="status" />
    </main>
</template>
