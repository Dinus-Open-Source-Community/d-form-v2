<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

defineProps<{
    options: Record<string, { value: string; label: string }[]>;
}>();

const page = usePage();
</script>

<template>
    <div class="p-6">
        <h1 class="text-xl font-semibold">Create event</h1>
        <p class="mt-2 text-sm text-neutral-600">
            Placeholder — submit POST multipart to <code>dashboard.events.store</code> with fields from the PRD.
        </p>
        <pre class="mt-4 max-h-[50vh] overflow-auto rounded border border-neutral-200 bg-neutral-50 p-3 text-xs">{{ page.props }}</pre>
    </div>
</template>
