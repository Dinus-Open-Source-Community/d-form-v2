<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'
import { CheckCircle2, Download, FileText, History, Trophy, XCircle } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

type FinalAction = 'accept' | 'reject' | null

type ScreeningAction = 'revision' | 'reject' | null

interface ScreeningRow {
    id: string
    decision: string
    decision_label: string
    reason: string | null
    reason_label: string | null
    notes: string | null
    acted_at: string | null
    actor: { id: string; name: string } | null
}

interface ActivityRow {
    id: string
    action: string
    old_values: Record<string, unknown> | null
    new_values: Record<string, unknown> | null
    created_at: string | null
    actor: { id: string; name: string } | null
}

interface CorrectionRow {
    id: string
    status: string
    status_label: string
    request_message: string
    review_notes: string | null
    reviewed_at: string | null
    completed_at: string | null
    reviewer: { id: string; name: string } | null
}

interface EvaluationDetail {
    speaking_score: number
    technical_score: number
    attitude_score: number
    recommendation: string
    recommendation_label: string
    notes: string | null
    is_locked: boolean
    evaluated_at: string | null
    evaluator: { id: string; name: string } | null
}

interface FinalDecisionDetail {
    membership_type: string | null
    membership_type_label: string | null
    final_division: { id: string; name: string; code: string } | null
    internal_reason: string | null
    public_message: string | null
    decided_at: string | null
    decider: { id: string; name: string } | null
}

interface ApplicationDetail {
    id: string
    registration_number: string
    full_name: string
    nim: string
    semester: number
    phone: string
    personal_email: string
    student_email: string
    instagram_username: string
    stage: string
    stage_label: string
    result: string
    result_label: string
    is_verified: boolean
    revision_required: boolean
    submitted_at: string | null
    period: { id: string; name: string } | null
    primary_division: { id: string; name: string; code: string } | null
    secondary_division: { id: string; name: string; code: string } | null
    document: {
        cv_original_name: string
        cv_mime: string
        cv_size_bytes: number
        portfolio_type: string
        portfolio_url: string | null
        portfolio_original_name: string | null
        portfolio_mime: string | null
        portfolio_size_bytes: number | null
        has_cv_file: boolean
        has_portfolio_file: boolean
    } | null
    screenings: ScreeningRow[]
    activity_logs: ActivityRow[]
    correction_requests: CorrectionRow[]
    evaluation: EvaluationDetail | null
    final_decision: FinalDecisionDetail | null
    can_screen: boolean
    can_verify: boolean
    can_decide_final: boolean
}

const props = defineProps<{
    application: ApplicationDetail
    screeningReasonOptions: { value: string; label: string }[]
    divisionOptions: { id: string; name: string; code: string }[]
    membershipTypeOptions: { value: string; label: string }[]
}>()

const page = usePage()
const user = useAuth(page.props)
const canScreen = computed(
    () => props.application.can_screen && user.value?.can_screen_recruitment_applications === true,
)
const canVerify = computed(() => props.application.can_verify && user.value?.can_screen_recruitment_applications === true)
const canReviewCorrections = computed(() => user.value?.can_review_recruitment_corrections === true)
const canDecideFinal = computed(
    () => props.application.can_decide_final && user.value?.can_decide_recruitment_final === true,
)

const correctionReviewForm = useForm({
    review_notes: '',
})

const screeningModalOpen = ref(false)
const screeningAction = ref<ScreeningAction>(null)

const finalModalOpen = ref(false)
const finalAction = ref<FinalAction>(null)

const screeningForm = useForm({
    reason: '',
    notes: '',
    public_message: '',
})

const finalAcceptForm = useForm({
    membership_type: '',
    final_division_id: props.application.primary_division?.id ?? '',
})

const finalRejectForm = useForm({
    internal_reason: '',
    public_message: '',
})

onMounted(() => {
    setTopbar({
        title: props.application.full_name,
        subtitle: props.application.registration_number,
    })
})

function openScreeningModal(action: ScreeningAction) {
    screeningAction.value = action
    screeningForm.reset()
    screeningForm.clearErrors()
    screeningModalOpen.value = true
}

