<script setup lang="ts">
import { GripVertical } from 'lucide-vue-next'
import type { IconComponent } from '@/types/icons'

const props = withDefaults(
    defineProps<{
        type: string
        label: string
        icon: IconComponent
        description?: string
    }>(),
    { description: '' },
)

const onDragStart = (event: DragEvent) => {
    if (!event.dataTransfer || !(event.target instanceof Element)) return

    event.dataTransfer.effectAllowed = 'copy'
    event.dataTransfer.setData(
        'application/json',
        JSON.stringify({ type: props.type, label: props.label, isNew: true }),
    )
    event.dataTransfer.setDragImage(event.target, event.target.clientWidth / 2, 20)
}
</script>

<template>
    <div
        class="group flex cursor-grab items-center gap-2.5 rounded-xl border border-border bg-card px-3 py-2.5 shadow-xs transition-[transform,border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] select-none hover:-translate-y-px hover:border-primary/30 hover:bg-accent active:cursor-grabbing active:scale-[0.98]"
        draggable="true"
        @dragstart="onDragStart"
    >
        <div class="grid size-8 shrink-0 place-items-center rounded-lg border border-primary/15 bg-primary/8 text-primary transition-colors group-hover:border-primary/30 group-hover:bg-primary/12">
            <component :is="icon" class="size-4" :stroke-width="2" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-semibold text-foreground">{{ label }}</p>
            <p v-if="description" class="truncate text-[10px] leading-tight text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <GripVertical
            class="size-3.5 shrink-0 text-muted-foreground/30 transition-colors group-hover:text-muted-foreground/60"
        />
    </div>
</template>
