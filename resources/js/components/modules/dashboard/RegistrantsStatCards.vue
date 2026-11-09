<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import type { RegistrantsStatCardModel } from '@/hooks/useEventRegistrantsPage'
import { cn } from '@/lib/utils'

defineProps<{
    statCards: RegistrantsStatCardModel[]
    toneStyles: Record<
        'primary' | 'warning' | 'success' | 'destructive',
        { chip: string; ring: string; bar: string; dot: string }
    >
    activeStatusTab: 'all' | 'pending' | 'accepted' | 'rejected'
}>()

const emit = defineEmits<{
    selectStat: [key: 'all' | 'pending' | 'accepted' | 'rejected']
}>()
</script>

<template>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card
            v-for="stat in statCards"
            :key="stat.key"
            role="button"
            tabindex="0"
            :class="cn(
 'cursor-pointer rounded-2xl border-border/70 shadow-sm ring-1 ring-black/[0.03] transition-colors',
 'hover:bg-muted/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:ring-white/[0.06]',
 activeStatusTab === stat.key && 'border-primary/30 bg-primary/[0.04] ring-2 ring-primary/25',
 )"
            @click="emit('selectStat', stat.key)"
            @keydown.enter.prevent="emit('selectStat', stat.key)"
            @keydown.space.prevent="emit('selectStat', stat.key)"
        >
            <CardContent class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="break-words text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                            {{ stat.label }}
                        </p>
                        <p class="font-display mt-2 text-3xl font-semibold tracking-[-0.03em] text-foreground sm:text-4xl">
                            {{ stat.value.toLocaleString('id-ID') }}
                        </p>
                        <p class="mt-2 text-xs leading-relaxed text-muted-foreground">{{ stat.helper }}</p>
                    </div>
                    <div
                        :class="cn(
 'flex size-12 shrink-0 items-center justify-center rounded-full border shadow-xs',
 toneStyles[stat.tone].chip,
 )"
                    >
                        <component :is="stat.icon" class="size-5" aria-hidden="true" />
                    </div>
                </div>
            </CardContent>
        </Card>
    </section>
</template>
