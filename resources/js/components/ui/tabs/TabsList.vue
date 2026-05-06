<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { TabsList, type TabsListProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<TabsListProps & { class?: HTMLAttributes['class'] }>()
</script>

<template>
    <TabsList
        data-slot="tabs-list"
        :class="cn('inline-flex h-10 items-center justify-center gap-1 rounded-xl border border-border bg-muted/40 p-1 text-muted-foreground', props.class)"
        v-bind="{ ...props, class: undefined }"
    >
        <slot />
    </TabsList>
</template>
