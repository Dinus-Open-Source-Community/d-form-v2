<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { TabsTrigger, type TabsTriggerProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<TabsTriggerProps & { class?: HTMLAttributes['class'] }>()
</script>

<template>
    <TabsTrigger
        data-slot="tabs-trigger"
        :class="cn('inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-transparent px-3 py-1 text-sm font-medium text-muted-foreground transition-[background-color,color,border-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/30 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:border-border data-[state=active]:bg-card data-[state=active]:text-foreground data-[state=active]:shadow-xs', props.class)"
        v-bind="{ ...props, class: undefined }"
    >
        <slot />
    </TabsTrigger>
</template>
