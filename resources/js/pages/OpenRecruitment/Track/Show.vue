<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { routes } from '@/lib/routes'
import { CalendarClock, CheckCircle2, Circle, CircleDot, LogOut, MapPin, Pencil, QrCode, Users } from 'lucide-vue-next'

defineOptions({ layout: FormFillLayout })

interface TimelineItem {
    key: string
    label: string
    status: 'completed' | 'current' | 'upcoming'
    note?: string | null
}

interface TrackingPayload {
    application: {
        registration_number: string
        full_name: string
        nim: string
        semester: number
        stage: string
        stage_label: string
        result: string
        result_label: string
        revision_required: boolean
        is_verified: boolean
        primary_division: string | null
        secondary_division: string | null
        submitted_at: string | null
    }
    period: { name: string | null }
    timeline: TimelineItem[]
    interview: {
        scheduled_at: string
        location: string
        room: string
        status: string
    } | null
    queue: {
        queue_number: number
        status: string
    } | null
    attendance: {
        checked_in_at: string
        method: string
    } | null
    attendance_qr_base64: string | null
    final: {
        membership_type: string | null
        final_division: string | null
        public_message: string | null
        result: string
        result_label: string
    } | null
    edit: {
        can_edit: boolean
        can_request_correction: boolean
        latest_correction: {
            id: string
            status: string
            status_label: string
            request_message: string
            review_notes: string | null
        } | null
    }
    feedback: {
        can_submit: boolean
        submitted: boolean
        submitted_at: string | null
    }
}

const props = defineProps<{
    tracking: TrackingPayload
    logoutUrl: string
    editUrl: string
    correctionUrl: string
    feedbackUrl: string
}>()

const correctionModalOpen = ref(false)
const correctionForm = useForm({
    request_message: '',
})

const submittedLabel = computed(() => {
    if (!props.tracking.application.submitted_at) return null
    return new Date(props.tracking.application.submitted_at).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
})

const interviewSchedule = computed(() => {
    if (!props.tracking.interview?.scheduled_at) return null
    return new Date(props.tracking.interview.scheduled_at).toLocaleString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
})

function timelineIcon(status: TimelineItem['status']) {
    if (status === 'completed') return CheckCircle2
    if (status === 'current') return CircleDot
    return Circle
}

function logout() {
    router.post(props.logoutUrl)
}

function submitCorrection() {
    correctionForm.post(props.correctionUrl, {
        preserveScroll: true,
        onSuccess: () => {
            correctionModalOpen.value = false
            correctionForm.reset()
        },
    })
}
</script>

