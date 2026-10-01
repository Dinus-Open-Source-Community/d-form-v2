<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BroadcastDatasetSection from '@/components/modules/dashboard/broadcast/BroadcastDatasetSection.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useBroadcastContextPrefill } from '@/utils/composables/useBroadcastContextPrefill'
import { useBroadcastSnapshotPicker } from '@/utils/composables/useBroadcastSnapshotPicker'
import { handleInertiaFormErrors, showErrorToast } from '@/lib/error-message'
import { routes } from '@/lib/routes'
import type { IBroadcastScopeOption } from '@/lib/broadcastHub'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

/** Konteks prefill dari query (?event_id=/&period_id=) via props Inertia. */
interface IBroadcastCreatePageContext {
    id: string
    name: string
}

const props = withDefaults(
    defineProps<{
        contextEvent?: IBroadcastCreatePageContext | null
        contextPeriod?: IBroadcastCreatePageContext | null
        eventOptions?: IBroadcastScopeOption[]
        periodOptions?: IBroadcastScopeOption[]
    }>(),
    { contextEvent: null, contextPeriod: null, eventOptions: () => [], periodOptions: () => [] },
)

const prefill = useBroadcastContextPrefill({
    eventId: props.contextEvent?.id ?? null,
    eventName: props.contextEvent?.name ?? null,
    periodId: props.contextPeriod?.id ?? null,
    periodName: props.contextPeriod?.name ?? null,
})

const picker = useBroadcastSnapshotPicker({ locked: prefill.lockedContext.value })

const form = useForm({
    name: '',
    scheduled_at: '',
    delay_seconds: 30 as number | null,
    dataset_source: '',
    event_id: '',
    period_id: '',
    subject: '',
    body: '',
})

const errorFieldLabels: Record<string, string> = {
    name: 'Nama broadcast',
    scheduled_at: 'Jadwal kirim',
    delay_seconds: 'Jeda antar email',
    dataset_source: 'Sumber dataset',
    event_id: 'Event',
    period_id: 'Periode',
    subject: 'Subjek',
    body: 'Isi pesan',
}

const canSubmit = computed<boolean>(() => picker.canSubmitDataset.value && !form.processing)

/** Jembatan number|null form ke Input (string|number): kosong berarti tanpa jeda. */
function onDelayInput(value: string | number): void {
    if (typeof value === 'number') {
        form.delay_seconds = value
        return
    }
    const parsed = Number.parseInt(value, 10)
    form.delay_seconds = Number.isNaN(parsed) ? null : parsed
}

onMounted(() => {
    setTopbar({ title: 'Broadcast baru', subtitle: 'Pusat broadcast' })
})

function submit(): void {
    if (picker.scopeError.value) {
        showErrorToast(picker.scopeError.value, { title: 'Konteks belum lengkap' })
        return
    }
    form.dataset_source = picker.datasetSource.value ?? ''
    form.event_id = picker.effectiveSelection.value.eventId ?? ''
    form.period_id = picker.effectiveSelection.value.periodId ?? ''
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
                        <Label for="broadcast-scheduled">Jadwal kirim</Label>
                        <Input
                            id="broadcast-scheduled"
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            :aria-invalid="!!form.errors.scheduled_at"
                        />
                        <p v-if="form.errors.scheduled_at" class="text-destructive text-xs">
                            {{ form.errors.scheduled_at }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="broadcast-delay">Jeda antar email (detik)</Label>
                        <Input
                            id="broadcast-delay"
                            :model-value="form.delay_seconds ?? ''"
                            type="number"
                            min="0"
                            :aria-invalid="!!form.errors.delay_seconds"
                            @update:model-value="onDelayInput"
                        />
                        <p v-if="form.errors.delay_seconds" class="text-destructive text-xs">
                            {{ form.errors.delay_seconds }}
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
                        :event-options="props.eventOptions"
                        :period-options="props.periodOptions"
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

            <Card class="rounded-2xl border-border/70">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Komposer</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="space-y-2">
                        <Label for="broadcast-subject">Subjek email</Label>
                        <Input
                            id="broadcast-subject"
                            v-model="form.subject"
                            placeholder="Hasil screening Open Recruitment 2026"
                            :aria-invalid="!!form.errors.subject"
                        />
                        <p v-if="form.errors.subject" class="text-destructive text-xs">{{ form.errors.subject }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="broadcast-body">Isi pesan</Label>
                        <Textarea
                            id="broadcast-body"
                            v-model="form.body"
                            rows="6"
                            placeholder="Halo {nama}, ..."
                            :aria-invalid="!!form.errors.body"
                        />
                        <p v-if="form.errors.body" class="text-destructive text-xs">{{ form.errors.body }}</p>
                    </div>
                </CardContent>
            </Card>
        </form>
    </div>
</template>
