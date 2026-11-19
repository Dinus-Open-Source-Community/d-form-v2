<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        lines?: number;
        class?: HTMLAttributes['class'];
    }>(),
    { lines: 3 }
);

const bodyLines = computed<number>(() => Math.max(0, props.lines - 1));
</script>

<template>
    <div :class="cn('flex flex-col gap-3 rounded-2xl border border-border bg-card p-4', props.class)">
        <Skeleton class="h-28 w-full rounded-xl" />
        <Skeleton class="h-4 w-2/3" />
        <Skeleton v-for="line in bodyLines" :key="line" class="h-3 w-full" />
    </div>
</template>