function submitScreening() {
    if (screeningAction.value === 'revision') {
        screeningForm.post(routes.admin.recruitment.applications.screening.revision(props.application.id), {
            preserveScroll: true,
            onSuccess: () => {
                screeningModalOpen.value = false
            },
        })
        return
    }

    if (screeningAction.value === 'reject') {
        screeningForm.post(routes.admin.recruitment.applications.screening.reject(props.application.id), {
            preserveScroll: true,
            onSuccess: () => {
                screeningModalOpen.value = false
            },
        })
    }
}

function openFinalModal(action: FinalAction) {
    finalAction.value = action
    finalAcceptForm.reset()
    finalRejectForm.reset()
    finalAcceptForm.final_division_id = props.application.primary_division?.id ?? ''
    finalAcceptForm.clearErrors()
    finalRejectForm.clearErrors()
    finalModalOpen.value = true
}

function submitFinalDecision() {
    if (finalAction.value === 'accept') {
        finalAcceptForm.post(routes.admin.recruitment.applications.final.accept(props.application.id), {
            preserveScroll: true,
            onSuccess: () => {
                finalModalOpen.value = false
            },
        })
        return
    }

    if (finalAction.value === 'reject') {
        finalRejectForm.post(routes.admin.recruitment.applications.final.reject(props.application.id), {
            preserveScroll: true,
            onSuccess: () => {
                finalModalOpen.value = false
            },
        })
    }
}

function passApplication() {
    router.post(
        routes.admin.recruitment.applications.screening.pass(props.application.id),
        {},
        { preserveScroll: true },
    )
}

function verifyApplication() {
    router.post(routes.admin.recruitment.applications.verify(props.application.id), {}, { preserveScroll: true })
}

function approveCorrection(correctionId: string) {
    correctionReviewForm.post(routes.admin.recruitment.corrections.approve(correctionId), {
        preserveScroll: true,
        onSuccess: () => correctionReviewForm.reset(),
    })
}

