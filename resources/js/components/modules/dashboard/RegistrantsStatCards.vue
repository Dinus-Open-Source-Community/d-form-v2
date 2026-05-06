<script setup lang="ts">
import type { RegistrantsStatCardModel } from '@/utils/composables/useEventRegistrantsPage'

defineProps<{
    statCards: RegistrantsStatCardModel[]
    toneStyles: Record<
        'primary' | 'warning' | 'success' | 'destructive',
        { chip: string; ring: string; bar: string; dot: string }
    >
    activeStatusTab: 'all' | 'pending' | 'approved' | 'rejected'
    cardShadow: string
}>()

const emit = defineEmits<{
    selectStat: [key: 'all' | 'pending' | 'approved' | 'rejected']
}>()
</script>

<template>
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <button
            v-for="stat in statCards"
            :key="stat.key"
            type="button"
            :class="[
                'group relative flex items-start gap-4 overflow-hidden rounded-2xl border border-border/60 bg-card p-5 text-left transition-all',
                'hover:-translate-y-0.5 hover:border-primary/30',
                cardShadow,
                activeStatusTab === stat.key ? 'ring-2 ring-primary/40' : 'ring-1 ring-transparent',
            ]"
            @click="emit('selectStat', stat.key)"
        >
            <span :class="['absolute inset-x-0 top-0 h-1', toneStyles[stat.tone].bar]" />
            <div
                :class="[
                    'flex size-11 shrink-0 items-center justify-center rounded-xl ring-1',
                    toneStyles[stat.tone].chip,
                    toneStyles[stat.tone].ring,
                ]"
            >
                <component :is="stat.icon" class="size-[19px]" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                    {{ stat.label }}
                </p>
                <p class="mt-1 text-[28px] font-semibold leading-none tabular-nums tracking-tight text-foreground">
                    {{ stat.value.toLocaleString() }}
                </p>
                <p class="mt-1.5 text-[11.5px] leading-snug text-muted-foreground">{{ stat.helper }}</p>
            </div>
        </button>
    </section>
</template>
