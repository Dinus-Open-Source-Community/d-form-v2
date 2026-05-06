<script setup lang="ts">
import DraggableItem from '@/components/modules/builder/DraggableItem.vue'
import FormBannerSettings from '@/components/modules/builder/FormBannerSettings.vue'
import { Search, Sparkles, ChevronRight } from 'lucide-vue-next'
import type { FormBannerState } from '@/components/modules/builder/formBanner'
import type { FormBuilderPaletteCategory, FormBuilderPaletteField } from '@/components/modules/builder/formBuilderPalette'

const searchQuery = defineModel<string>('searchQuery', { required: true })

defineProps<{
    categories: FormBuilderPaletteCategory[]
    banner: FormBannerState
}>()

const emit = defineEmits<{
    patchBanner: [v: FormBannerState]
    toggleCategory: [index: number]
    sidebarDragStart: [e: DragEvent, field: FormBuilderPaletteField]
}>()
</script>

<template>
    <aside class="hidden w-[260px] shrink-0 flex-col border-r border-border bg-card lg:flex">
        <div class="border-b border-border px-4 pt-4 pb-3">
            <h2 class="font-display flex items-center gap-1.5 text-sm font-semibold tracking-[-0.01em] text-foreground">
                <Sparkles class="size-4 text-primary" />
                Components
            </h2>
            <p class="mt-0.5 text-[10px] text-muted-foreground">Drag a component to the form canvas</p>
            <div class="relative mt-3">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground/70"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search components..."
                    class="w-full rounded-lg border border-border bg-card py-2 pr-3 pl-8 text-xs font-medium text-foreground shadow-xs transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-3 focus:ring-primary/15"
                />
            </div>
        </div>
        <div class="flex-1 overflow-y-auto px-3 py-3">
            <div class="mb-4">
                <FormBannerSettings :model-value="banner" @update:model-value="emit('patchBanner', $event)" />
            </div>
            <div v-for="(category, catIdx) in categories" :key="category.name" class="mb-3 last:mb-0">
                <button
                    type="button"
                    class="mb-1.5 flex w-full items-center gap-2 rounded-lg px-1 py-1 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground transition-colors hover:text-foreground"
                    @click="emit('toggleCategory', catIdx)"
                >
                    <ChevronRight
                        class="size-3 shrink-0 transition-transform duration-200"
                        :class="category.isOpen ? 'rotate-90' : ''"
                    />
                    <component :is="category.icon" class="size-3.5 shrink-0" />
                    {{ category.name }}
                    <span class="ml-auto text-[10px] font-medium text-muted-foreground">
                        {{ category.fields.length }}
                    </span>
                </button>
                <div v-show="category.isOpen" class="flex flex-col gap-1.5">
                    <DraggableItem
                        v-for="f in category.fields"
                        :key="f.type"
                        :type="f.type"
                        :label="f.label"
                        :icon="f.icon"
                        :description="f.description"
                        @dragstart="(e) => emit('sidebarDragStart', e, f)"
                    />
                </div>
            </div>
            <div v-if="categories.length === 0" class="flex flex-col items-center py-8 text-center">
                <Search class="mb-2 size-8 text-muted-foreground/50" />
                <p class="text-xs font-medium text-muted-foreground">No components found</p>
                <p class="mt-0.5 text-[10px] text-muted-foreground/70">Try a different search term</p>
            </div>
        </div>
    </aside>
</template>
