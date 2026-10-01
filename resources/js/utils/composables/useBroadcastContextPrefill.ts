import { computed, type ComputedRef } from 'vue'
import {
    resolveBroadcastLockedContext,
    type IBroadcastContextPrefill,
    type IBroadcastLockedContext,
} from '@/lib/broadcastHub'

/** Prefill chip konteks terkunci dari query create (?event_id=/&period_id=). */
export function useBroadcastContextPrefill(prefill: IBroadcastContextPrefill): {
    lockedContext: ComputedRef<IBroadcastLockedContext>
} {
    const lockedContext = computed<IBroadcastLockedContext>(() => resolveBroadcastLockedContext(prefill))
    return { lockedContext }
}
