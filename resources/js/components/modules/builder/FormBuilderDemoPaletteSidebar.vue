<script setup lang="ts">
import DraggableItem from '@/components/modules/builder/DraggableItem.vue';
import FormBannerSettings from '@/components/modules/builder/FormBannerSettings.vue';
import { Search, Sparkles, ChevronRight } from 'lucide-vue-next';
import type { IFormBannerState } from '@/components/modules/builder/formBanner';
import type {
    IFormBuilderPaletteCategory,
    IFormBuilderPaletteField,
} from '@/components/modules/builder/formBuilderPalette';

const searchQuery = defineModel<string>('searchQuery', { required: true });

defineProps<{
    categories: IFormBuilderPaletteCategory[];
    banner: IFormBannerState;
}>();

const emit = defineEmits<{
    patchBanner: [v: IFormBannerState];
    toggleCategory: [index: number];
    sidebarDragStart: [e: DragEvent, field: IFormBuilderPaletteField];
}>();
</script>

<template>
    <aside class="border-border bg-card hidden w-[260px] shrink-0 flex-col border-r lg:flex">
        <div class="border-border border-b px-4 pt-4 pb-3">
            <h2 class="font-display text-foreground flex items-center gap-1.5 text-sm font-semibold tracking-[-0.01em]">
                <Sparkles class="text-primary size-4" />
                Components
            </h2>
            <p class="text-muted-foreground mt-0.5 text-[10px]">Drag a component to the form canvas</p>
            <div class="relative mt-3">
                <Search
                    class="text-muted-foreground/70 pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    aria-label="Cari komponen"
                    placeholder="Search components..."
                    class="border-border bg-card text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-primary/15 w-full rounded-lg border py-2 pr-3 pl-8 text-xs font-medium shadow-xs transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] focus:ring-3 focus:outline-none"
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
                    class="text-muted-foreground hover:text-foreground mb-1.5 flex w-full items-center gap-2 px-1 py-1 text-left text-[11px] font-semibold tracking-[0.12em] uppercase transition-colors"
                    @click="emit('toggleCategory', catIdx)"
                >
                    <ChevronRight
                        class="size-3 shrink-0 transition-transform duration-200"
                        :class="category.isOpen ? 'rotate-90' : ''"
                    />
                    <component :is="category.icon" class="size-3.5 shrink-0" />
                    {{ category.name }}
                    <span class="text-muted-foreground ml-auto text-[10px] font-medium">
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
                        @dragstart="(e: DragEvent) => emit('sidebarDragStart', e, f)"
                    />
                </div>
            </div>
            <div v-if="categories.length === 0" class="flex flex-col items-center py-8 text-center">
                <Search class="text-muted-foreground/50 mb-2 size-8" />
                <p class="text-muted-foreground text-xs font-medium">No components found</p>
                <p class="text-muted-foreground/70 mt-0.5 text-[10px]">Try a different search term</p>
            </div>
        </div>
    </aside>
</template>
