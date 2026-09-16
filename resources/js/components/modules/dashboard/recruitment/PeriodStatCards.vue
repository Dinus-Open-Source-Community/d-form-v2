<script setup lang="ts">
import { computed } from 'vue'
import KpiCard from '@/components/modules/dashboard/KpiCard.vue'
import { CheckCircle2, ClipboardList, MessagesSquare, Users } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import type { IconComponent } from '@/types/icons'

interface StatCard {
    key: string
    label: string
    value: string | number
    icon: IconComponent
    color: 'primary' | 'success' | 'warning' | 'destructive'
}

const props = withDefaults(
    defineProps<{
        applicationsCount: number
        queueCounts: Record<string, number>
        activeQueue?: string
    }>(),
    { activeQueue: '' },
)

const emit = defineEmits<{ select: [queue: string] }>()

const cards = computed<StatCard[]>(() => [
    {
        key: '',
        label: 'Total applicant',
        value: props.applicationsCount.toLocaleString('id-ID'),
        icon: Users,
        color: 'primary',
    },
    {
        key: 'screening',
        label: 'Perlu screening',
        value: (props.queueCounts.screening ?? 0).toLocaleString('id-ID'),
        icon: ClipboardList,
        color: 'warning',
    },
    {
        key: 'interview',
        label: 'Interview',
        value: (props.queueCounts.interview ?? 0).toLocaleString('id-ID'),
        icon: MessagesSquare,
        color: 'primary',
    },
    {
        key: 'done',
        label: 'Selesai',
        value: (props.queueCounts.done ?? 0).toLocaleString('id-ID'),
        icon: CheckCircle2,
        color: 'success',
    },
])
</script>

<template>
    <div role="group" aria-label="Filter antrean" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <button
            v-for="card in cards"
            :key="card.key || 'all'"
            type="button"
            class="focus-visible:outline-ring cursor-pointer rounded-2xl text-left focus-visible:outline-2 focus-visible:outline-offset-2"
            :class="cn(card.key === activeQueue && 'ring-2 ring-primary')"
            :aria-pressed="card.key === activeQueue"
            @click="emit('select', card.key)"
        >
            <KpiCard :label="card.label" :value="card.value" :icon="card.icon" :color="card.color" />
        </button>
    </div>
</template>
