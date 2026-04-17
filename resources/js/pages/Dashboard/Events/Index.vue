<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

defineProps<{
    events: unknown;
    filterOptions: Record<string, { value: string; label: string }[]>;
    query: Record<string, unknown>;
}>();

const page = usePage();
</script>

<template>
    <div class="p-6">
        <h1 class="text-xl font-semibold">Events</h1>
        <p class="mt-2 text-sm text-neutral-600">
            Admin event list (Vue placeholder — wire forms and table in a follow-up).
        </p>
        <pre class="mt-4 max-h-[50vh] overflow-auto rounded border border-neutral-200 bg-neutral-50 p-3 text-xs">{{ page.props }}</pre>
    </div>
</template>
