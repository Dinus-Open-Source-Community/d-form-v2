<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BroadcastComposerSection from '@/components/modules/dashboard/broadcast/BroadcastComposerSection.vue'
import BroadcastTrackingSummary from '@/components/modules/dashboard/broadcast/BroadcastTrackingSummary.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { SplitDateTimeField } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useBroadcastComposer } from '@/utils/composables/useBroadcastComposer'
import {
    buildBroadcastSnapshotRows,
    isBroadcastBodyFilled,
    isBroadcastComposerReady,
    normalizeBroadcastScheduledAt,
    parseBroadcastDelaySeconds,
} from '@/lib/broadcastHub'
import type {
    IBroadcastShowContext,
    IBroadcastShownBroadcast,
    IBroadcastSnapshot,
    TBroadcastHubStatus,
} from '@/lib/broadcastHub'
import { handleInertiaFormErrors } from '@/lib/error-message'
import { routes } from '@/lib/routes'
import { cn } from '@/lib/utils'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { Lock, MailOpen, Send } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    broadcast: IBroadcastShownBroadcast
    snapshot: IBroadcastSnapshot | null
    context: IBroadcastShowContext | null
}>()

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

/** Area edit hanya untuk draft/scheduled; processing/sent read-only + note terkunci. */
const editable = computed<boolean>(
    () => props.broadcast.status === 'draft' || props.broadcast.status === 'scheduled',
)

/** Baris snapshot read-only (source of truth per broadcast). */
const snapshotRows = computed(() => buildBroadcastSnapshotRows(props.snapshot, props.context))

const scheduleLabel = computed<string>(() => props.broadcast.scheduled_at ?? 'Draft — tanpa jadwal')
const delayLabel = computed<string>(() =>
    props.broadcast.send_delay_seconds === null ? '—' : `${props.broadcast.send_delay_seconds} detik`,
)

/** Konten tersimpan punya body nyata (untuk render read-only). */
const hasStoredBody = computed<boolean>(() => isBroadcastBodyFilled(props.broadcast.body_html))

/** Gerbang kirim M-6 atas konten tersimpan (bukan draf edit yang belum disimpan). */
const canSend = computed<boolean>(
    () =>
        editable.value &&
        isBroadcastComposerReady({ subject: props.broadcast.subject, bodyHtml: props.broadcast.body_html }),
)

/** Komposer edit (M-6: subject + body wajib untuk Simpan Perubahan). */
const composer = useBroadcastComposer({
    subject: props.broadcast.subject,
    bodyHtml: props.broadcast.body_html,
})

const editForm = useForm({
    subject: '',
    body_html: '',
    scheduled_at: props.broadcast.scheduled_at ?? '',
    send_delay_seconds: props.broadcast.send_delay_seconds,
})

function resetEditState(): void {
    composer.subject.value = props.broadcast.subject ?? ''
    composer.bodyHtml.value = props.broadcast.body_html ?? ''
    editForm.subject = ''
    editForm.body_html = ''
    editForm.scheduled_at = props.broadcast.scheduled_at ?? ''
    editForm.send_delay_seconds = props.broadcast.send_delay_seconds
    editForm.clearErrors()
}

watch(
    () => props.broadcast.id,
    () => {
        resetEditState()
    },
)

/** Jembatan number|null form ke Input (string|number): kosong berarti tanpa jeda. */
function onDelayInput(value: string | number): void {
    editForm.send_delay_seconds = parseBroadcastDelaySeconds(value)
}

function submitEdit(): void {
    if (!composer.composerReady.value) {
        handleInertiaFormErrors(
            { subject: 'Subjek email wajib diisi.', body_html: 'Isi email wajib diisi.' },
            { title: 'Konten belum lengkap' },
        )
        return
    }
    editForm.subject = composer.subject.value
    editForm.body_html = composer.bodyHtml.value
    editForm.scheduled_at = normalizeBroadcastScheduledAt(editForm.scheduled_at)
    editForm.put(routes.admin.broadcasts.update(props.broadcast.id), {
        preserveScroll: true,
        onError: (errors) => {
            handleInertiaFormErrors(errors, { title: 'Gagal menyimpan broadcast' })
        },
    })
}

const sendDialogOpen = ref(false)
const isSending = ref(false)

function confirmSend(): void {
    if (isSending.value || !canSend.value) return
    isSending.value = true
    router.post(
        routes.admin.broadcasts.send(props.broadcast.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                sendDialogOpen.value = false
                toast.success('Broadcast dikirim — pengiriman diproses di antrean.')
            },
            onError: (errors) => {
                handleInertiaFormErrors(errors, { title: 'Gagal mengirim broadcast' })
            },
            onFinish: () => {
                isSending.value = false
            },
        },
    )
}

