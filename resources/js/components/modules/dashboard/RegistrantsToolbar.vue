<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Search } from 'lucide-vue-next'
import { REGISTRANTS_TAB_ITEMS } from '@/lib/registrantsUi'

const searchQuery = defineModel<string>('searchQuery', { required: true })
const activeStatusTab = defineModel<'all' | 'pending' | 'accepted' | 'rejected'>('activeStatusTab', {
    required: true,
})

defineProps<{
    statusCounts: Record<'all' | 'pending' | 'accepted' | 'rejected', number>
}>()
</script>

<template>
    <section class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <Tabs v-model="activeStatusTab" class="w-full lg:w-auto" aria-label="Filter status pendaftar">
            <TabsList class="flex h-auto min-h-10 w-full flex-wrap gap-1 rounded-xl bg-muted/50 p-1.5 lg:inline-flex lg:w-auto">
                <TabsTrigger
                    v-for="t in REGISTRANTS_TAB_ITEMS"
                    :key="t.value"
                    :value="t.value"
                    class="rounded-lg px-3 py-2 text-xs font-medium data-[state=active]:shadow-sm sm:px-4"
                >
                    {{ t.label }}
                    <span
                        :class="[
                            'ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                            t.value === 'pending' && 'bg-warning/15 text-warning-foreground',
                            t.value === 'accepted' && 'bg-success/10 text-success',
                            t.value === 'rejected' && 'bg-destructive/10 text-destructive',
                            t.value === 'all' && 'bg-muted text-muted-foreground',
                        ]"
                    >
                        {{ statusCounts[t.value] }}
                    </span>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <div class="relative w-full lg:max-w-xs">
            <Search
                class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="searchQuery"
                type="search"
                placeholder="Cari nama atau email…"
                class="h-10 w-full rounded-xl border-border/60 bg-background pl-9 shadow-sm"
                autocomplete="off"
            />
        </div>
    </section>
</template>
