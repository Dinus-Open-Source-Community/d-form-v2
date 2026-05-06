<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { SelectContent, type SelectContentProps, SelectPortal, SelectViewport } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<SelectContentProps & { class?: HTMLAttributes['class'] }>(), { position: 'popper' })
</script>

<template>
    <SelectPortal>
        <SelectContent
            data-slot="select-content"
            v-bind="{ ...props, class: undefined }"
            :class="cn('relative z-50 max-h-96 min-w-32 overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2', position === 'popper' && 'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1', props.class)"
        >
            <SelectViewport
                :class="
                    cn(
                        'p-1 max-h-96 overflow-y-auto',
                        position === 'popper' && 'w-full min-w-(--reka-select-trigger-width)',
                    )
                "
            >
                <slot />
            </SelectViewport>
        </SelectContent>
    </SelectPortal>
</template>
