<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import SkeletonButton from './SkeletonButton.vue';
import SkeletonSearchField from './SkeletonSearchField.vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('flex flex-wrap items-center justify-between gap-3', props.class)">
        <SkeletonSearchField />
        <div class="flex items-center gap-2">
            <SkeletonButton class="w-28" />
            <SkeletonButton class="w-20" />
        </div>
    </div>
</template>
