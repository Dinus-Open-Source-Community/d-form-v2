<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useBroadcastTrackingSummary } from '@/utils/composables/useBroadcastTrackingSummary'
import type { IBroadcastHubTracking, TBroadcastHubStatus } from '@/lib/broadcastHub'

const props = defineProps<{
    status: TBroadcastHubStatus
    tracking: IBroadcastHubTracking | null
}>()

const summary = useBroadcastTrackingSummary({ status: props.status, tracking: props.tracking })

/** Seam Fase 2: backend Fase 1 tidak mengirim objek tracking — kartu hanya render bila ada baris. */
const hasTrackingRows = computed<boolean>(
    () => summary.trackingVisible.value && summary.trackingRows.value.length > 0,
)
</script>

<template>
    <Card v-if="hasTrackingRows" class="rounded-2xl border-border/70" data-testid="broadcast-tracking-summary">
        <CardHeader class="pb-2">
            <CardTitle class="text-sm font-semibold">Ringkasan pengiriman</CardTitle>
            <p class="text-xs text-muted-foreground">Read-only dari snapshot broadcast ini.</p>
        </CardHeader>
        <CardContent>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="row in summary.trackingRows.value"
                    :key="row.key"
                    class="rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5"
                >
                    <dt class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                        {{ row.label }}
                    </dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums">{{ row.value }}</dd>
                </div>
            </dl>
        </CardContent>
    </Card>
    <slot v-else name="empty" />
</template>
