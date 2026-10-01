<script setup lang="ts">
import { computed } from 'vue'
import { Label } from '@/components/ui/label'
import { SimpleSelect } from '@/components/ui/simple-select'
import { TriangleAlert } from 'lucide-vue-next'
import BroadcastContextChip from './BroadcastContextChip.vue'
import {
    BROADCAST_DATASET_LABELS,
    resolveBroadcastContextError,
    type IBroadcastLockedContext,
    type IBroadcastScopeOption,
    type TBroadcastDatasetSource,
} from '@/lib/broadcastHub'

const props = defineProps<{
    locked: IBroadcastLockedContext
    sources: TBroadcastDatasetSource[]
    eventOptions: IBroadcastScopeOption[]
    periodOptions: IBroadcastScopeOption[]
    source: TBroadcastDatasetSource | null
    eventId: string | null
    periodId: string | null
    scopeError: string | null
    disabled: boolean
}>()

const emit = defineEmits<{
    'update:source': [value: TBroadcastDatasetSource | null]
    'update:eventId': [value: string | null]
    'update:periodId': [value: string | null]
}>()

const datasetOptions = computed(() =>
    props.sources.map((source) => ({ value: source, label: BROADCAST_DATASET_LABELS[source] })),
)

/** SimpleSelect memakai {value,label}; IBroadcastScopeOption memakai {id,name}. */
const eventSelectOptions = computed(() => props.eventOptions.map((option) => ({ value: option.id, label: option.name })))
const periodSelectOptions = computed(() => props.periodOptions.map((option) => ({ value: option.id, label: option.name })))

const contextError = computed<string | null>(() => resolveBroadcastContextError(props.locked))

function onSourceChange(value: string): void {
    const match = props.sources.find((source) => source === value)
    emit('update:source', match ?? null)
}
</script>

<template>
    <section aria-label="Dataset broadcast" class="flex flex-col gap-4">
        <BroadcastContextChip
            v-if="props.locked.kind !== 'none' && props.locked.displayName"
            :kind="props.locked.kind"
            :display-name="props.locked.displayName"
        />

        <div v-else class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="broadcast-event">Event <span aria-hidden="true" class="text-destructive">*</span></Label>
                <SimpleSelect
                    id="broadcast-event"
                    :model-value="props.eventId ?? ''"
                    :options="eventSelectOptions"
                    placeholder="Pilih event"
                    :disabled="props.disabled"
                    :required="true"
                    @update:model-value="emit('update:eventId', $event === '' ? null : $event)"
                />
            </div>
            <div class="space-y-2">
                <Label for="broadcast-period">Periode <span aria-hidden="true" class="text-destructive">*</span></Label>
                <SimpleSelect
                    id="broadcast-period"
                    :model-value="props.periodId ?? ''"
                    :options="periodSelectOptions"
                    placeholder="Pilih periode"
                    :disabled="props.disabled"
                    :required="true"
                    @update:model-value="emit('update:periodId', $event === '' ? null : $event)"
                />
            </div>
            <p v-if="contextError" role="note" class="text-xs text-muted-foreground sm:col-span-2">
                {{ contextError }} Isi salah satu sesuai sumber dataset.
            </p>
        </div>

        <div class="space-y-2">
            <Label for="broadcast-source">Sumber dataset <span aria-hidden="true" class="text-destructive">*</span></Label>
            <SimpleSelect
                id="broadcast-source"
                :model-value="props.source ?? ''"
                :options="datasetOptions"
                placeholder="Pilih sumber dataset"
                :disabled="props.disabled"
                :required="true"
                :invalid="props.scopeError !== null"
                @update:model-value="onSourceChange($event)"
            />
            <p v-if="props.scopeError" role="alert" class="flex items-start gap-1.5 text-xs text-destructive">
                <TriangleAlert class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                {{ props.scopeError }}
            </p>
        </div>
    </section>
</template>
