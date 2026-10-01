import { computed, type ComputedRef } from 'vue'
import {
    buildBroadcastTrackingRows,
    canShowBroadcastTracking,
    type IBroadcastHubTracking,
    type IBroadcastTrackingRow,
    type TBroadcastHubStatus,
} from '@/lib/broadcastHub'

/** Masukan ringkasan tracking (status + payload snapshot read-only). */
export interface IBroadcastTrackingSummaryInput {
    status: TBroadcastHubStatus
    tracking: IBroadcastHubTracking | null
}

/** Ringkasan tracking read-only untuk Show (tersembunyi saat draft kosong). */
export function useBroadcastTrackingSummary(input: IBroadcastTrackingSummaryInput): {
    trackingVisible: ComputedRef<boolean>
    trackingRows: ComputedRef<IBroadcastTrackingRow[]>
} {
    const trackingVisible = computed<boolean>(() => canShowBroadcastTracking(input.status, input.tracking))
    const trackingRows = computed<IBroadcastTrackingRow[]>(() =>
        input.tracking ? buildBroadcastTrackingRows(input.tracking) : [],
    )
    return { trackingVisible, trackingRows }
}
