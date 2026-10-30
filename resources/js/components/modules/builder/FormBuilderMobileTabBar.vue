<script setup lang="ts">
import { Layers, SlidersHorizontal } from 'lucide-vue-next'
import type { FormBuilderMobileTab } from '@/utils/composables/useFormBuilderWorkspace'

const mobileTab = defineModel<FormBuilderMobileTab>({ required: true })

defineProps<{
    fieldCount: number
    isReadyToSave: boolean
    showSettingsTab: boolean
}>()
</script>

<template>
    <!-- Full bleed terhadap padding main (px-4), lebar penuh area konten -->
    <div class="border-border bg-card -mx-4 w-[calc(100%+2rem)] border-b lg:hidden">
        <div :class="['grid w-full gap-2 px-4 py-3', showSettingsTab ? 'grid-cols-2' : 'grid-cols-1']">
            <button
                v-if="showSettingsTab"
                type="button"
                role="tab"
                :aria-selected="mobileTab === 'build'"
                class="flex min-h-12 w-full min-w-0 items-center justify-center gap-2 border border-transparent px-3 py-3 text-sm font-medium transition-[color,background-color,box-shadow,border-color] duration-200"
                :class="
 mobileTab === 'build'
 ? 'border-border/60 bg-card text-foreground shadow-sm ring-1 ring-border/40'
 : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground'
 "
                @click="mobileTab = 'build'"
            >
                <Layers class="size-4 shrink-0" aria-hidden="true" />
                <span class="min-w-0 truncate">Kanvas</span>
                <span
                    class="border-border bg-background shrink-0 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold tabular-nums leading-none"
                >
                    {{ fieldCount }}
                </span>
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="mobileTab === 'settings'"
                class="flex min-h-12 w-full min-w-0 items-center justify-center gap-2 border border-transparent px-3 py-3 text-sm font-medium transition-[color,background-color,box-shadow,border-color] duration-200"
                :class="
 mobileTab === 'settings'
 ? 'border-border/60 bg-card text-foreground shadow-sm ring-1 ring-border/40'
 : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground'
 "
                @click="mobileTab = 'settings'"
            >
                <SlidersHorizontal class="size-4 shrink-0" aria-hidden="true" />
                <span class="min-w-0 truncate">Pengaturan</span>
                <span
                    v-if="!isReadyToSave"
                    class="bg-warning/15 text-warning grid size-5 shrink-0 place-items-center rounded-full text-[10px] font-bold leading-none"
                    aria-label="Ada isu validasi"
                >
                    !
                </span>
            </button>
        </div>
    </div>
</template>
