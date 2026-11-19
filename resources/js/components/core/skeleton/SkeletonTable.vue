<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        rows?: number;
        columns?: number;
        class?: HTMLAttributes['class'];
    }>(),
    { rows: 5, columns: 4 }
);

const gridStyle = computed<Record<string, string>>(() => ({
    gridTemplateColumns: `repeat(${String(props.columns)}, minmax(0, 1fr))`,
}));
</script>

<template>
    <div :class="cn('overflow-hidden rounded-xl border border-border bg-card', props.class)">
        <div class="grid gap-4 border-b border-border bg-muted/40 px-4 py-3" :style="gridStyle">
            <Skeleton v-for="column in props.columns" :key="column" class="h-3 w-2/3" />
        </div>
        <div
            v-for="row in props.rows"
            :key="row"
            class="grid gap-4 border-b border-border px-4 py-3 last:border-b-0"
            :style="gridStyle"
        >
            <Skeleton v-for="column in props.columns" :key="column" class="h-4 w-full" />
        </div>
    </div>
</template>
