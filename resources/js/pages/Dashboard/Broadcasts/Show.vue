<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BroadcastTrackingSummary from '@/components/modules/dashboard/broadcast/BroadcastTrackingSummary.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { isBroadcastHubLocked } from '@/lib/broadcastHub'
import type { IBroadcastHubTracking, TBroadcastDatasetSource, TBroadcastHubStatus } from '@/lib/broadcastHub'
import { handleInertiaFormErrors } from '@/lib/error-message'
import { routes } from '@/lib/routes'
import { cn } from '@/lib/utils'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { Lock, MailOpen } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

/** Broadcast yang ditampilkan (provisional, snapshot-only). */
interface IBroadcastHubShown {
    id: string
    name: string
    status: TBroadcastHubStatus
    scheduledAt: string | null
    delaySeconds: number | null
    datasetSource: TBroadcastDatasetSource | null
    contextName: string | null
    subject: string | null
    body: string | null
}

const props = defineProps<{
    broadcast: IBroadcastHubShown
    tracking: IBroadcastHubTracking | null
}>()

const locked = computed<boolean>(() => isBroadcastHubLocked(props.broadcast.status))

const statusClasses: Record<TBroadcastHubStatus, string> = {
    draft: 'border-border bg-secondary text-secondary-foreground',
    scheduled: 'border-warning/25 bg-warning/10 text-warning-foreground',
    processing: 'border-primary/25 bg-primary/10 text-primary',
    sent: 'border-success/20 bg-success/10 text-success',
}

const statusLabels: Record<TBroadcastHubStatus, string> = {
    draft: 'Draft',
    scheduled: 'Terjadwal',
    processing: 'Diproses',
    sent: 'Terkirim',
}

const form = useForm({
    name: props.broadcast.name,
    scheduled_at: props.broadcast.scheduledAt ?? '',
    delay_seconds: props.broadcast.delaySeconds,
    subject: props.broadcast.subject ?? '',
    body: props.broadcast.body ?? '',
})

watch(
    () => props.broadcast.id,
    () => {
        form.name = props.broadcast.name
        form.scheduled_at = props.broadcast.scheduledAt ?? ''
        form.delay_seconds = props.broadcast.delaySeconds
        form.subject = props.broadcast.subject ?? ''
        form.body = props.broadcast.body ?? ''
    },
)

const activeTab = ref<string>('ringkasan')

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
    setTopbar({ title: props.broadcast.name, subtitle: 'Detail broadcast' })
})

function submit(): void {
    form.put(routes.admin.broadcasts.update(props.broadcast.id), {
        preserveScroll: true,
        onError: (errors) => {
            handleInertiaFormErrors(errors, { title: 'Gagal menyimpan broadcast' })
        },
    })
}
</script>

<template>
    <Head :title="props.broadcast.name" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 pb-8 sm:pb-10">
        <Card class="overflow-hidden rounded-xl border-border/70 shadow-sm">
            <CardContent class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                            <h1 class="min-w-0 break-words text-lg font-semibold tracking-tight sm:text-xl">
                                {{ props.broadcast.name }}
                            </h1>
                            <Badge
                                :class="
                                    cn('shrink-0 border text-[11px] font-medium', statusClasses[props.broadcast.status])
                                "
                            >
                                {{ statusLabels[props.broadcast.status] }}
                            </Badge>
                        </div>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            <span v-if="props.broadcast.contextName">{{ props.broadcast.contextName }}</span>
                            <span v-if="props.broadcast.contextName && props.broadcast.datasetSource"> · </span>
                            <span v-if="props.broadcast.datasetSource">{{ props.broadcast.datasetSource }}</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:justify-end">
                        <Button
                            type="submit"
                            form="broadcast-edit-form"
                            size="sm"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Tabs :model-value="activeTab" @update:model-value="activeTab = String($event)" class="w-full">
            <TabsList
                class="flex h-auto w-full items-center justify-start gap-6 overflow-x-auto whitespace-nowrap rounded-none border-0 border-b border-border bg-transparent p-0 text-muted-foreground"
            >
                <TabsTrigger
                    value="ringkasan"
                    class="-mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    Ringkasan
                </TabsTrigger>
                <TabsTrigger
                    value="pengaturan"
                    class="-mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    Pengaturan
                </TabsTrigger>
            </TabsList>

            <TabsContent value="ringkasan" class="mt-4">
                <BroadcastTrackingSummary :status="props.broadcast.status" :tracking="props.tracking">
                    <template #empty>
                        <div
                            class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border/80 px-4 py-8 text-center"
                        >
                            <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-full bg-muted">
                                <MailOpen class="size-5 text-muted-foreground" />
                            </span>
                            <p class="text-sm font-medium">Belum ada data pengiriman.</p>
                            <p class="max-w-sm text-xs leading-relaxed text-muted-foreground">
                                Broadcast masih draft — ringkasan tracking muncul setelah dijadwalkan.
                            </p>
                        </div>
                    </template>
                </BroadcastTrackingSummary>
            </TabsContent>

            <TabsContent value="pengaturan" class="mt-4">
                <form id="broadcast-edit-form" @submit.prevent="submit">
                    <Card class="rounded-2xl border-border/70">
                        <CardContent class="grid gap-4 p-4 sm:p-5">
                            <div
                                v-if="locked"
                                role="note"
                                class="flex items-start gap-2 rounded-xl border border-warning/25 bg-warning/10 px-3 py-2.5 text-xs leading-relaxed text-warning-foreground"
                            >
                                <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                Dataset dan komposer terkunci karena status
                                {{ statusLabels[props.broadcast.status].toLowerCase() }} (snapshot-only).
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="broadcast-edit-name">Nama broadcast</Label>
                                    <Input id="broadcast-edit-name" v-model="form.name" required />
                                    <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="broadcast-edit-scheduled">Jadwal kirim</Label>
                                    <Input id="broadcast-edit-scheduled" v-model="form.scheduled_at" type="datetime-local" />
                                    <p v-if="form.errors.scheduled_at" class="text-destructive text-xs">
                                        {{ form.errors.scheduled_at }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="broadcast-edit-delay">Jeda antar email (detik)</Label>
                                    <Input
                                        id="broadcast-edit-delay"
                                        :model-value="form.delay_seconds ?? ''"
                                        type="number"
                                        min="0"
                                        @update:model-value="onDelayInput"
                                    />
                                </div>
                            </div>

                            <fieldset :disabled="locked" class="grid gap-4">
                                <legend class="sr-only">Dataset dan komposer (terkunci saat terjadwal)</legend>
                                <div class="space-y-2">
                                    <Label for="broadcast-edit-subject">Subjek email</Label>
                                    <Input id="broadcast-edit-subject" v-model="form.subject" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="broadcast-edit-body">Isi pesan</Label>
                                    <Textarea id="broadcast-edit-body" v-model="form.body" rows="6" />
                                </div>
                            </fieldset>
                        </CardContent>
                    </Card>
                </form>
            </TabsContent>
        </Tabs>
    </div>
</template>
