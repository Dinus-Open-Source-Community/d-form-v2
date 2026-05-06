<script setup lang="ts">
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Layers, SlidersHorizontal } from 'lucide-vue-next'
import type { FormBuilderMobileTab } from '@/utils/composables/useFormBuilderWorkspace'

const mobileTab = defineModel<FormBuilderMobileTab>({ required: true })

defineProps<{
    fieldCount: number
    isReadyToSave: boolean
}>()
</script>

<template>
    <div class="border-border bg-card border-b lg:hidden">
        <Tabs v-model="mobileTab" class="px-3 py-2">
            <TabsList class="w-full">
                <TabsTrigger value="build" class="flex-1 gap-1.5">
                    <Layers class="size-3.5" />
                    Build
                    <span
                        class="border-border bg-muted ml-1 rounded-full border px-1.5 py-0.5 text-[9px] leading-none font-semibold tabular-nums"
                    >
                        {{ fieldCount }}
                    </span>
                </TabsTrigger>
                <TabsTrigger value="settings" class="flex-1 gap-1.5">
                    <SlidersHorizontal class="size-3.5" />
                    Settings
                    <span
                        v-if="!isReadyToSave"
                        class="bg-warning/15 text-warning ml-1 grid size-4 place-items-center rounded-full text-[9px] leading-none font-bold"
                    >
                        !
                    </span>
                </TabsTrigger>
            </TabsList>
        </Tabs>
    </div>
</template>
