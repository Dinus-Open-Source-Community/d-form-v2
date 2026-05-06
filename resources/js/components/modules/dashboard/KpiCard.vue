<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import { TrendingUp, TrendingDown } from 'lucide-vue-next'
import type { IconComponent } from '@/types/icons'

const props = defineProps<{
    label: string
    value: string | number
    trend?: number
    icon: IconComponent
    color?: 'primary' | 'success' | 'warning' | 'destructive'
}>()

const visible = ref(false)
onMounted(() => requestAnimationFrame(() => (visible.value = true)))

const isPositive = computed(() => (props.trend ?? 0) >= 0)

const colorClasses = computed(() => {
    const map: Record<string, string> = {
        primary: 'border-primary/15 bg-primary/10 text-primary',
        success: 'border-success/15 bg-success/10 text-success',
        warning: 'border-warning/20 bg-warning/10 text-warning-foreground',
        destructive: 'border-destructive/15 bg-destructive/10 text-destructive',
    }
    return map[props.color ?? 'primary']
})
</script>

<template>
    <Card
        :class="cn(
            'transition-all duration-500',
            visible ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
        )"
    >
        <CardContent class="p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">{{ label }}</p>
                    <p class="font-display mt-2 text-4xl font-semibold tracking-[-0.04em]">{{ value }}</p>
                    <div v-if="trend !== undefined" class="mt-2 flex items-center gap-1">
                        <component
                            :is="isPositive ? TrendingUp : TrendingDown"
                            :class="cn('size-3.5', isPositive ? 'text-success' : 'text-destructive')"
                        />
                        <span
                            :class="cn('text-xs font-semibold', isPositive ? 'text-success' : 'text-destructive')"
                        >
                            {{ isPositive ? '+' : '' }}{{ trend }}%
                        </span>
                        <span class="text-xs text-muted-foreground">vs last month</span>
                    </div>
                </div>
                <div :class="cn('flex size-12 shrink-0 items-center justify-center rounded-xl border shadow-xs', colorClasses)">
                    <component :is="icon" class="size-5" />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
