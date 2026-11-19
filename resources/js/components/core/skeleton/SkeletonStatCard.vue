<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('flex flex-col gap-2 rounded-xl border border-border bg-card p-4', props.class)">
        <Skeleton class="h-3 w-24" />
        <Skeleton class="h-6 w-32" />
    </div>
</template>
