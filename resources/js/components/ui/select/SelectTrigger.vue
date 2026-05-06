<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { SelectTrigger, type SelectTriggerProps } from 'reka-ui'
import { ChevronDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = defineProps<SelectTriggerProps & { class?: HTMLAttributes['class'] }>()
</script>

<template>
    <SelectTrigger
        data-slot="select-trigger"
        v-bind="{ ...props, class: undefined }"
        :class="cn('flex h-10 w-full items-center justify-between gap-2 whitespace-nowrap rounded-xl border border-input bg-card px-3 py-2 text-sm font-medium text-foreground shadow-xs ring-offset-background transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] placeholder:text-muted-foreground hover:border-primary/30 focus:outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50 [&>span]:line-clamp-1 [&>span]:flex-1 [&>span]:text-left', props.class)"
    >
        <slot />
        <ChevronDown class="h-4 w-4 opacity-50" />
    </SelectTrigger>
</template>
