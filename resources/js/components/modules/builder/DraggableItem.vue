<script setup lang="ts">
import { GripVertical } from 'lucide-vue-next'

const props = defineProps({
    type: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: [Object, Function], required: true },
    description: { type: String, default: '' },
})

const onDragStart = (e) => {
    e.dataTransfer.effectAllowed = 'copy'
    e.dataTransfer.setData(
        'application/json',
        JSON.stringify({ type: props.type, label: props.label, isNew: true }),
    )
    e.dataTransfer.setDragImage(e.target, e.target.offsetWidth / 2, 20)
}
</script>

<template>
    <div
        class="group flex cursor-grab items-center gap-2.5 rounded-xl border-[1.5px] border-[var(--brutal-ink)]/12 bg-white px-3 py-2.5 shadow-[var(--shadow-xs)] transition-all duration-200 select-none hover:-translate-y-0.5 hover:border-[var(--brutal-ink)]/25 hover:shadow-[var(--shadow-sm)] active:cursor-grabbing active:translate-y-0 active:shadow-[var(--shadow-xs)]"
        draggable="true"
        @dragstart="onDragStart"
    >
        <div
            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/8 text-primary transition-colors group-hover:bg-primary/12"
        >
            <component :is="icon" class="size-4" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-semibold text-[var(--brutal-ink)]">{{ label }}</p>
            <p v-if="description" class="truncate text-[10px] leading-tight text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <GripVertical
            class="size-3.5 shrink-0 text-muted-foreground/30 transition-colors group-hover:text-muted-foreground/60"
        />
    </div>
</template>