function rejectCorrection(correctionId: string) {
    correctionReviewForm.post(routes.admin.recruitment.corrections.reject(correctionId), {
        preserveScroll: true,
        onSuccess: () => correctionReviewForm.reset(),
    })
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const modalTitle = computed(() => {
    if (screeningAction.value === 'revision') return 'Minta revisi'
    if (screeningAction.value === 'reject') return 'Tolak applicant'
    return 'Keputusan screening'
})

const finalModalTitle = computed(() => {
    if (finalAction.value === 'accept') return 'Terima applicant'
    if (finalAction.value === 'reject') return 'Tolak applicant (final)'
    return 'Keputusan final'
})

const defaultTab = computed(() => {
    const { stage, revision_required, correction_requests } = props.application
    const hasPendingCorrection = correction_requests.some((c) => c.status === 'pending')

    if (revision_required || hasPendingCorrection) return 'screening'
    if (stage === 'submitted' || stage === 'screening') return 'screening'
    if (stage === 'final_review' || stage === 'completed') return 'final'

    return 'profile'
})
</script>

<template>
    <Head :title="application.full_name" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <Button v-if="canVerify" size="sm" variant="secondary" @click="verifyApplication">
                Verifikasi pendaftaran
            </Button>
            <Button v-if="canDecideFinal" size="sm" variant="destructive" @click="openFinalModal('reject')">
                <XCircle class="mr-2 size-4" />
                Tolak final
            </Button>
            <Button v-if="canDecideFinal" size="sm" @click="openFinalModal('accept')">
                <Trophy class="mr-2 size-4" />
                Terima
            </Button>
            <Button v-if="canScreen" size="sm" variant="outline" @click="openScreeningModal('revision')">
                Minta revisi
            </Button>
            <Button v-if="canScreen" size="sm" variant="destructive" @click="openScreeningModal('reject')">
                <XCircle class="mr-2 size-4" />
                Tolak
            </Button>
            <Button v-if="canScreen" size="sm" @click="passApplication">
                <CheckCircle2 class="mr-2 size-4" />
                Lolos screening
            </Button>
        </div>

        <div
            v-if="application.is_verified"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            Pendaftaran sudah diverifikasi staff.
        </div>

        <div
            v-if="application.revision_required"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Applicant diminta melakukan revisi pendaftaran.
        </div>

        <Tabs :default-value="defaultTab" class="w-full">
            <TabsList class="grid w-full grid-cols-4">
                <TabsTrigger value="profile">Profil</TabsTrigger>
                <TabsTrigger value="screening">Screening</TabsTrigger>
                <TabsTrigger value="final">Final</TabsTrigger>
                <TabsTrigger value="history">Riwayat</TabsTrigger>
            </TabsList>

            <TabsContent value="profile" class="mt-4 space-y-4">
                <Card class="rounded-2xl border-border/70">
                    <CardContent class="grid gap-4 p-6 sm:grid-cols-2">
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">NIM</p>
                            <p class="font-medium">{{ application.nim }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Semester</p>
                            <p class="font-medium">{{ application.semester }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Telepon</p>
                            <p class="font-medium">{{ application.phone }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Instagram</p>
                            <p class="font-medium">@{{ application.instagram_username }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Email pribadi</p>
                            <p class="font-medium">{{ application.personal_email }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Email kampus</p>
                            <p class="font-medium">{{ application.student_email }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Divisi utama</p>
                            <p class="font-medium">{{ application.primary_division?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Divisi cadangan</p>
                            <p class="font-medium">{{ application.secondary_division?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Periode</p>
                            <p class="font-medium">{{ application.period?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Hasil</p>
                            <p class="font-medium">{{ application.result_label }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border-border/70">
                    <CardContent class="space-y-4 p-6">
                        <p class="font-medium">Dokumen</p>
                        <div v-if="application.document" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4">
                                <div class="flex items-center gap-3">
                                    <FileText class="text-muted-foreground size-5" />
                                    <div>
                                        <p class="font-medium">{{ application.document.cv_original_name }}</p>
                                        <p class="text-muted-foreground text-xs">
                                            CV · {{ formatBytes(application.document.cv_size_bytes) }}
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    v-if="application.document.has_cv_file"
                                    as-child
                                    variant="outline"
                                    size="sm"
                                >
                                    <a :href="routes.admin.recruitment.applications.document(application.id, 'cv')">
                                        <Download class="mr-2 size-4" />
                                        Unduh CV
                                    </a>
                                </Button>
                            </div>

                            <div class="rounded-xl border p-4">
                                <p class="font-medium">Portfolio</p>
                                <p v-if="application.document.portfolio_type === 'url'" class="text-muted-foreground mt-1 text-sm">
                                    <a
                                        :href="application.document.portfolio_url ?? '#'"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary underline"
                                    >
                                        {{ application.document.portfolio_url }}
                                    </a>
                                </p>
                                <div
                                    v-else-if="application.document.has_portfolio_file"
                                    class="mt-2 flex flex-wrap items-center justify-between gap-3"
                                >
                                    <p class="text-sm">{{ application.document.portfolio_original_name }}</p>
                                    <Button as-child variant="outline" size="sm">
                                        <a
                                            :href="
                                                routes.admin.recruitment.applications.document(
                                                    application.id,
                                                    'portfolio',
                                                )
                                            "
                                        >
                                            <Download class="mr-2 size-4" />
                                            Unduh portfolio
                                        </a>
                                    </Button>
                                </div>
                                <p v-else class="text-muted-foreground mt-1 text-sm">Tidak ada portfolio.</p>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground text-sm">Dokumen belum tersedia.</p>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="screening" class="mt-4 space-y-4">
                <Card class="rounded-2xl border-border/70">
                    <CardContent class="space-y-4 p-6">
                        <p class="font-medium">Riwayat screening</p>
                        <div
                            v-for="screening in application.screenings"
                            :key="screening.id"
                            class="rounded-xl border p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium">{{ screening.decision_label }}</p>
                                    <p v-if="screening.reason_label" class="text-muted-foreground text-sm">
                                        {{ screening.reason_label }}
                                    </p>
                                </div>
                                <p class="text-muted-foreground text-xs">
                                    {{ screening.actor?.name ?? 'Staff' }}
                                </p>
                            </div>
                            <p v-if="screening.notes" class="mt-2 text-sm">{{ screening.notes }}</p>
                        </div>
                        <p v-if="application.screenings.length === 0" class="text-muted-foreground text-sm">
                            Belum ada keputusan screening.
                        </p>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border-border/70">
                    <CardContent class="space-y-4 p-6">
                        <p class="font-medium">Permintaan koreksi</p>
                        <div
                            v-for="correction in application.correction_requests"
                            :key="correction.id"
                            class="rounded-xl border p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="font-medium">{{ correction.status_label }}</p>
                                <p v-if="correction.reviewer" class="text-muted-foreground text-xs">
                                    {{ correction.reviewer.name }}
                                </p>
                            </div>
                            <p class="mt-2 text-sm">{{ correction.request_message }}</p>
                            <p v-if="correction.review_notes" class="text-muted-foreground mt-2 text-sm">
                                Catatan: {{ correction.review_notes }}
                            </p>
                            <div
                                v-if="canReviewCorrections && correction.status === 'pending'"
                                class="mt-4 flex flex-wrap gap-2"
                            >
                                <Button size="sm" @click="approveCorrection(correction.id)">
                                    Setujui
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    @click="rejectCorrection(correction.id)"
                                >
                                    Tolak
                                </Button>
                            </div>
                        </div>
                        <p v-if="application.correction_requests.length === 0" class="text-muted-foreground text-sm">
                            Belum ada permintaan koreksi.
                        </p>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="final" class="mt-4 space-y-4">
                <Card v-if="application.evaluation" class="rounded-2xl border-border/70">
                    <CardContent class="space-y-3 p-6">
                        <p class="font-medium">Evaluasi interviewer</p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">Speaking</p>
                                <p class="font-medium">{{ application.evaluation.speaking_score }}/10</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">Technical</p>
                                <p class="font-medium">{{ application.evaluation.technical_score }}/10</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">Attitude</p>
                                <p class="font-medium">{{ application.evaluation.attitude_score }}/10</p>
                            </div>
                        </div>
                        <p class="text-sm">
                            Rekomendasi:
                            <span class="font-medium">{{ application.evaluation.recommendation_label }}</span>
                        </p>
                        <p v-if="application.evaluation.notes" class="text-muted-foreground text-sm">
                            {{ application.evaluation.notes }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{ application.evaluation.evaluator?.name ?? 'Interviewer' }}
                            · {{ application.evaluation.is_locked ? 'Terkunci' : 'Draft' }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="application.final_decision" class="rounded-2xl border-border/70">
                    <CardContent class="space-y-3 p-6">
                        <p class="font-medium">Keputusan final</p>
                        <div v-if="application.final_decision.membership_type_label" class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">Keanggotaan</p>
                                <p class="font-medium">{{ application.final_decision.membership_type_label }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs uppercase">Divisi penempatan</p>
                                <p class="font-medium">{{ application.final_decision.final_division?.name ?? '—' }}</p>
                            </div>
                        </div>
                        <div v-if="application.final_decision.internal_reason">
                            <p class="text-muted-foreground text-xs uppercase">Alasan internal</p>
                            <p class="text-sm">{{ application.final_decision.internal_reason }}</p>
                        </div>
                        <div v-if="application.final_decision.public_message">
                            <p class="text-muted-foreground text-xs uppercase">Pesan applicant</p>
                            <p class="text-sm">{{ application.final_decision.public_message }}</p>
                        </div>
                        <p class="text-muted-foreground text-xs">
                            {{ application.final_decision.decider?.name ?? 'Staff' }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="canDecideFinal" class="rounded-2xl border-dashed border-border/70">
                    <CardContent class="flex flex-wrap gap-3 p-6">
                        <Button size="sm" @click="openFinalModal('accept')">
                            <Trophy class="mr-2 size-4" />
                            Terima (AA / Member)
                        </Button>
                        <Button size="sm" variant="destructive" @click="openFinalModal('reject')">
                            <XCircle class="mr-2 size-4" />
                            Tolak final
                        </Button>
                    </CardContent>
                </Card>

                <p
                    v-if="!application.evaluation && !application.final_decision && !canDecideFinal"
                    class="text-muted-foreground text-sm"
                >
                    Belum ada data final review.
                </p>
            </TabsContent>

            <TabsContent value="history" class="mt-4">
                <Card class="rounded-2xl border-border/70">
                    <CardContent class="space-y-4 p-6">
                        <div
                            v-for="log in application.activity_logs"
                            :key="log.id"
                            class="flex gap-3 rounded-xl border p-4"
                        >
                            <History class="text-muted-foreground mt-0.5 size-4 shrink-0" />
                            <div>
                                <p class="font-medium">{{ log.action }}</p>
                                <p class="text-muted-foreground text-xs">
                                    {{ log.actor?.name ?? 'Sistem' }}
                                </p>
                            </div>
                        </div>
                        <p v-if="application.activity_logs.length === 0" class="text-muted-foreground text-sm">
                            Belum ada aktivitas tercatat.
                        </p>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>

    <Dialog v-model:open="screeningModalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ modalTitle }}</DialogTitle>
                <DialogDescription>
                    Alasan wajib diisi. Catatan tambahan diperlukan jika memilih "Lainnya".
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitScreening">
                <div class="space-y-2">
                    <Label for="reason">Alasan</Label>
                    <select
                        id="reason"
                        v-model="screeningForm.reason"
                        class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                        required
                    >
                        <option value="" disabled>Pilih alasan</option>
                        <option
                            v-for="opt in screeningReasonOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                    <p v-if="screeningForm.errors.reason" class="text-destructive text-xs">
                        {{ screeningForm.errors.reason }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="notes">Catatan</Label>
                    <textarea
                        id="notes"
                        v-model="screeningForm.notes"
                        rows="3"
                        class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Catatan internal untuk tim..."
                    />
                    <p v-if="screeningForm.errors.notes" class="text-destructive text-xs">
                        {{ screeningForm.errors.notes }}
                    </p>
                </div>

                <div v-if="screeningAction === 'reject'" class="space-y-2">
                    <Label for="public_message">Pesan untuk applicant (opsional)</Label>
                    <textarea
                        id="public_message"
                        v-model="screeningForm.public_message"
                        rows="2"
                        class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                    />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="screeningModalOpen = false">
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        :disabled="screeningForm.processing"
                        :variant="screeningAction === 'reject' ? 'destructive' : 'default'"
                    >
                        Simpan keputusan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="finalModalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ finalModalTitle }}</DialogTitle>
                <DialogDescription>
                    <span v-if="finalAction === 'accept'">
                        Pilih tipe keanggotaan dan divisi penempatan final.
                    </span>
                    <span v-else>
                        Alasan internal hanya untuk staff. Pesan applicant akan tampil di tracking portal.
                    </span>
                </DialogDescription>
            </DialogHeader>

            <form
                v-if="finalAction === 'accept'"
                class="space-y-4"
                @submit.prevent="submitFinalDecision"
            >
                <div class="space-y-2">
                    <Label for="membership_type">Tipe keanggotaan</Label>
                    <select
                        id="membership_type"
                        v-model="finalAcceptForm.membership_type"
                        class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                        required
                    >
                        <option value="" disabled>Pilih tipe</option>
                        <option
                            v-for="opt in membershipTypeOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                    <p v-if="finalAcceptForm.errors.membership_type" class="text-destructive text-xs">
                        {{ finalAcceptForm.errors.membership_type }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="final_division_id">Divisi penempatan</Label>
                    <select
                        id="final_division_id"
                        v-model="finalAcceptForm.final_division_id"
                        class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                        required
                    >
                        <option value="" disabled>Pilih divisi</option>
                        <option v-for="div in divisionOptions" :key="div.id" :value="div.id">
                            {{ div.name }}
                        </option>
                    </select>
                    <p v-if="finalAcceptForm.errors.final_division_id" class="text-destructive text-xs">
                        {{ finalAcceptForm.errors.final_division_id }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="finalModalOpen = false">
                        Batal
                    </Button>
                    <Button type="submit" :disabled="finalAcceptForm.processing">
                        Simpan keputusan
                    </Button>
                </DialogFooter>
            </form>

            <form
                v-else-if="finalAction === 'reject'"
                class="space-y-4"
                @submit.prevent="submitFinalDecision"
            >
                <div class="space-y-2">
                    <Label for="internal_reason">Alasan internal</Label>
                    <textarea
                        id="internal_reason"
                        v-model="finalRejectForm.internal_reason"
                        rows="3"
                        class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                        required
                    />
                    <p v-if="finalRejectForm.errors.internal_reason" class="text-destructive text-xs">
                        {{ finalRejectForm.errors.internal_reason }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="final_public_message">Pesan untuk applicant</Label>
                    <textarea
                        id="final_public_message"
                        v-model="finalRejectForm.public_message"
                        rows="3"
                        class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                        required
                    />
                    <p v-if="finalRejectForm.errors.public_message" class="text-destructive text-xs">
                        {{ finalRejectForm.errors.public_message }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="finalModalOpen = false">
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="finalRejectForm.processing"
                    >
                        Tolak applicant
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
