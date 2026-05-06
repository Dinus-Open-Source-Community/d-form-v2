<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{ class?: HTMLAttributes['class']; modelValue?: number; max?: number }>()
</script>

<template>
    <div data-slot="progress" :class="cn('relative h-2.5 w-full overflow-hidden rounded-full bg-muted', props.class)">
        <div
            class="h-full w-full flex-1 rounded-full bg-primary transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
            :style="{ transform: `translateX(-${100 - (Math.min(modelValue ?? 0, max ?? 100) / (max ?? 100)) * 100}%)` }"
        />
    </div>
</template>
