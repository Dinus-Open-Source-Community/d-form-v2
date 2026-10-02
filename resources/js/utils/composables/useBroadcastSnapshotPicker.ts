import { computed, ref, type ComputedRef, type Ref } from 'vue'
import {
    BROADCAST_EVENT_DATASET,
    BROADCAST_PERIOD_DATASET,
    resolveBroadcastScopeError,
    type IBroadcastDatasetSelection,
    type IBroadcastLockedContext,
    type TBroadcastDatasetSource,
} from '@/lib/broadcastHub'

/** State awal picker snapshot (konteks terkunci + sumber awal). */
export interface IBroadcastSnapshotPickerState {
    locked: IBroadcastLockedContext
    initialSource?: TBroadcastDatasetSource | null
}

/** Picker dataset snapshot: sumber + konteks efektif + error scope salah. */
export function useBroadcastSnapshotPicker(state: IBroadcastSnapshotPickerState): {
    datasetSource: Ref<TBroadcastDatasetSource | null>
    selectedEventId: Ref<string | null>
    selectedPeriodId: Ref<string | null>
    effectiveSelection: ComputedRef<IBroadcastDatasetSelection>
    scopeError: ComputedRef<string | null>
    canSubmitDataset: ComputedRef<boolean>
} {
    const defaultSource = (locked: IBroadcastLockedContext): TBroadcastDatasetSource | null => {
        if (locked.kind === 'event') return BROADCAST_EVENT_DATASET
        if (locked.kind === 'period') return BROADCAST_PERIOD_DATASET
        return null
    }

    const datasetSource = ref<TBroadcastDatasetSource | null>(state.initialSource ?? defaultSource(state.locked))
    const selectedEventId = ref<string | null>(state.locked.eventId)
    const selectedPeriodId = ref<string | null>(state.locked.periodId)

    const effectiveSelection = computed<IBroadcastDatasetSelection>(() => ({
        source: datasetSource.value,
        eventId: state.locked.eventId ?? selectedEventId.value,
        periodId: state.locked.periodId ?? selectedPeriodId.value,
    }))
    const scopeError = computed<string | null>(() => resolveBroadcastScopeError(effectiveSelection.value))
    const canSubmitDataset = computed<boolean>(() => scopeError.value === null)

    return {
        datasetSource,
        selectedEventId,
        selectedPeriodId,
        effectiveSelection,
        scopeError,
        canSubmitDataset,
    }
}
