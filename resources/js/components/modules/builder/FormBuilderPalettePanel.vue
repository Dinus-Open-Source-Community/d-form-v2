<script setup lang="ts">
import DraggableItem from '@/components/modules/builder/DraggableItem.vue'
import { ChevronRight, Search, Sparkles } from 'lucide-vue-next'
import type { FormBuilderPaletteCategory } from '@/components/modules/builder/formBuilderPalette'

const searchQuery = defineModel<string>('searchQuery', { required: true })

defineProps<{
    categories: FormBuilderPaletteCategory[]
}>()

defineEmits<{
    toggleCategory: [cat: FormBuilderPaletteCategory]
}>()
</script>

<template>
    <aside
        class="border-border bg-card hidden w-[260px] shrink-0 flex-col border-r lg:flex"
        aria-label="Component palette"
    >
        <div class="border-border border-b px-4 pt-4 pb-3">
            <h2 class="font-display text-foreground flex items-center gap-1.5 text-sm font-semibold tracking-[-0.01em]">
                <Sparkles class="text-primary size-4" />
                Components
            </h2>
            <p class="text-muted-foreground mt-0.5 text-[10px]">Drag a block onto the canvas</p>
            <div class="relative mt-3">
                <Search
                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search components..."
                    class="border-border bg-card text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-primary/15 w-full rounded-lg border py-2 pr-3 pl-8 text-xs font-medium shadow-xs transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] focus:ring-3 focus:outline-none"
                />
            </div>
        </div>
        <div class="flex-1 overflow-y-auto px-3 py-3">
            <div v-for="cat in categories" :key="cat.name" class="mb-3 last:mb-0">
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground mb-1.5 flex w-full items-center gap-2 rounded-lg px-1 py-1 text-left text-[11px] font-semibold tracking-[0.12em] uppercase transition-colors"
                    @click="$emit('toggleCategory', cat)"
                >
                    <ChevronRight
                        class="size-3 shrink-0 transition-transform duration-200"
                        :class="cat.isOpen ? 'rotate-90' : ''"
                    />
                    <component :is="cat.icon" class="size-3.5 shrink-0" />
                    {{ cat.name }}
                    <span class="text-muted-foreground ml-auto text-[10px] font-medium">{{ cat.fields.length }}</span>
                </button>
                <div v-show="cat.isOpen" class="flex flex-col gap-1.5">
                    <DraggableItem v-for="f in cat.fields" :key="f.type" v-bind="f" />
                </div>
            </div>
            <div v-if="categories.length === 0" class="flex flex-col items-center py-8 text-center">
                <Search class="text-muted-foreground/50 mb-2 size-8" />
                <p class="text-muted-foreground text-xs font-medium">No components found</p>
            </div>
        </div>
    </aside>
</template>