onMounted(() => {
    setTopbar({ title: props.broadcast.name, subtitle: 'Detail broadcast' })
})
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
                        <dl class="mt-3 grid gap-3 border-t border-border/60 pt-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                    Jadwal
                                </dt>
                                <dd class="mt-1 text-sm tabular-nums">{{ scheduleLabel }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                    Jeda antar email
                                </dt>
                                <dd class="mt-1 text-sm tabular-nums">{{ delayLabel }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                    Penerima
                                </dt>
                                <dd class="mt-1 text-sm tabular-nums">
                                    {{ props.broadcast.recipient_count.toLocaleString('id-ID') }} orang
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:justify-end">
                        <Button
                            v-if="editable"
                            size="sm"
                            :disabled="!canSend"
                            :title="canSend ? undefined : 'Lengkapi subjek dan isi email tersimpan dahulu'"
                            @click="sendDialogOpen = true"
                        >
                            <Send class="mr-2 size-4" aria-hidden="true" />
                            Kirim Sekarang
                        </Button>
                    </div>
                </div>
                <p v-if="editable && !canSend" class="mt-3 text-xs text-muted-foreground">
                    Lengkapi subjek dan isi email (lalu Simpan Perubahan) sebelum mengirim.
                </p>
            </CardContent>
        </Card>

        <!-- Seam Fase 2: backend Fase 1 tidak mengirim objek tracking — tidak render apa pun. -->
        <BroadcastTrackingSummary :status="props.broadcast.status" :tracking="null" />

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Konten email</CardTitle>
                <p class="text-xs text-muted-foreground">Read-only dari konten tersimpan.</p>
            </CardHeader>
            <CardContent>
                <p class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Subjek</p>
                <p class="mt-1 text-sm font-semibold">{{ props.broadcast.subject ?? '—' }}</p>
                <div class="mt-4 border-t border-border/60 pt-4">
                    <TiptapRichHtml v-if="hasStoredBody" :html="props.broadcast.body_html" />
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border/80 px-4 py-8 text-center"
                    >
                        <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-full bg-muted">
                            <MailOpen class="size-5 text-muted-foreground" />
                        </span>
                        <p class="text-sm font-medium">Belum ada isi email tersimpan.</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card v-if="editable" class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Ubah broadcast</CardTitle>
                <p class="text-xs text-muted-foreground">Snapshot penerima tidak berubah saat menyimpan.</p>
            </CardHeader>
            <CardContent>
                <form id="broadcast-edit-form" class="grid gap-4" @submit.prevent="submitEdit">
                    <BroadcastComposerSection
                        :subject="composer.subject.value"
                        :body-html="composer.bodyHtml.value"
                        :disabled="editForm.processing"
                        :subject-error="editForm.errors.subject"
                        :body-error="editForm.errors.body_html"
                        @update:subject="composer.subject.value = $event"
                        @update:body-html="composer.bodyHtml.value = $event"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <SplitDateTimeField
                                id-prefix="broadcast-edit-scheduled"
                                v-model="editForm.scheduled_at"
                                label="Jadwal kirim"
                                layout="row"
                                picker-class="bg-white"
                                :error="editForm.errors.scheduled_at"
                                :invalid="!!editForm.errors.scheduled_at"
                            />
                            <p class="mt-2 text-xs text-muted-foreground">
                                Atur nilai baru untuk menjadwalkan; kosong berarti draft.
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="broadcast-edit-delay">Jeda antar email (detik)</Label>
                            <Input
                                id="broadcast-edit-delay"
                                :model-value="editForm.send_delay_seconds ?? ''"
                                type="number"
                                min="0"
                                :aria-invalid="!!editForm.errors.send_delay_seconds"
                                @update:model-value="onDelayInput"
                            />
                            <p v-if="editForm.errors.send_delay_seconds" class="text-destructive text-xs">
                                {{ editForm.errors.send_delay_seconds }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <Button type="submit" size="sm" :disabled="editForm.processing || !composer.composerReady.value">
                            {{ editForm.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card v-else class="rounded-2xl border-border/70">
            <CardContent class="p-4 sm:p-5">
                <p role="note" class="flex items-start gap-2 text-xs leading-relaxed text-muted-foreground">
                    <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    Broadcast {{ statusLabels[props.broadcast.status].toLowerCase() }} terkunci —
                    konten dan jadwal tidak bisa diubah (snapshot-only).
                </p>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Snapshot penerima</CardTitle>
                <p class="text-xs text-muted-foreground">Read-only — snapshot terkunci saat broadcast disimpan.</p>
            </CardHeader>
            <CardContent>
                <div v-if="props.snapshot">
                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div
                            v-for="row in snapshotRows"
                            :key="row.key"
                            class="rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5"
                        >
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd class="mt-1 text-sm font-semibold break-words">{{ row.value }}</dd>
                        </div>
                    </dl>
                    <ul
                        v-if="props.snapshot.recipients.length > 0"
                        class="mt-4 max-h-72 divide-y divide-border/60 overflow-y-auto rounded-xl border border-border/60"
                    >
                        <li
                            v-for="recipient in props.snapshot.recipients"
                            :key="recipient.email"
                            class="flex min-w-0 items-center justify-between gap-3 px-3 py-2"
                        >
                            <span class="min-w-0 truncate text-sm font-medium">
                                {{ recipient.name ?? recipient.email }}
                            </span>
                            <span class="shrink-0 truncate text-xs text-muted-foreground">{{ recipient.email }}</span>
                        </li>
                    </ul>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border/80 px-4 py-8 text-center"
                >
                    <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-full bg-muted">
                        <MailOpen class="size-5 text-muted-foreground" />
                    </span>
                    <p class="text-sm font-medium">Belum ada snapshot penerima.</p>
                </div>
            </CardContent>
        </Card>
    </div>

    <ConfirmationModal
        :open="sendDialogOpen"
        title="Kirim broadcast sekarang?"
        :description="`Email dikirim ke ${props.broadcast.recipient_count.toLocaleString('id-ID')} penerima snapshot dan tidak bisa dibatalkan.`"
        confirm-text="Kirim Sekarang"
        cancel-text="Batal"
        :loading="isSending"
        @confirm="confirmSend"
        @cancel="sendDialogOpen = false"
        @update:open="(v: boolean) => { sendDialogOpen = v }"
    />
</template>
