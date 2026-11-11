<script setup lang="ts">
import DraggableItem from '@/components/modules/builder/DraggableItem.vue';
import { ChevronRight, Search } from 'lucide-vue-next';
import type { IFormBuilderPaletteCategory } from '@/components/modules/builder/formBuilderPalette';

const searchQuery = defineModel<string>('searchQuery', { required: true });

const props = defineProps<{
    categories: IFormBuilderPaletteCategory[];
    openCategoryName: string | null;
}>();

defineEmits<{
    toggleCategory: [cat: IFormBuilderPaletteCategory];
    paletteDragStart: [];
    paletteDragEnd: [];
}>();

/** Saat mencari, semua kategori hasil tampil terbuka; default ikuti single-expand. */
function isCategoryExpanded(name: string): boolean {
    const q = searchQuery.value.trim();
    return q !== '' ? true : name === props.openCategoryName;
}
</script>

<template>
    <aside
        class="border-border bg-background hidden w-[260px] shrink-0 flex-col border-r lg:flex lg:max-h-full lg:self-start"
        aria-label="Component palette"
    >
        <div class="border-border shrink-0 border-b px-4 pt-5 pb-4">
            <h2 class="font-display text-foreground text-sm font-semibold tracking-tight">Komponen</h2>
            <p class="text-muted-foreground mt-1 text-xs leading-snug">Tarik ke kanvas di tengah.</p>
            <div class="relative mt-4">
                <Search
                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari komponen…"
                    class="border-border bg-background text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-primary/20 h-11 w-full rounded-lg border py-2.5 pr-3 pl-10 text-sm shadow-sm transition-[border-color,box-shadow] focus:ring-2 focus:outline-none"
                />
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
            <div v-for="cat in categories" :key="cat.name" class="mb-2.5 last:mb-0">
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground mb-1.5 flex w-full items-center gap-2 px-1.5 py-1 text-left text-xs font-semibold tracking-wide uppercase transition-colors"
                    :aria-expanded="isCategoryExpanded(cat.name)"
                    @click="$emit('toggleCategory', cat)"
                >
                    <ChevronRight
                        class="size-3.5 shrink-0 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
                        :class="isCategoryExpanded(cat.name) ? 'rotate-90' : ''"
                    />
                    <span class="min-w-0 flex-1 truncate">{{ cat.name }}</span>
                    <span class="text-muted-foreground shrink-0 text-[11px] font-medium tabular-nums">
                        {{ cat.fields.length }}
                    </span>
                </button>
                <div
                    class="grid transition-[grid-template-rows,opacity] duration-250 ease-[cubic-bezier(0.22,1,0.36,1)]"
                    :class="isCategoryExpanded(cat.name) ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                >
                    <div class="min-h-0 overflow-hidden">
                        <div class="flex flex-col gap-1.5 pt-0.5">
                            <DraggableItem
                                v-for="f in cat.fields"
                                :key="f.type"
                                v-bind="f"
                                @drag-start="$emit('paletteDragStart')"
                                @drag-end="$emit('paletteDragEnd')"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="categories.length === 0" class="flex flex-col items-center py-10 text-center">
                <p class="text-muted-foreground text-sm">Tidak ada komponen yang cocok</p>
            </div>
        </div>
    </aside>
</template>
