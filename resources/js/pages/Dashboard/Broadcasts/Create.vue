<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BroadcastDatasetSection from '@/components/modules/dashboard/broadcast/BroadcastDatasetSection.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useBroadcastContextPrefill } from '@/utils/composables/useBroadcastContextPrefill'
import { useBroadcastSnapshotPicker } from '@/utils/composables/useBroadcastSnapshotPicker'
import { handleInertiaFormErrors, showErrorToast } from '@/lib/error-message'
import { routes } from '@/lib/routes'
import { combineLocalDateTime, modelValueToCalendarDate } from '@/lib/shadcnDateFormat'
import {
    BROADCAST_EVENT_DATASET,
    BROADCAST_PERIOD_DATASET,
    mapBroadcastEventOptions,
    mapBroadcastPeriodOptions,
    mapBroadcastPrefill,
    normalizeBroadcastScheduledAt,
    type IBroadcastAllowedEvent,
    type IBroadcastAllowedPeriod,
    type IBroadcastCreatePrefill,
    type TBroadcastDatasetSource,
} from '@/lib/broadcastHub'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

const props = withDefaults(
    defineProps<{
        prefill?: IBroadcastCreatePrefill | null
        sources?: TBroadcastDatasetSource[]
        allowedEvents?: IBroadcastAllowedEvent[]
        allowedPeriods?: IBroadcastAllowedPeriod[]
    }>(),
    {
        prefill: null,
        sources: () => [BROADCAST_EVENT_DATASET, BROADCAST_PERIOD_DATASET],
        allowedEvents: () => [],
        allowedPeriods: () => [],
    },
)

/** Prefill snake_case backend dipetakan ke konteks camelCase hub. */
const prefill = useBroadcastContextPrefill(mapBroadcastPrefill(props.prefill ?? {}))

const picker = useBroadcastSnapshotPicker({ locked: prefill.lockedContext.value })

/** Opsi dropdown dari allowed_events (title) / allowed_periods (name). */
const eventOptions = computed(() => mapBroadcastEventOptions(props.allowedEvents))
const periodOptions = computed(() => mapBroadcastPeriodOptions(props.allowedPeriods))

const form = useForm({
    name: '',
    source: '',
    event_id: '',
    period_id: '',
    scheduled_at: '',
    send_delay_seconds: 0 as number | null,
})

const errorFieldLabels: Record<string, string> = {
    name: 'Nama broadcast',
    source: 'Sumber dataset',
    event_id: 'Event',
    period_id: 'Periode',
    scheduled_at: 'Jadwal kirim',
    send_delay_seconds: 'Jeda antar email',
}

const canSubmit = computed<boolean>(() => picker.canSubmitDataset.value && !form.processing)

onMounted(() => {
    setTopbar({ title: 'Broadcast baru', subtitle: 'Pusat broadcast' })
})

/** Tanggal (yyyy-mm-dd) + jam (HH:mm) jadwal kirim; digabung saat submit. */
const scheduleDate = ref<string>('')
const scheduleTime = ref<string>('00:00')

/** Jembatan ModelValue string|number ke jam HH:mm. */
function onScheduleTimeInput(value: string | number): void {
    scheduleTime.value = String(value).slice(0, 5)
}

/** Jembatan number|null form ke Input (string|number): kosong berarti tanpa jeda. */
function onDelayInput(value: string | number): void {
    if (typeof value === 'number') {
        form.send_delay_seconds = value
        return
    }
    const parsed = Number.parseInt(value, 10)
    form.send_delay_seconds = Number.isNaN(parsed) ? null : parsed
}

function submit(): void {
    if (picker.scopeError.value) {
        showErrorToast(picker.scopeError.value, { title: 'Konteks belum lengkap' })
        return
    }
    form.source = picker.datasetSource.value ?? ''
    form.event_id = picker.effectiveSelection.value.eventId ?? ''
    form.period_id = picker.effectiveSelection.value.periodId ?? ''
    form.scheduled_at = scheduleDate.value
        ? normalizeBroadcastScheduledAt(
              combineLocalDateTime(modelValueToCalendarDate(scheduleDate.value), scheduleTime.value),
          )
        : ''
    form.post(routes.admin.broadcasts.store, {
        onError: (errors) => {
            handleInertiaFormErrors(errors, { title: 'Gagal menyimpan broadcast', fieldLabels: errorFieldLabels })
        },
    })
}
</script>

<template>
    <Head title="Broadcast baru" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="font-display text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">
                    Broadcast baru
                </h1>
                <p class="text-muted-foreground mt-1.5 text-base">Kirim email ke peserta event atau pelamar periode.</p>
            </div>
            <Button
                type="submit"
                form="broadcast-form"
                :disabled="!canSubmit"
                class="w-full shrink-0 sm:w-auto"
            >
                {{ form.processing ? 'Menyimpan…' : 'Simpan broadcast' }}
            </Button>
        </div>

        <form id="broadcast-form" class="flex min-w-0 flex-col gap-6 sm:gap-8" @submit.prevent="submit">
            <Card class="rounded-2xl border-border/70">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Informasi campaign</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="broadcast-name">Nama broadcast</Label>
                        <Input
                            id="broadcast-name"
                            v-model="form.name"
                            placeholder="Pengumuman hasil screening"
                            required
                            :aria-invalid="!!form.errors.name"
                        />
                        <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="broadcast-scheduled-date">Jadwal kirim</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <DatePicker
                                id="broadcast-scheduled-date"
                                v-model="scheduleDate"
                                placeholder="Pilih tanggal"
                                :aria-invalid="!!form.errors.scheduled_at"
                            />
                            <Input
                                id="broadcast-scheduled-time"
                                type="time"
                                :model-value="scheduleTime"
                                aria-label="Jam kirim"
                                :aria-invalid="!!form.errors.scheduled_at"
                                @update:model-value="onScheduleTimeInput"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">Kosong berarti draft — tanpa jadwal.</p>
                        <p v-if="form.errors.scheduled_at" class="text-destructive text-xs">
                            {{ form.errors.scheduled_at }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="broadcast-delay">Jeda antar email (detik)</Label>
                        <Input
                            id="broadcast-delay"
                            :model-value="form.send_delay_seconds ?? ''"
                            type="number"
                            min="0"
                            :aria-invalid="!!form.errors.send_delay_seconds"
                            @update:model-value="onDelayInput"
                        />
                        <p v-if="form.errors.send_delay_seconds" class="text-destructive text-xs">
                            {{ form.errors.send_delay_seconds }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="rounded-2xl border-border/70">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Konteks &amp; dataset</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Snapshot penerima dikunci per broadcast — dataset reusable tidak didukung.
                    </p>
                </CardHeader>
                <CardContent>
                    <BroadcastDatasetSection
                        :locked="prefill.lockedContext.value"
                        :sources="props.sources"
                        :event-options="eventOptions"
                        :period-options="periodOptions"
                        :source="picker.datasetSource.value"
                        :event-id="picker.selectedEventId.value"
                        :period-id="picker.selectedPeriodId.value"
                        :scope-error="picker.scopeError.value"
                        :disabled="form.processing"
                        @update:source="picker.datasetSource.value = $event"
                        @update:event-id="picker.selectedEventId.value = $event"
                        @update:period-id="picker.selectedPeriodId.value = $event"
                    />
                </CardContent>
            </Card>
        </form>
    </div>
</template>
