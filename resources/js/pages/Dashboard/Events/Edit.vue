<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

defineProps<{
    event: Record<string, unknown>;
    options: Record<string, { value: string; label: string }[]>;
}>();

const page = usePage();
</script>

<template>
    <div class="p-6">
        <h1 class="text-xl font-semibold">Edit event</h1>
        <pre class="mt-4 max-h-[50vh] overflow-auto rounded border border-neutral-200 bg-neutral-50 p-3 text-xs">{{ page.props }}</pre>
    </div>
</template>