<template>
    <Head title="Progress OpRec" />

    <div class="mx-auto max-w-2xl space-y-6 px-2 pb-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-primary text-xs font-semibold tracking-wide uppercase">Tracking OpRec</p>
                <h1 class="text-2xl font-bold">{{ tracking.application.full_name }}</h1>
                <p class="text-muted-foreground mt-1 font-mono text-sm">
                    {{ tracking.application.registration_number }}
                </p>
                <p v-if="tracking.period.name" class="text-muted-foreground text-sm">
                    {{ tracking.period.name }}
                </p>
            </div>
            <Button variant="outline" size="sm" @click="logout">
                <LogOut class="mr-2 size-4" />
                Keluar
            </Button>
        </div>

        <div
            v-if="tracking.application.revision_required"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Tim meminta revisi pendaftaran. Silakan perbarui data kamu.
        </div>

        <div v-if="tracking.edit.can_edit || tracking.edit.can_request_correction" class="flex flex-wrap gap-2">
            <Button v-if="tracking.edit.can_edit" as-child size="sm">
                <Link :href="editUrl">
                    <Pencil class="mr-2 size-4" />
                    Edit pendaftaran
                </Link>
            </Button>
            <Button
                v-if="tracking.edit.can_request_correction"
                variant="outline"
                size="sm"
                @click="correctionModalOpen = true"
            >
                Ajukan permintaan koreksi
            </Button>
        </div>

        <Card
            v-if="tracking.edit.latest_correction"
            class="rounded-2xl border-border/70"
        >
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Permintaan koreksi</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <p>
                    Status:
                    <span class="font-medium">{{ tracking.edit.latest_correction.status_label }}</span>
                </p>
                <p class="text-muted-foreground">{{ tracking.edit.latest_correction.request_message }}</p>
                <p v-if="tracking.edit.latest_correction.review_notes" class="text-muted-foreground">
                    Catatan staff: {{ tracking.edit.latest_correction.review_notes }}
                </p>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Status saat ini</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
                <p class="text-lg font-medium">{{ tracking.application.stage_label }}</p>
                <p class="text-muted-foreground text-sm">{{ tracking.application.result_label }}</p>
                <p v-if="submittedLabel" class="text-muted-foreground text-xs">
                    Dikirim: {{ submittedLabel }}
                </p>
                <div class="text-muted-foreground flex flex-wrap gap-4 pt-2 text-sm">
                    <span>Divisi utama: {{ tracking.application.primary_division ?? '—' }}</span>
                    <span v-if="tracking.application.secondary_division">
                        Cadangan: {{ tracking.application.secondary_division }}
                    </span>
                </div>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Timeline proses</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-for="item in tracking.timeline"
                    :key="item.key"
                    class="flex gap-3"
                >
                    <component
                        :is="timelineIcon(item.status)"
                        class="mt-0.5 size-5 shrink-0"
                        :class="{
                            'text-primary': item.status === 'current',
                            'text-emerald-600': item.status === 'completed',
                            'text-muted-foreground/50': item.status === 'upcoming',
                        }"
                    />
                    <div class="min-w-0 flex-1">
                        <p
                            class="font-medium"
                            :class="item.status === 'upcoming' ? 'text-muted-foreground' : 'text-foreground'"
                        >
                            {{ item.label }}
                        </p>
                        <p v-if="item.note" class="text-amber-700 dark:text-amber-400 mt-1 text-sm">
                            {{ item.note }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card v-if="tracking.interview" class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <CalendarClock class="size-5" />
                    Jadwal interview
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <p v-if="interviewSchedule" class="font-medium">{{ interviewSchedule }}</p>
                <p class="text-muted-foreground flex items-center gap-2">
                    <MapPin class="size-4 shrink-0" />
                    {{ tracking.interview.location }} · Ruang {{ tracking.interview.room }}
                </p>
                <p class="text-muted-foreground">Status: {{ tracking.interview.status }}</p>
            </CardContent>
        </Card>

        <Card
            v-if="tracking.attendance_qr_base64 && !tracking.attendance"
            class="rounded-2xl border-border/70"
        >
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <QrCode class="size-5" />
                    QR code absensi
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 text-center text-sm">
                <img
                    :src="`data:image/png;base64,${tracking.attendance_qr_base64}`"
                    alt="QR code absensi interview"
                    class="mx-auto size-56 rounded-xl border border-border/70 bg-white p-2"
                />
                <p class="text-muted-foreground">
                    Tunjukkan QR code ini kepada panitia saat tiba di lokasi interview.
                    Panitia akan memindainya melalui scanner admin — kamu tidak perlu check-in sendiri.
                </p>
            </CardContent>
        </Card>

        <Card v-if="tracking.attendance" class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Sudah check-in</CardTitle>
            </CardHeader>
            <CardContent class="text-muted-foreground text-sm">
                <p>
                    Waktu check-in:
                    {{
                        tracking.attendance.checked_in_at
                            ? new Date(tracking.attendance.checked_in_at).toLocaleString('id-ID')
                            : '—'
                    }}
                </p>
            </CardContent>
        </Card>

        <Card v-if="tracking.queue" class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <Users class="size-5" />
                    Antrean interview
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-2xl font-semibold tabular-nums">#{{ tracking.queue.queue_number }}</p>
                <p class="text-muted-foreground text-sm">Status: {{ tracking.queue.status }}</p>
            </CardContent>
        </Card>

        <Card v-if="tracking.final" class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Keputusan akhir</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <p class="font-medium">{{ tracking.final.result_label }}</p>
                <p v-if="tracking.final.membership_type">
                    Keanggotaan: {{ tracking.final.membership_type }}
                </p>
                <p v-if="tracking.final.final_division">
                    Divisi: {{ tracking.final.final_division }}
                </p>
                <p v-if="tracking.final.public_message" class="text-muted-foreground">
                    {{ tracking.final.public_message }}
                </p>
            </CardContent>
        </Card>

        <Card v-if="tracking.feedback.can_submit || tracking.feedback.submitted" class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Feedback OpRec</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 text-sm">
                <p v-if="tracking.feedback.submitted" class="text-muted-foreground">
                    Terima kasih! Feedback kamu sudah kami terima
                    {{
                        tracking.feedback.submitted_at
                            ? ` pada ${new Date(tracking.feedback.submitted_at).toLocaleString('id-ID')}`
                            : ''
                    }}.
                </p>
                <Button v-else-if="tracking.feedback.can_submit" as-child>
                    <Link :href="feedbackUrl">Isi feedback</Link>
                </Button>
            </CardContent>
        </Card>

        <div class="text-center">
            <Button as-child variant="link">
                <Link :href="routes.openRecruitment.landing">Kembali ke landing OpRec</Link>
            </Button>
        </div>
    </div>

    <Dialog v-model:open="correctionModalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Permintaan koreksi</DialogTitle>
                <DialogDescription>
                    Jelaskan data apa yang perlu diperbaiki. Tim akan meninjau permintaanmu.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitCorrection">
                <div class="space-y-2">
                    <Label for="request_message">Pesan</Label>
                    <textarea
                        id="request_message"
                        v-model="correctionForm.request_message"
                        rows="4"
                        required
                        minlength="10"
                        class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Contoh: NIM saya salah ketik..."
                    />
                    <p v-if="correctionForm.errors.request_message" class="text-destructive text-xs">
                        {{ correctionForm.errors.request_message }}
                    </p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="correctionModalOpen = false">
                        Batal
                    </Button>
                    <Button type="submit" :disabled="correctionForm.processing">Kirim permintaan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
