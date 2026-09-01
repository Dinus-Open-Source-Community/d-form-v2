<script setup lang="ts">
import { ref } from 'vue'
import type { IconComponent } from '@/types/icons'
import { GripVertical } from 'lucide-vue-next'

const props = withDefaults(
    defineProps<{
        type: string
        label: string
        icon?: IconComponent
        description?: string
    }>(),
    { description: '' },
)

const isDragging = ref(false)

const onDragStart = (event: DragEvent) => {
    if (!event.dataTransfer || !(event.target instanceof Element)) return

    event.dataTransfer.effectAllowed = 'copy'
    event.dataTransfer.setData(
        'application/json',
        JSON.stringify({ type: props.type, label: props.label, isNew: true }),
    )
    event.dataTransfer.setDragImage(event.target, event.target.clientWidth / 2, 20)
    isDragging.value = true
}

const onDragEnd = () => {
    isDragging.value = false
}
</script>

<template>
    <div
        class="group relative flex cursor-grab items-center gap-3 rounded-xl border bg-card px-3 py-2.5 shadow-xs select-none transition-[border-color,background-color,box-shadow,opacity] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] active:cursor-grabbing"
        :class="
            isDragging
                ? 'border-primary/60 bg-primary/10 opacity-80 shadow-md ring-2 ring-primary/25'
                : 'border-border hover:border-primary/35 hover:bg-accent'
        "
        draggable="true"
        @dragstart="onDragStart"
        @dragend="onDragEnd"
    >
        <GripVertical
            class="size-4 shrink-0 text-muted-foreground/50 transition-colors group-hover:text-primary"
            aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-foreground">{{ label }}</p>
            <p v-if="description" class="mt-0.5 truncate text-xs leading-snug text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <span
            class="text-primary max-w-[6.5rem] truncate text-[10px] font-semibold opacity-0 transition-opacity duration-200 group-hover:opacity-100"
        >
            → kanvas
        </span>
    </div>
</template>
