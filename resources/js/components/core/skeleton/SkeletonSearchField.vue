<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Skeleton :class="cn('h-10 w-full max-w-72 rounded-lg', props.class)" />
</template>
