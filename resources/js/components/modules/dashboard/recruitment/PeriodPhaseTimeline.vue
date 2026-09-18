<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import { formatIdDateLabel, formatIdDateTimeLabel } from '@/lib/shadcnDateFormat'
import { parsePeriodDate, type PeriodPhaseInput, type PeriodStatusValue } from '@/lib/recruitmentPeriodPhase'

type NodeState = 'done' | 'current' | 'upcoming' | 'unscheduled' | 'past'

interface TimelineNode {
    key: string
    label: string
    value: string | null
    withTime: boolean
    state: NodeState
}

const props = defineProps<{ period: PeriodPhaseInput; status: PeriodStatusValue }>()

const nodeStateClasses: Record<NodeState, string> = {
    done: 'border-success/30 bg-success/10 text-success',
    current: 'border-primary/30 bg-primary/10 text-primary',
    upcoming: 'border-border bg-background text-muted-foreground',
    unscheduled: 'border-dashed border-border bg-background text-muted-foreground',
    past: 'border-border bg-muted text-muted-foreground',
}

const nodeStateLabels: Record<NodeState, string> = {
    done: 'selesai',
    current: 'sedang berlangsung',
    upcoming: 'akan datang',
    past: 'sudah lewat',
    unscheduled: 'belum dijadwalkan',
}

const nodes = computed<TimelineNode[]>(() => {
    const now = new Date()
    const raw: Omit<TimelineNode, 'state'>[] = [
        {
            key: 'registration-open',
            label: 'Buka pendaftaran',
            value: props.period.registrationOpensAt,
            withTime: true,
        },
        {
            key: 'registration-close',
            label: 'Tutup pendaftaran',
            value: props.period.registrationClosesAt,
            withTime: true,
        },
        { key: 'interview-start', label: 'Mulai interview', value: props.period.interviewStartsAt, withTime: false },
        { key: 'interview-end', label: 'Selesai interview', value: props.period.interviewEndsAt, withTime: false },
        {
            key: 'finalization',
            label: 'Target finalisasi',
            value: props.period.finalizationDeadlineAt,
            withTime: false,
        },
    ]

    const isOpen = props.status === 'open'
    let currentAssigned = false

    return raw.map((node) => {
        if (!node.value) return { ...node, state: 'unscheduled' }

        const when = parsePeriodDate(node.value)
        if (!when) return { ...node, state: 'unscheduled' }
        if (when.getTime() <= now.getTime()) return { ...node, state: isOpen ? 'done' : 'past' }
        if (!isOpen) return { ...node, state: 'upcoming' }
        if (!currentAssigned) {
            currentAssigned = true
            return { ...node, state: 'current' }
        }

        return { ...node, state: 'upcoming' }
    })
})

function displayValue(node: TimelineNode): string {
    if (!node.value) return 'Belum ditentukan'
    if (!parsePeriodDate(node.value)) return 'Belum ditentukan'
    return node.withTime ? formatIdDateTimeLabel(node.value) : formatIdDateLabel(node.value)
}
</script>

<template>
    <Card class="rounded-2xl border-border/70 shadow-sm">
        <CardHeader class="pb-2">
            <CardTitle class="text-base font-medium">Jadwal periode</CardTitle>
        </CardHeader>
        <CardContent>
            <ol role="list" class="flex flex-col gap-3 lg:flex-row lg:items-stretch lg:gap-0">
                <li
                    v-for="(node, index) in nodes"
                    :key="node.key"
                    class="flex min-w-0 flex-1 items-start gap-3 lg:flex-col lg:items-stretch lg:gap-2"
                >
                    <div class="flex items-center gap-3 lg:w-full">
                        <span
                            :class="cn(
                                'flex size-8 shrink-0 items-center justify-center rounded-full border text-xs font-semibold tabular-nums',
                                nodeStateClasses[node.state],
                            )"
                            aria-hidden="true"
                        >
                            {{ index + 1 }}
                        </span>
                        <span
                            v-if="index < nodes.length - 1"
                            class="bg-border hidden h-px flex-1 lg:block"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0 pb-1 lg:pr-4">
                        <p class="text-sm font-medium">{{ node.label }}</p>
                        <p class="text-muted-foreground text-xs tabular-nums">{{ displayValue(node) }}</p>
                        <span class="sr-only">{{ nodeStateLabels[node.state] }}</span>
                    </div>
                </li>
            </ol>
        </CardContent>
    </Card>
</template>
