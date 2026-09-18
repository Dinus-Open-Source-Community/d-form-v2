<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { cn } from '@/lib/utils'
import {
    daysRemaining,
    phaseCountdownLabel,
    phaseDeadline,
    phaseLabel,
    resolvePeriodPhase,
    statusLabel,
    type PeriodPhaseInput,
    type PeriodStatusValue,
} from '@/lib/recruitmentPeriodPhase'

const props = defineProps<{
    period: PeriodPhaseInput & { id: string; name: string; slug: string }
}>()

const emit = defineEmits<{ open: []; close: [] }>()

const phase = computed(() => resolvePeriodPhase(props.period))
const remaining = computed(() => daysRemaining(phaseDeadline(phase.value, props.period)))
const countdown = computed(() => phaseCountdownLabel(phase.value, remaining.value))

const statusClasses: Record<PeriodStatusValue, string> = {
    draft: 'border-border bg-secondary text-secondary-foreground',
    open: 'border-success/20 bg-success/10 text-success',
    closed: 'border-warning/25 bg-warning/10 text-warning-foreground',
    archived: 'border-border bg-muted text-muted-foreground',
}

const canOpen = computed(() => props.period.status === 'draft' || props.period.status === 'closed')
const canClose = computed(() => props.period.status === 'open')
</script>

<template>
    <Card class="rounded-2xl border-border/70 shadow-sm">
        <CardContent class="flex flex-col gap-5 p-6 lg:flex-row lg:items-start lg:justify-between lg:gap-8">
            <div class="min-w-0 flex-1 space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ period.name }}</h1>
                    <Badge :class="cn('border', statusClasses[period.status])">
                        {{ statusLabel(period.status) }}
                    </Badge>
                </div>

                <p class="text-muted-foreground text-sm">
                    {{ phaseLabel(phase) }}
                    <template v-if="countdown !== null"> · {{ countdown }}</template>
                </p>

                <p class="text-muted-foreground font-mono text-xs">/{{ period.slug }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:shrink-0">
                <Button v-if="canOpen" size="sm" @click="emit('open')">Buka pendaftaran</Button>
                <Button v-if="canClose" variant="destructive" size="sm" @click="emit('close')">
                    Tutup pendaftaran
                </Button>
                <Button as-child variant="outline" size="sm">
                    <Link :href="routes.admin.recruitment.periods.edit(period.id)">Edit</Link>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
