<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { Search, Download, LayoutGrid, List } from 'lucide-vue-next'
import { REGISTRANTS_TAB_ITEMS } from '@/lib/registrantsUi'

const searchQuery = defineModel<string>('searchQuery', { required: true })
const activeStatusTab = defineModel<'all' | 'pending' | 'approved' | 'rejected'>('activeStatusTab', {
    required: true,
})
const viewType = defineModel<'table' | 'form'>('viewType', { required: true })

defineProps<{
    statusCounts: Record<'all' | 'pending' | 'approved' | 'rejected', number>
}>()

defineEmits<{
    exportClick: []
}>()
</script>

<template>
    <section class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap items-center gap-3">
            <Tabs v-model="activeStatusTab" class="w-full md:w-auto">
                <TabsList class="h-10 rounded-full bg-muted/60 p-1">
                    <TabsTrigger
                        v-for="t in REGISTRANTS_TAB_ITEMS"
                        :key="t.value"
                        :value="t.value"
                        class="gap-1.5 rounded-full px-4 text-xs font-medium data-[state=active]:shadow-sm"
                    >
                        {{ t.label }}
                        <span
                            :class="[
                                'rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                                t.value === 'pending' && 'bg-warning/15 text-warning-foreground',
                                t.value === 'approved' && 'bg-success/10 text-success',
                                t.value === 'rejected' && 'bg-destructive/10 text-destructive',
                                t.value === 'all' && 'bg-muted text-muted-foreground',
                            ]"
                        >
                            {{ statusCounts[t.value] }}
                        </span>
                    </TabsTrigger>
                </TabsList>
            </Tabs>

            <Tabs v-model="viewType" class="hidden md:block">
                <TabsList class="h-10 rounded-full bg-muted/60 p-1">
                    <TabsTrigger value="table" class="rounded-full px-3">
                        <List class="size-3.5" />
                    </TabsTrigger>
                    <TabsTrigger value="form" class="rounded-full px-3">
                        <LayoutGrid class="size-3.5" />
                    </TabsTrigger>
                </TabsList>
            </Tabs>
        </div>

        <div class="flex items-center gap-2">
            <div class="relative w-full md:w-64">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    placeholder="Search name or email…"
                    class="h-10 rounded-full border-border/60 bg-card pl-9 shadow-sm focus-visible:ring-primary/30"
                />
            </div>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-10 rounded-full"
                        @click="$emit('exportClick')"
                    >
                        <Download class="size-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Export visible list as CSV</TooltipContent>
            </Tooltip>
        </div>
    </section>
</template>
