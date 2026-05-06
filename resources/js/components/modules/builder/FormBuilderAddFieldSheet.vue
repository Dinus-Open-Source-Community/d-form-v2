<script setup lang="ts">
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet'
import { Search, Plus } from 'lucide-vue-next'
import type { FormBuilderPaletteCategory, FormBuilderPaletteField } from '@/components/modules/builder/formBuilderPalette'

const open = defineModel<boolean>('open', { required: true })
const searchQuery = defineModel<string>('searchQuery', { required: true })

defineProps<{
    categories: FormBuilderPaletteCategory[]
}>()

defineEmits<{
    pickField: [template: FormBuilderPaletteField]
}>()
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="bottom" class="border-border flex max-h-[88vh] flex-col rounded-t-2xl border-t p-0">
            <SheetHeader class="border-border shrink-0 space-y-1 border-b px-5 py-4 text-left">
                <SheetTitle class="font-display flex items-center gap-2 text-base font-bold tracking-[-0.015em]">
                    <Plus class="text-primary size-4" />
                    Add a field
                </SheetTitle>
                <SheetDescription class="text-xs">Tap any block to insert it at the bottom of your form.</SheetDescription>
            </SheetHeader>
            <div class="border-border bg-card border-b px-5 py-3">
                <div class="relative">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search components..."
                        class="border-border bg-card text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-primary/15 w-full rounded-lg border py-2.5 pr-3 pl-9 text-sm shadow-xs transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] focus:ring-3 focus:outline-none"
                    />
                </div>
            </div>
            <div class="bg-background flex-1 space-y-5 overflow-y-auto px-5 py-5">
                <section v-for="cat in categories" :key="cat.name">
                    <div
                        class="text-muted-foreground mb-2.5 flex items-center gap-1.5 text-[10px] font-semibold tracking-[0.14em] uppercase"
                    >
                        <component :is="cat.icon" class="size-3.5" />
                        {{ cat.name }}
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <button
                            v-for="f in cat.fields"
                            :key="f.type"
                            type="button"
                            class="group border-border bg-card hover:border-primary/30 flex flex-col items-start gap-2 rounded-xl border p-3.5 text-left shadow-xs transition-[transform,border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px active:scale-[0.98]"
                            @click="$emit('pickField', f)"
                        >
                            <div
                                class="border-primary/15 bg-primary/8 text-primary group-hover:border-primary/30 group-hover:bg-primary/12 grid size-9 place-items-center rounded-lg border transition-colors"
                            >
                                <component :is="f.icon" class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-foreground truncate text-[13px] font-semibold">{{ f.label }}</p>
                                <p class="text-muted-foreground line-clamp-2 text-[10px] leading-tight">
                                    {{ f.description }}
                                </p>
                            </div>
                        </button>
                    </div>
                </section>
                <div v-if="categories.length === 0" class="flex flex-col items-center py-10 text-center">
                    <Search class="text-muted-foreground/50 mb-2 size-9" />
                    <p class="text-muted-foreground text-sm font-medium">No components match "{{ searchQuery }}"</p>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
