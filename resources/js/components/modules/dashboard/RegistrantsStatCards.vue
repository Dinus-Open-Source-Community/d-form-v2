<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import type { IRegistrantsStatCardModel } from '@/hooks/useEventRegistrantsPage';
import { cn } from '@/lib/utils';

defineProps<{
    statCards: IRegistrantsStatCardModel[];
    toneStyles: Record<
        'primary' | 'warning' | 'success' | 'destructive',
        { chip: string; ring: string; bar: string; dot: string }
    >;
    activeStatusTab: 'all' | 'pending' | 'accepted' | 'rejected';
}>();

const emit = defineEmits<{
    selectStat: [key: 'all' | 'pending' | 'accepted' | 'rejected'];
}>();
</script>

<template>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card
            v-for="stat in statCards"
            :key="stat.key"
            role="button"
            tabindex="0"
            :class="
                cn(
                    'border-border/70 cursor-pointer rounded-2xl shadow-sm ring-1 ring-black/[0.03] transition-colors',
                    'hover:bg-muted/25 focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none dark:ring-white/[0.06]',
                    activeStatusTab === stat.key && 'border-primary/30 bg-primary/[0.04] ring-primary/25 ring-2'
                )
            "
            @click="emit('selectStat', stat.key)"
            @keydown.enter.prevent="emit('selectStat', stat.key)"
            @keydown.space.prevent="emit('selectStat', stat.key)"
        >
            <CardContent class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] break-words uppercase"
                        >
                            {{ stat.label }}
                        </p>
                        <p
                            class="font-display text-foreground mt-2 text-3xl font-semibold tracking-[-0.03em] sm:text-4xl"
                        >
                            {{ stat.value.toLocaleString('id-ID') }}
                        </p>
                        <p class="text-muted-foreground mt-2 text-xs leading-relaxed">{{ stat.helper }}</p>
                    </div>
                    <div
                        :class="
                            cn(
                                'flex size-12 shrink-0 items-center justify-center rounded-full border shadow-xs',
                                toneStyles[stat.tone].chip
                            )
                        "
                    >
                        <component :is="stat.icon" class="size-5" aria-hidden="true" />
                    </div>
                </div>
            </CardContent>
        </Card>
    </section>
</template>
