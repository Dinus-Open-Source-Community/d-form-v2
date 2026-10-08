<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { ComponentPublicInstance } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { SimpleSelect } from '@/components/ui/simple-select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { applicantAllowsTrackingResend, userAllowsTrackingResend } from '@/lib/recruitmentApplicantCapabilities'
import ApplicantEmailingSection from './ApplicantEmailingSection.vue'
import type {
    IEmailResendPrereq,
    IEmailResendTypeStatus,
    TRecruitmentEmailResendType,
} from '@/lib/recruitmentEmailResend'
import { routes } from '@/lib/routes'
import { showErrorToast } from '@/lib/error-message'
import { isCheckboxOptionSelected, toggleCheckboxSelection } from '@/lib/formCheckboxAnswers'
import useAuth from '@/utils/composables/useAuth'
import {
    CheckCircle2,
    ClipboardCheck,
    Download,
    ExternalLink,
    FileText,
    GraduationCap,
    History,
    Instagram,
    Lock,
    Mail,
    MicVocal,
    PenLine,
    Trophy,
    User,
    Users,
    VideoOff,
    XCircle,
} from 'lucide-vue-next'

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
    division?: string | null
    division_id?: string | null
    save_count?: number
    saves_remaining?: number
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

export interface ApplicationDetail {
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
        instagram_follow_original_name: string | null
        instagram_follow_mime: string | null
        instagram_follow_size_bytes: number | null
        twibbon_url: string | null
        has_cv_file: boolean
        has_portfolio_file: boolean
        has_instagram_follow_file: boolean
    } | null
    screenings: ScreeningRow[]
    activity_logs: ActivityRow[]
    correction_requests: CorrectionRow[]
    evaluation: EvaluationDetail | null
    evaluations: {
        primary: EvaluationDetail | null
        secondary: EvaluationDetail | null
    }
    final_decision: FinalDecisionDetail | null
    can_screen: boolean
    can_verify: boolean
    can_decide_final: boolean
    can_resend_tracking: boolean
    email_resend_status?: Partial<Record<TRecruitmentEmailResendType, IEmailResendTypeStatus>> | null
    email_resend_prereq?: IEmailResendPrereq | null
}

const props = withDefaults(
    defineProps<{
        application: ApplicationDetail
        screeningReasonOptions?: { value: string; label: string }[]
        divisionOptions?: { id: string; name: string; code: string }[]
        membershipTypeOptions?: { value: string; label: string }[]
        readonly?: boolean
        hideRevisionAction?: boolean
        hideActions?: boolean
        whatsappGroupUrl?: string | null
    }>(),
    {
        screeningReasonOptions: () => [],
        divisionOptions: () => [],
        membershipTypeOptions: () => [],
        readonly: false,
        hideRevisionAction: false,
        hideActions: false,
        whatsappGroupUrl: null,
    },
)

const page = usePage()
const user = useAuth(page.props)

const emit = defineEmits<{ submitted: []; resent: [] }>()

const canScreen = computed(
    () => props.application.can_screen && user.value?.can_screen_recruitment_applications === true,
)
const canVerify = computed(() => props.application.can_verify && user.value?.can_screen_recruitment_applications === true)
const canResendTracking = computed(
    () =>
        applicantAllowsTrackingResend(props.application) && userAllowsTrackingResend(user.value),
)
const canReviewCorrections = computed(() => user.value?.can_review_recruitment_corrections === true)
const canDecideFinal = computed(
    () => props.application.can_decide_final && user.value?.can_decide_recruitment_final === true,
)

const correctionReviewForm = useForm({
    review_notes: '',
})

const screeningModalOpen = ref(false)
const screeningAction = ref<ScreeningAction>(null)

const confirmOpen = ref(false)
const confirmAction = ref<'verify' | 'pass' | 'reject' | 'resend_tracking' | null>(null)

const screeningForm = useForm({
    reason: '',
    notes: '',
    public_message: '',
    sections: [] as string[],
})

const revisionSectionOptions: { value: string; label: string }[] = [
    { value: 'data_diri', label: 'Data diri' },
    { value: 'divisi', label: 'Divisi' },
    { value: 'cv', label: 'CV' },
    { value: 'portfolio', label: 'Portofolio' },
]

function toggleRevisionSection(value: string, checked: boolean) {
    screeningForm.sections = toggleCheckboxSelection(screeningForm.sections, value, checked)
}

function openScreeningModal(action: ScreeningAction) {
    screeningAction.value = action
    screeningForm.reset()
    screeningForm.clearErrors()
    screeningModalOpen.value = true
}

function openRevisionModal() {
    openScreeningModal('revision')
}

defineExpose({
    openRevisionModal,
    openScreeningModal,
    verifyApplication,
    passApplication,
    requestConfirm,
    requestResendTracking,
    resendTrackingApplication,
    openFinalConfirm,
    openFinalAcceptForDivision,
    openFinalReject,
})

function submitScreening() {
    if (screeningAction.value === 'revision') {
        screeningForm.post(routes.admin.recruitment.applications.screening.revision(props.application.id), {
            preserveScroll: true,
            onSuccess: () => {
                screeningModalOpen.value = false
                toast.success('Permintaan revisi telah dikirim.')
                emit('submitted')
            },
            onError: () => showErrorToast('Gagal mengirim permintaan revisi.'),
        })
        return
    }

    if (screeningAction.value === 'reject') {
        requestConfirm('reject')
    }
}

function postScreeningReject() {
    screeningForm.post(routes.admin.recruitment.applications.screening.reject(props.application.id), {
        preserveScroll: true,
        onSuccess: () => {
            screeningModalOpen.value = false
            toast.success('Applicant ditolak pada tahap screening.')
            emit('submitted')
        },
        onError: () => showErrorToast('Gagal menolak applicant.'),
    })
}

type FinalDecisionChoice = 'accept_aa' | 'accept_member' | 'reject'

const finalConfirmOpen = ref(false)
const finalChoice = ref<FinalDecisionChoice | null>(null)
const finalCancelRef = ref<ComponentPublicInstance | null>(null)
const finalDivisionName = ref<string>('')

const finalConfirmForm = useForm({
    membership_type: '',
    final_division_id: '',
    internal_reason: '',
    public_message: '',
})

const showFinalDecision = computed<boolean>((): boolean => {
    if (props.readonly) return false
    if (!canDecideFinal.value) return false
    if (props.application.final_decision) return false
    if (props.application.stage === 'completed') return false
    return true
})

const isFinalRejectChoice = computed<boolean>((): boolean => finalChoice.value === 'reject')

const finalChoiceActionLabel = computed<string>((): string => {
    if (finalChoice.value === 'accept_aa') return 'Diterima sebagai AA'
    if (finalChoice.value === 'accept_member') return 'Diterima sebagai Member'
    return 'Ditolak'
})

const primaryDivisionId = computed<string>(
    (): string =>
        props.application.evaluations?.primary?.division_id ??
        props.application.primary_division?.id ??
        '',
)

const primaryDivisionName = computed<string>(
    (): string =>
        props.application.evaluations?.primary?.division ??
        props.application.primary_division?.name ??
        '—',
)

const secondaryDivisionId = computed<string>(
    (): string =>
        props.application.evaluations?.secondary?.division_id ??
        props.application.secondary_division?.id ??
        '',
)

const secondaryDivisionName = computed<string>(
    (): string =>
        props.application.evaluations?.secondary?.division ??
        props.application.secondary_division?.name ??
        '—',
)

function openFinalConfirm(choice: FinalDecisionChoice, divisionId?: string, divisionName?: string): void {
    finalChoice.value = choice
    finalConfirmForm.reset()
    finalConfirmForm.clearErrors()
    if (choice === 'accept_aa' || choice === 'accept_member') {
        finalConfirmForm.membership_type = choice === 'accept_aa' ? 'aa' : 'member'
        finalConfirmForm.final_division_id = divisionId ?? ''
        finalDivisionName.value = divisionName ?? ''
    } else {
        finalConfirmForm.membership_type = ''
        finalConfirmForm.final_division_id = ''
        finalDivisionName.value = ''
    }
    finalConfirmOpen.value = true
}

function openFinalAcceptForDivision(
    divisionId: string,
    divisionName: string,
    membershipType: 'aa' | 'member',
): void {
    openFinalConfirm(membershipType === 'aa' ? 'accept_aa' : 'accept_member', divisionId, divisionName)
}

function openFinalReject(): void {
    openFinalConfirm('reject')
}

function focusFinalCancel(event: Event): void {
    event.preventDefault()
    const target: unknown = finalCancelRef.value?.$el
    if (target instanceof HTMLElement) target.focus()
}

function submitFinalConfirm(): void {
    if (finalChoice.value === 'accept_aa' || finalChoice.value === 'accept_member') {
        finalConfirmForm.post(routes.admin.recruitment.applications.final.accept(props.application.id), {
            preserveScroll: true,
            onSuccess: () => {
                finalConfirmOpen.value = false
                toast.success('Applicant diterima. Email hasil telah dikirim.')
                emit('submitted')
            },
            onError: () => showErrorToast('Gagal menyimpan keputusan final.'),
        })
        return
    }

    if (finalChoice.value === 'reject') {
        finalConfirmForm.post(routes.admin.recruitment.applications.final.reject(props.application.id), {
            preserveScroll: true,
            onSuccess: () => {
                finalConfirmOpen.value = false
                toast.success('Applicant ditolak. Email hasil telah dikirim.')
                emit('submitted')
            },
            onError: () => showErrorToast('Gagal menyimpan keputusan final.'),
        })
    }
}

function requestConfirm(action: 'verify' | 'pass' | 'reject' | 'resend_tracking') {
    confirmAction.value = action
    confirmOpen.value = true
}

function requestResendTracking() {
    requestConfirm('resend_tracking')
}

function executeConfirmed() {
    const action = confirmAction.value
    confirmOpen.value = false

    if (action === 'verify') {
        verifyApplication()
        return
    }

    if (action === 'pass') {
        if (!hasGroupLink.value) {
            confirmOpen.value = false
            openGroupLinkDialog()
            return
        }
        passApplication()
        return
    }

    if (action === 'reject') {
        postScreeningReject()
        return
    }

    if (action === 'resend_tracking') {
        resendTrackingApplication()
    }
}

const confirmTitle = computed(() => {
    if (confirmAction.value === 'verify') return 'Verifikasi pendaftaran'
    if (confirmAction.value === 'pass') return 'Loloskan applicant'
    if (confirmAction.value === 'reject') return 'Tolak applicant'
    if (confirmAction.value === 'resend_tracking') return 'Kirim ulang informasi tracking'
    return 'Konfirmasi'
})

const confirmQuestion = computed(() => {
    const who = `${props.application.full_name} (${props.application.registration_number})`

    if (confirmAction.value === 'verify') return `Verifikasi pendaftaran ${who}?`
    if (confirmAction.value === 'pass') return `Loloskan ${who} ke tahap berikutnya?`
    if (confirmAction.value === 'reject') return `Tolak ${who}?`
    if (confirmAction.value === 'resend_tracking') {
        return `Kirim ulang email tracking ke ${props.application.personal_email}?`
    }
    return ''
})

const confirmConsequence = computed(() => {
    if (confirmAction.value === 'pass') return 'Applicant lanjut ke tahap interview.'
    if (confirmAction.value === 'reject') return 'Applicant tidak lanjut ke tahap berikutnya.'
    if (confirmAction.value === 'resend_tracking') {
        return 'Token tracking lama tidak berlaku lagi. Email konfirmasi pendaftaran akan dikirim dengan token baru.'
    }
    return ''
})

function passApplication(payload: Record<string, string | boolean> = {}) {
    router.post(
        routes.admin.recruitment.applications.screening.pass(props.application.id),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Applicant lolos screening.')
                emit('submitted')
            },
            onError: () => showErrorToast('Gagal meloloskan applicant.'),
        },
    )
}

/** Link grup WA periode; sekali tersimpan lokal tetap dianggap ada sesi ini. */
const groupLinkSavedLocal = ref(false)
const hasGroupLink = computed<boolean>(
    () =>
        groupLinkSavedLocal.value ||
        (props.whatsappGroupUrl !== null && props.whatsappGroupUrl !== ''),
)

const waDialogOpen = ref(false)
const waLinkInput = ref('')
const waSaving = ref(false)
const waLocalError = ref<string | null>(null)
const waIncludeGroup = ref<boolean>(true)

function openGroupLinkDialog(): void {
    waLinkInput.value = props.whatsappGroupUrl ?? ''
    waIncludeGroup.value = true
    waLocalError.value = null
    waDialogOpen.value = true
}

function closeGroupLinkDialog(): void {
    waDialogOpen.value = false
    waLocalError.value = null
}

/** Lolos tanpa menyertakan link grup di email (toggle OFF). */
function submitGroupLinkWithoutLink(): void {
    if (waSaving.value) return
    waLocalError.value = null
    waSaving.value = true
    router.post(
        routes.admin.recruitment.applications.screening.pass(props.application.id),
        { include_group_link: false },
        {
            preserveScroll: true,
            onSuccess: () => {
                waDialogOpen.value = false
                toast.success('Applicant lolos screening tanpa link grup.')
                emit('submitted')
            },
            onError: (errors: Record<string, string | string[]>) => {
                const first = errors['whatsapp_group_url'] ?? errors['application']
                waLocalError.value =
                    (Array.isArray(first) ? first[0] : first) ?? 'Gagal meloloskan applicant.'
            },
            onFinish: () => {
                waSaving.value = false
            },
        },
    )
}

function submitGroupLink(): void {
    if (!waIncludeGroup.value) {
        submitGroupLinkWithoutLink()
        return
    }
    const value = waLinkInput.value.trim()
    if (value === '') {
        waLocalError.value = 'Link grup WA wajib diisi bila toggle menyertakan link aktif.'
        return
    }
    if (!value.startsWith('https://')) {
        waLocalError.value = 'Link grup WA harus diawali https://.'
        return
    }
    if (waSaving.value) return
    waLocalError.value = null
    waSaving.value = true
    router.post(
        routes.admin.recruitment.applications.screening.pass(props.application.id),
        { whatsapp_group_url: value, include_group_link: true },
        {
            preserveScroll: true,
            onSuccess: () => {
                groupLinkSavedLocal.value = true
                waDialogOpen.value = false
                toast.success('Link grup tersimpan. Applicant lolos screening.')
                emit('submitted')
            },
            onError: (errors: Record<string, string | string[]>) => {
                const first =
                    errors['whatsapp_group_url'] ??
                    errors['include_group_link'] ??
                    errors['application']
                waLocalError.value =
                    (Array.isArray(first) ? first[0] : first) ??
                    'Gagal menyimpan link. Minta admin mengisinya di tab Settings.'
            },
            onFinish: () => {
                waSaving.value = false
            },
        },
    )
}

function verifyApplication() {
    router.post(
        routes.admin.recruitment.applications.verify(props.application.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Pendaftaran berhasil diverifikasi.')
                emit('submitted')
            },
            onError: () => showErrorToast('Gagal memverifikasi pendaftaran.'),
        },
    )
}

function resendTrackingApplication() {
    router.post(
        routes.admin.recruitment.applications.resendTracking(props.application.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Informasi tracking telah dikirim ulang ke applicant.')
                emit('submitted')
            },
            onError: () => showErrorToast('Gagal mengirim ulang informasi tracking.'),
        },
    )
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

function scoreBarWidth(score: number): string {
    return `${Math.min(100, Math.max(0, (score / 10) * 100))}%`
}

function evaluationAverageLabel(speaking: number, technical: number, attitude: number): string {
    return ((speaking + technical + attitude) / 3).toFixed(1).replace('.', ',')
}

const activityActionLabels: Record<string, string> = {
    'screening.pass': 'Lolos screening',
    'screening.revision_required': 'Diminta revisi',
    'screening.reject': 'Ditolak pada tahap screening',
    'evaluation.submitted': 'Evaluasi interview dikirim',
    'evaluation.updated': 'Evaluasi interview diperbarui',
    'evaluation.staff_override': 'Evaluasi diubah staff',
    'correction.requested': 'Applicant meminta koreksi',
    'correction.approved': 'Permintaan koreksi disetujui',
    'correction.rejected': 'Permintaan koreksi ditolak',
    'interview.scheduled': 'Interview dijadwalkan',
    'interview.rescheduled': 'Jadwal interview diubah',
    'interview.reassigned': 'Interviewer diganti',
    'interview.cancelled': 'Interview dibatalkan',
    'application.verified': 'Pendaftaran diverifikasi',
    'tracking.resend': 'Informasi tracking dikirim ulang',
    'application.updated': 'Pendaftaran diperbarui applicant',
    'final.accept': 'Diterima sebagai anggota',
    'final.reject': 'Tidak lolos seleksi akhir',
    'attendance.check_in': 'Absensi interview tercatat',
    'interview.no_show': 'Tidak hadir interview',
}

function activityActionLabel(action: string): string {
    const label = activityActionLabels[action]
    if (label) return label

    const pretty = action.replace(/[._-]+/g, ' ').trim()
    if (pretty === '') return action

    return pretty.charAt(0).toUpperCase() + pretty.slice(1)
}

const activityDateFormatter = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Jakarta',
})

function formatActivityTime(value: string | null): string {
    if (!value) return ''

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''

    return activityDateFormatter.format(date)
}

const instagramHandle = computed<string>(() =>
    props.application.instagram_username.replace(/^@+/, '').trim(),
)
const instagramUrl = computed<string>(() => `https://instagram.com/${instagramHandle.value}`)

const cvDownloadUrl = computed<string>(() =>
    routes.admin.recruitment.applications.document(props.application.id, 'cv'),
)
const cvPreviewUrl = computed<string>(() =>
    routes.admin.recruitment.applications.document(props.application.id, 'cv', true),
)
const portfolioDownloadUrl = computed<string>(() =>
    routes.admin.recruitment.applications.document(props.application.id, 'portfolio'),
)
const portfolioPreviewUrl = computed<string>(() =>
    routes.admin.recruitment.applications.document(props.application.id, 'portfolio', true),
)
const instagramFollowDownloadUrl = computed<string>(() =>
    routes.admin.recruitment.applications.document(props.application.id, 'instagram_follow'),
)
const instagramFollowPreviewUrl = computed<string>(() =>
    routes.admin.recruitment.applications.document(props.application.id, 'instagram_follow', true),
)

const cvPreviewLoading = ref<boolean>(true)
const cvPreviewFailed = ref<boolean>(false)
const portfolioPreviewLoading = ref<boolean>(true)
const portfolioPreviewFailed = ref<boolean>(false)
const instagramFollowPreviewFailed = ref<boolean>(false)

function resetDocumentPreview(): void {
    cvPreviewLoading.value = true
    cvPreviewFailed.value = false
    portfolioPreviewLoading.value = true
    portfolioPreviewFailed.value = false
    instagramFollowPreviewFailed.value = false
}

watch(
    () => props.application.id,
    () => resetDocumentPreview(),
)

const modalTitle = computed(() => {
    if (screeningAction.value === 'revision') return 'Minta revisi'
    if (screeningAction.value === 'reject') return 'Tolak applicant'
    return 'Keputusan screening'
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
    <div class="flex flex-col gap-5">
        <div v-if="!readonly && !hideActions" class="flex flex-wrap items-center justify-end gap-3">
            <Button v-if="canVerify" size="sm" variant="secondary" @click="requestConfirm('verify')">
                Verifikasi
            </Button>
            <Button v-if="canScreen && !hideRevisionAction" size="sm" variant="outline" @click="openScreeningModal('revision')">
                Revisi
            </Button>
            <Button v-if="canScreen" size="sm" variant="destructive" @click="openScreeningModal('reject')">
                <XCircle class="mr-2 size-4" />
                Tolak
            </Button>
            <Button v-if="canScreen" size="sm" @click="requestConfirm('pass')">
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
            <TabsList
                class="flex h-auto w-full items-center justify-start gap-6 overflow-x-auto overflow-y-hidden whitespace-nowrap rounded-none border-0 border-b border-border bg-transparent p-0 text-muted-foreground [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                <TabsTrigger
                    value="profile"
                    class="group -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    <User class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100" aria-hidden="true" />
                    <span>Profil</span>
                </TabsTrigger>
                <TabsTrigger
                    value="screening"
                    class="group -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    <ClipboardCheck class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100" aria-hidden="true" />
                    <span>Screening</span>
                </TabsTrigger>
                <TabsTrigger
                    value="final"
                    class="group -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    <Trophy class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100" aria-hidden="true" />
                    <span>Final</span>
                </TabsTrigger>
                <TabsTrigger
                    value="history"
                    class="group -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    <History class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100" aria-hidden="true" />
                    <span>Riwayat</span>
                </TabsTrigger>
                <TabsTrigger
                    value="emailing"
                    class="group -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    <Mail class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100" aria-hidden="true" />
                    <span>Emailing</span>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="profile" class="mt-4 space-y-5">
                <Card class="rounded-2xl border-border/70">
                    <CardContent class="space-y-5 p-6">
                        <div class="space-y-3">
                            <p class="text-muted-foreground text-sm font-semibold uppercase tracking-wide">
                                Detail pendaftaran
                            </p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div v-if="!readonly">
                                    <p class="text-muted-foreground text-xs uppercase">NIM</p>
                                    <p class="font-medium">{{ application.nim }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-xs uppercase">Semester</p>
                                    <p class="font-medium">{{ application.semester }}</p>
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
                            </div>
                        </div>
                        <div class="space-y-3 border-t border-border/60 pt-5">
                            <p class="text-muted-foreground text-sm font-semibold uppercase tracking-wide">
                                Kontak
                            </p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p class="text-muted-foreground text-xs uppercase">Telepon</p>
                                    <p class="font-medium">{{ application.phone }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-xs uppercase">Instagram</p>
                                    <a
                                        v-if="instagramHandle"
                                        :href="instagramUrl"
                                        target="_blank"
                                        rel="noopener"
                                        class="mt-0.5 inline-flex items-center gap-1.5 font-medium underline-offset-4 hover:underline"
                                    >
                                        <Instagram class="size-4 shrink-0" aria-hidden="true" />
                                        <span>@{{ instagramHandle }}</span>
                                    </a>
                                    <p v-else class="font-medium">—</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-xs uppercase">Email pribadi</p>
                                    <p class="font-medium">{{ application.personal_email }}</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-xs uppercase">Email kampus</p>
                                    <p class="font-medium">{{ application.student_email }}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border-border/70">
                    <CardContent class="space-y-4 p-6">
                        <p class="text-sm font-semibold">Dokumen</p>
                        <div v-if="application.document" class="divide-y divide-border">
                            <div class="space-y-3 pb-5">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <FileText class="text-muted-foreground size-5" />
                                        <div>
                                            <p class="font-medium">{{ application.document.cv_original_name }}</p>
                                            <p class="text-muted-foreground text-xs">
                                                CV · {{ formatBytes(application.document.cv_size_bytes) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        v-if="application.document.has_cv_file"
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Button as-child variant="outline" size="sm">
                                            <a :href="cvDownloadUrl">
                                                <Download class="mr-2 size-4" />
                                                Unduh CV
                                            </a>
                                        </Button>
                                        <Button
                                            v-if="!cvPreviewFailed"
                                            as-child
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <a :href="cvPreviewUrl" target="_blank" rel="noopener">
                                                <ExternalLink class="mr-2 size-4" />
                                                Buka di tab baru
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                                <div
                                    v-if="application.document.has_cv_file"
                                    class="relative overflow-hidden rounded-lg border bg-muted/30"
                                >
                                    <iframe
                                        v-show="!cvPreviewFailed"
                                        :src="cvPreviewUrl"
                                        title="Pratinjau CV"
                                        class="h-80 w-full bg-white"
                                        loading="lazy"
                                        @load="cvPreviewLoading = false"
                                        @error="cvPreviewFailed = true; cvPreviewLoading = false"
                                    />
                                    <div
                                        v-if="cvPreviewLoading && !cvPreviewFailed"
                                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-muted/30 p-6 text-center"
                                        aria-live="polite"
                                    >
                                        <div
                                            class="border-muted-foreground/30 border-t-foreground h-8 w-8 animate-spin rounded-full border-2"
                                            aria-hidden="true"
                                        />
                                        <p class="text-muted-foreground text-sm">Memuat pratinjau CV…</p>
                                    </div>
                                    <div
                                        v-if="cvPreviewFailed"
                                        class="flex flex-col items-center justify-center gap-3 p-6 text-center"
                                    >
                                        <p class="text-muted-foreground text-sm">
                                            Pratinjau tidak dapat dimuat. Gunakan tombol unduh untuk membuka
                                            berkas.
                                        </p>
                                        <Button as-child variant="outline" size="sm">
                                            <a :href="cvDownloadUrl">
                                                <Download class="mr-2 size-4" />
                                                Unduh CV
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 pt-5">
                                <p class="text-sm font-semibold">Portfolio</p>
                                <div
                                    v-if="application.document.portfolio_type === 'url' && application.document.portfolio_url"
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <a
                                        :href="application.document.portfolio_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary inline-flex min-w-0 max-w-full items-center gap-2 text-sm underline-offset-4 hover:underline"
                                    >
                                        <ExternalLink class="size-4 shrink-0" aria-hidden="true" />
                                        <span class="truncate">{{ application.document.portfolio_url }}</span>
                                    </a>
                                    <Button as-child variant="outline" size="sm">
                                        <a
                                            :href="application.document.portfolio_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <ExternalLink class="mr-2 size-4" />
                                            Buka tautan
                                        </a>
                                    </Button>
                                </div>
                                <div v-else-if="application.document.has_portfolio_file" class="space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <FileText class="text-muted-foreground size-5" />
                                            <div>
                                                <p class="text-sm font-medium">
                                                    {{ application.document.portfolio_original_name }}
                                                </p>
                                                <p
                                                    v-if="application.document.portfolio_size_bytes"
                                                    class="text-muted-foreground text-xs"
                                                >
                                                    Portfolio ·
                                                    {{ formatBytes(application.document.portfolio_size_bytes) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Button as-child variant="outline" size="sm">
                                                <a :href="portfolioDownloadUrl">
                                                    <Download class="mr-2 size-4" />
                                                    Unduh portfolio
                                                </a>
                                            </Button>
                                            <Button
                                                v-if="!portfolioPreviewFailed"
                                                as-child
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <a :href="portfolioPreviewUrl" target="_blank" rel="noopener">
                                                    <ExternalLink class="mr-2 size-4" />
                                                    Buka di tab baru
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="relative overflow-hidden rounded-lg border bg-muted/30">
                                        <iframe
                                            v-show="!portfolioPreviewFailed"
                                            :src="portfolioPreviewUrl"
                                            title="Pratinjau portfolio"
                                            class="h-80 w-full bg-white"
                                            loading="lazy"
                                            @load="portfolioPreviewLoading = false"
                                            @error="portfolioPreviewFailed = true; portfolioPreviewLoading = false"
                                        />
                                        <div
                                            v-if="portfolioPreviewLoading && !portfolioPreviewFailed"
                                            class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-muted/30 p-6 text-center"
                                            aria-live="polite"
                                        >
                                            <div
                                                class="border-muted-foreground/30 border-t-foreground h-8 w-8 animate-spin rounded-full border-2"
                                                aria-hidden="true"
                                            />
                                            <p class="text-muted-foreground text-sm">
                                                Memuat pratinjau portfolio…
                                            </p>
                                        </div>
                                        <div
                                            v-if="portfolioPreviewFailed"
                                            class="flex flex-col items-center justify-center gap-3 p-6 text-center"
                                        >
                                            <p class="text-muted-foreground text-sm">
                                                Pratinjau tidak dapat dimuat. Gunakan tombol unduh untuk membuka
                                                berkas.
                                            </p>
                                            <Button as-child variant="outline" size="sm">
                                                <a :href="portfolioDownloadUrl">
                                                    <Download class="mr-2 size-4" />
                                                    Unduh portfolio
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-muted-foreground mt-1 text-sm">Tidak ada portfolio (opsional).</p>
                            </div>

                            <div class="space-y-3 pt-5">
                                <p class="text-sm font-semibold">Bukti Follow Instagram</p>
                                <div
                                    v-if="application.document.has_instagram_follow_file"
                                    class="space-y-3"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium">
                                                {{
                                                    application.document.instagram_follow_original_name
                                                        ?? 'Bukti follow'
                                                }}
                                            </p>
                                            <p
                                                v-if="application.document.instagram_follow_size_bytes"
                                                class="text-muted-foreground text-xs"
                                            >
                                                Screenshot ·
                                                {{ formatBytes(application.document.instagram_follow_size_bytes) }}
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Button as-child variant="outline" size="sm">
                                                <a :href="instagramFollowDownloadUrl">
                                                    <Download class="mr-2 size-4" />
                                                    Unduh
                                                </a>
                                            </Button>
                                            <Button as-child variant="ghost" size="sm">
                                                <a
                                                    :href="instagramFollowPreviewUrl"
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    <ExternalLink class="mr-2 size-4" />
                                                    Buka di tab baru
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="overflow-hidden rounded-lg border bg-muted/30">
                                        <img
                                            v-show="!instagramFollowPreviewFailed"
                                            :src="instagramFollowPreviewUrl"
                                            alt="Bukti follow Instagram"
                                            class="max-h-80 w-full object-contain bg-white"
                                            loading="lazy"
                                            @error="instagramFollowPreviewFailed = true"
                                        />
                                        <div
                                            v-if="instagramFollowPreviewFailed"
                                            class="flex flex-col items-center justify-center gap-3 p-6 text-center"
                                        >
                                            <p class="text-muted-foreground text-sm">
                                                Pratinjau tidak dapat dimuat. Gunakan tombol unduh.
                                            </p>
                                            <Button as-child variant="outline" size="sm">
                                                <a :href="instagramFollowDownloadUrl">
                                                    <Download class="mr-2 size-4" />
                                                    Unduh
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-muted-foreground text-sm">Belum diunggah.</p>
                            </div>

                            <div class="space-y-3 pt-5">
                                <p class="text-sm font-semibold">Link Twibbon</p>
                                <div v-if="application.document.twibbon_url" class="flex flex-wrap items-center justify-between gap-3">
                                    <a
                                        :href="application.document.twibbon_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary inline-flex min-w-0 max-w-full items-center gap-2 text-sm underline-offset-4 hover:underline"
                                    >
                                        <ExternalLink class="size-4 shrink-0" aria-hidden="true" />
                                        <span class="truncate">{{ application.document.twibbon_url }}</span>
                                    </a>
                                    <Button as-child variant="outline" size="sm">
                                        <a
                                            :href="application.document.twibbon_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <ExternalLink class="mr-2 size-4" />
                                            Buka tautan
                                        </a>
                                    </Button>
                                </div>
                                <p v-else class="text-muted-foreground text-sm">Belum diisi.</p>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground text-sm">Dokumen belum tersedia.</p>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="screening" class="mt-4 space-y-5">
                <Card class="rounded-2xl border-border/70">
                    <CardContent class="space-y-3 p-6">
                        <p class="text-sm font-semibold">Riwayat screening</p>
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
                    <CardContent class="space-y-3 p-6">
                        <p class="text-sm font-semibold">Permintaan koreksi</p>
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
                                v-if="!readonly && canReviewCorrections && correction.status === 'pending'"
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

            <TabsContent value="final" class="mt-4 space-y-5">
                <Card v-if="application.evaluation" class="overflow-hidden rounded-2xl border-border/70">
                    <CardContent class="p-0">
                        <div class="flex flex-wrap items-start justify-between gap-2 px-6 pt-5">
                            <p class="text-muted-foreground text-xs font-semibold uppercase tracking-wider">
                                Evaluasi primary
                                <span v-if="primaryDivisionName !== '—'" class="normal-case tracking-normal">
                                    — {{ primaryDivisionName }}
                                </span>
                            </p>
                            <span
                                v-if="application.evaluation.is_locked"
                                class="inline-flex items-center gap-1 rounded-full border border-border/70 bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                            >
                                <Lock class="size-3" aria-hidden="true" />
                                Terkunci
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-900"
                            >
                                <PenLine class="size-3" aria-hidden="true" />
                                Draft
                            </span>
                        </div>

                        <div class="px-6 pt-3">
                            <p class="text-muted-foreground text-xs">Rekomendasi interviewer</p>
                            <p class="mt-0.5 text-xl font-semibold tracking-tight">
                                {{ application.evaluation.recommendation_label }}
                            </p>
                        </div>

                        <div class="px-6 pt-5">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="text-muted-foreground text-xs font-semibold uppercase tracking-wider">
                                    Penilaian
                                </p>
                                <p class="text-muted-foreground text-xs">
                                    Rata-rata
                                    <span class="text-foreground font-semibold tabular-nums">{{
                                        evaluationAverageLabel(
                                            application.evaluation.speaking_score,
                                            application.evaluation.technical_score,
                                            application.evaluation.attitude_score,
                                        )
                                    }}</span>
                                </p>
                            </div>
                            <dl class="mt-2 divide-y divide-border/60 border-y border-border/60">
                                <div class="flex items-center gap-3 py-2">
                                    <dt class="w-24 shrink-0 text-sm">Speaking</dt>
                                    <dd
                                        class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="h-full rounded-full bg-foreground/70"
                                            :style="{ width: scoreBarWidth(application.evaluation.speaking_score) }"
                                        />
                                    </dd>
                                    <dd class="w-14 shrink-0 text-right text-sm tabular-nums">
                                        <span class="font-semibold">{{ application.evaluation.speaking_score }}</span><span class="text-muted-foreground">/10</span>
                                    </dd>
                                </div>
                                <div class="flex items-center gap-3 py-2">
                                    <dt class="w-24 shrink-0 text-sm">Technical</dt>
                                    <dd
                                        class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="h-full rounded-full bg-foreground/70"
                                            :style="{ width: scoreBarWidth(application.evaluation.technical_score) }"
                                        />
                                    </dd>
                                    <dd class="w-14 shrink-0 text-right text-sm tabular-nums">
                                        <span class="font-semibold">{{ application.evaluation.technical_score }}</span><span class="text-muted-foreground">/10</span>
                                    </dd>
                                </div>
                                <div class="flex items-center gap-3 py-2">
                                    <dt class="w-24 shrink-0 text-sm">Attitude</dt>
                                    <dd
                                        class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="h-full rounded-full bg-foreground/70"
                                            :style="{ width: scoreBarWidth(application.evaluation.attitude_score) }"
                                        />
                                    </dd>
                                    <dd class="w-14 shrink-0 text-right text-sm tabular-nums">
                                        <span class="font-semibold">{{ application.evaluation.attitude_score }}</span><span class="text-muted-foreground">/10</span>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div v-if="application.evaluation.notes" class="px-6 pt-4">
                            <figure class="rounded-r-lg border-l-2 border-foreground/25 bg-muted/40 py-2.5 pl-4 pr-3">
                                <figcaption class="text-muted-foreground text-xs">
                                    Catatan interviewer
                                </figcaption>
                                <blockquote class="mt-1 text-sm leading-relaxed">
                                    {{ application.evaluation.notes }}
                                </blockquote>
                            </figure>
                        </div>

                        <div
                            class="px-6 pt-4"
                            :class="{ 'pb-6': !(showFinalDecision && primaryDivisionId) }"
                        >
                            <p class="text-muted-foreground flex items-center gap-1.5 text-xs">
                                <MicVocal class="size-3.5 shrink-0" aria-hidden="true" />
                                <span>Dinilai oleh {{ application.evaluation.evaluator?.name ?? 'Interviewer' }}</span>
                            </p>
                        </div>

                        <div
                            v-if="showFinalDecision && primaryDivisionId"
                            class="mt-4 border-t border-border/60 bg-muted/40 px-6 py-4"
                        >
                            <p class="text-muted-foreground text-xs">
                                Keputusan final — tempatkan applicant di {{ primaryDivisionName }}
                            </p>
                            <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    class="h-auto min-h-9 justify-start whitespace-normal py-2 text-left leading-snug"
                                    :aria-label="`Terima ${application.full_name} sebagai AA di ${primaryDivisionName}`"
                                    @click="openFinalAcceptForDivision(primaryDivisionId, primaryDivisionName, 'aa')"
                                >
                                    <GraduationCap class="size-4 shrink-0" aria-hidden="true" />
                                    Diterima sebagai AA — {{ primaryDivisionName }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="h-auto min-h-9 justify-start whitespace-normal py-2 text-left leading-snug"
                                    :aria-label="`Terima ${application.full_name} sebagai Member di ${primaryDivisionName}`"
                                    @click="openFinalAcceptForDivision(primaryDivisionId, primaryDivisionName, 'member')"
                                >
                                    <Users class="size-4 shrink-0" aria-hidden="true" />
                                    Diterima sebagai Member — {{ primaryDivisionName }}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    v-if="application.evaluations?.secondary"
                    class="overflow-hidden rounded-2xl border-border/70"
                >
                    <CardContent class="p-0">
                        <div class="flex flex-wrap items-start justify-between gap-2 px-6 pt-5">
                            <p class="text-muted-foreground text-xs font-semibold uppercase tracking-wider">
                                Evaluasi secondary
                                <span
                                    v-if="application.evaluations.secondary.division"
                                    class="normal-case tracking-normal"
                                >
                                    — {{ application.evaluations.secondary.division }}
                                </span>
                            </p>
                            <span
                                v-if="application.evaluations.secondary.is_locked"
                                class="inline-flex items-center gap-1 rounded-full border border-border/70 bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                            >
                                <Lock class="size-3" aria-hidden="true" />
                                Terkunci
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-900"
                            >
                                <PenLine class="size-3" aria-hidden="true" />
                                Draft
                            </span>
                        </div>

                        <div class="px-6 pt-3">
                            <p class="text-muted-foreground text-xs">Rekomendasi interviewer</p>
                            <p class="mt-0.5 text-xl font-semibold tracking-tight">
                                {{ application.evaluations.secondary.recommendation_label }}
                            </p>
                        </div>

                        <div class="px-6 pt-5">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="text-muted-foreground text-xs font-semibold uppercase tracking-wider">
                                    Penilaian
                                </p>
                                <p class="text-muted-foreground text-xs">
                                    Rata-rata
                                    <span class="text-foreground font-semibold tabular-nums">{{
                                        evaluationAverageLabel(
                                            application.evaluations.secondary.speaking_score,
                                            application.evaluations.secondary.technical_score,
                                            application.evaluations.secondary.attitude_score,
                                        )
                                    }}</span>
                                </p>
                            </div>
                            <dl class="mt-2 divide-y divide-border/60 border-y border-border/60">
                                <div class="flex items-center gap-3 py-2">
                                    <dt class="w-24 shrink-0 text-sm">Speaking</dt>
                                    <dd
                                        class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="h-full rounded-full bg-foreground/70"
                                            :style="{ width: scoreBarWidth(application.evaluations.secondary.speaking_score) }"
                                        />
                                    </dd>
                                    <dd class="w-14 shrink-0 text-right text-sm tabular-nums">
                                        <span class="font-semibold">{{ application.evaluations.secondary.speaking_score }}</span><span class="text-muted-foreground">/10</span>
                                    </dd>
                                </div>
                                <div class="flex items-center gap-3 py-2">
                                    <dt class="w-24 shrink-0 text-sm">Technical</dt>
                                    <dd
                                        class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="h-full rounded-full bg-foreground/70"
                                            :style="{ width: scoreBarWidth(application.evaluations.secondary.technical_score) }"
                                        />
                                    </dd>
                                    <dd class="w-14 shrink-0 text-right text-sm tabular-nums">
                                        <span class="font-semibold">{{ application.evaluations.secondary.technical_score }}</span><span class="text-muted-foreground">/10</span>
                                    </dd>
                                </div>
                                <div class="flex items-center gap-3 py-2">
                                    <dt class="w-24 shrink-0 text-sm">Attitude</dt>
                                    <dd
                                        class="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="h-full rounded-full bg-foreground/70"
                                            :style="{ width: scoreBarWidth(application.evaluations.secondary.attitude_score) }"
                                        />
                                    </dd>
                                    <dd class="w-14 shrink-0 text-right text-sm tabular-nums">
                                        <span class="font-semibold">{{ application.evaluations.secondary.attitude_score }}</span><span class="text-muted-foreground">/10</span>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div v-if="application.evaluations.secondary.notes" class="px-6 pt-4">
                            <figure class="rounded-r-lg border-l-2 border-foreground/25 bg-muted/40 py-2.5 pl-4 pr-3">
                                <figcaption class="text-muted-foreground text-xs">
                                    Catatan interviewer
                                </figcaption>
                                <blockquote class="mt-1 text-sm leading-relaxed">
                                    {{ application.evaluations.secondary.notes }}
                                </blockquote>
                            </figure>
                        </div>

                        <div
                            class="px-6 pt-4"
                            :class="{ 'pb-6': !(showFinalDecision && secondaryDivisionId) }"
                        >
                            <p class="text-muted-foreground flex items-center gap-1.5 text-xs">
                                <MicVocal class="size-3.5 shrink-0" aria-hidden="true" />
                                <span>Dinilai oleh {{ application.evaluations.secondary.evaluator?.name ?? 'Interviewer' }}</span>
                            </p>
                        </div>

                        <div
                            v-if="showFinalDecision && secondaryDivisionId"
                            class="mt-4 border-t border-border/60 bg-muted/40 px-6 py-4"
                        >
                            <p class="text-muted-foreground text-xs">
                                Keputusan final — tempatkan applicant di {{ secondaryDivisionName }}
                            </p>
                            <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    class="h-auto min-h-9 justify-start whitespace-normal py-2 text-left leading-snug"
                                    :aria-label="`Terima ${application.full_name} sebagai AA di ${secondaryDivisionName}`"
                                    @click="openFinalAcceptForDivision(secondaryDivisionId, secondaryDivisionName, 'aa')"
                                >
                                    <GraduationCap class="size-4 shrink-0" aria-hidden="true" />
                                    Diterima sebagai AA — {{ secondaryDivisionName }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="h-auto min-h-9 justify-start whitespace-normal py-2 text-left leading-snug"
                                    :aria-label="`Terima ${application.full_name} sebagai Member di ${secondaryDivisionName}`"
                                    @click="openFinalAcceptForDivision(secondaryDivisionId, secondaryDivisionName, 'member')"
                                >
                                    <Users class="size-4 shrink-0" aria-hidden="true" />
                                    Diterima sebagai Member — {{ secondaryDivisionName }}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <div
                    v-else-if="application.secondary_division && application.evaluations?.primary"
                    class="flex items-start gap-2.5 rounded-2xl border border-dashed border-border px-4 py-3.5"
                >
                    <VideoOff class="text-muted-foreground mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p class="text-muted-foreground text-xs leading-relaxed">
                        Belum diinterview secondary (opsional) — keputusan final memakai hasil primary.
                    </p>
                </div>

                <Card v-if="application.final_decision" class="rounded-2xl border-border/70">
                    <CardContent class="space-y-3 p-6">
                        <p class="text-sm font-semibold">Keputusan final</p>
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

                <p
                    v-if="!application.evaluation && !application.final_decision && !canDecideFinal"
                    class="text-muted-foreground text-sm"
                >
                    Belum ada data final review.
                </p>
            </TabsContent>

            <TabsContent value="history" class="mt-4">
                <Card class="rounded-2xl border-border/70">
                    <CardContent class="space-y-3 p-6">
                        <div
                            v-for="log in application.activity_logs"
                            :key="log.id"
                            class="flex gap-3 rounded-xl border p-4"
                        >
                            <History class="text-muted-foreground mt-0.5 size-4 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ activityActionLabel(log.action) }}</p>
                                <p class="text-muted-foreground text-xs">
                                    {{ log.actor?.name ?? 'Sistem' }} · {{ formatActivityTime(log.created_at) || 'Waktu tidak tercatat' }}
                                </p>
                            </div>
                        </div>
                        <p v-if="application.activity_logs.length === 0" class="text-muted-foreground text-sm">
                            Belum ada aktivitas tercatat.
                        </p>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="emailing" class="mt-4">
                <ApplicantEmailingSection
                    :application-id="application.id"
                    :applicant-name="application.full_name"
                    :status-map="application.email_resend_status ?? null"
                    :prereq="application.email_resend_prereq ?? null"
                    :can-resend="canResendTracking"
                    :whatsapp-group-url="whatsappGroupUrl"
                    @resent="emit('resent')"
                />
            </TabsContent>
        </Tabs>

        <Dialog v-if="!readonly" v-model:open="screeningModalOpen">
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
                        <SimpleSelect
                            id="reason"
                            v-model="screeningForm.reason"
                            :options="screeningReasonOptions"
                            placeholder="Pilih alasan"
                            :invalid="!!screeningForm.errors.reason"
                        />
                        <p v-if="screeningForm.errors.reason" class="text-destructive text-xs">
                            {{ screeningForm.errors.reason }}
                        </p>
                    </div>

                    <div v-if="screeningAction === 'revision'" class="space-y-2">
                        <Label>Bagian yang perlu diperbaiki</Label>
                        <div class="space-y-2">
                            <label
                                v-for="opt in revisionSectionOptions"
                                :key="opt.value"
                                class="flex cursor-pointer items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :model-value="isCheckboxOptionSelected(screeningForm.sections, opt.value)"
                                    @update:model-value="(v: boolean | 'indeterminate') => toggleRevisionSection(opt.value, v === true)"
                                />
                                {{ opt.label }}
                            </label>
                        </div>
                        <p v-if="screeningForm.errors.sections" class="text-destructive text-xs">
                            {{ screeningForm.errors.sections }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="notes">{{
                            screeningAction === 'revision' ? 'Catatan untuk applicant' : 'Catatan'
                        }}</Label>
                        <textarea
                            id="notes"
                            v-model="screeningForm.notes"
                            rows="3"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            :placeholder="
                                screeningAction === 'revision'
                                    ? 'Tulis catatan perbaikan untuk applicant...'
                                    : 'Catatan internal untuk tim...'
                            "
                        />
                        <p
                            v-if="screeningAction === 'revision'"
                            class="text-muted-foreground text-xs"
                        >
                            Catatan ini dikirim ke applicant lewat email.
                        </p>
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
                            :disabled="
                                screeningForm.processing ||
                                (screeningAction === 'revision' &&
                                    screeningForm.sections.length === 0)
                            "
                            :variant="screeningAction === 'reject' ? 'destructive' : 'default'"
                        >
                            Simpan keputusan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-if="!readonly" v-model:open="confirmOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ confirmTitle }}</DialogTitle>
                    <DialogDescription>
                        {{ confirmQuestion }}
                    </DialogDescription>
                </DialogHeader>

                <p v-if="confirmConsequence" class="text-sm text-muted-foreground">
                    {{ confirmConsequence }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="confirmOpen = false">
                        Batal
                    </Button>
                    <Button
                        type="button"
                        :variant="confirmAction === 'reject' ? 'destructive' : 'default'"
                        :disabled="confirmAction === 'reject' && screeningForm.processing"
                        @click="executeConfirmed"
                    >
                        Konfirmasi
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-if="!readonly" v-model:open="finalConfirmOpen">
            <DialogContent class="sm:max-w-md" @open-auto-focus="focusFinalCancel">
                <DialogHeader>
                    <DialogTitle>Konfirmasi keputusan final</DialogTitle>
                    <DialogDescription>
                        Periksa kembali sebelum dikirim — keputusan ini tidak bisa dibatalkan.
                    </DialogDescription>
                </DialogHeader>

                <div class="rounded-xl border border-border/70 bg-muted/40 px-4 py-3 text-sm">
                    <p class="font-semibold">{{ application.full_name }}</p>
                    <p class="mt-0.5 font-mono text-xs text-muted-foreground">
                        {{ application.registration_number }}
                    </p>
                    <p class="mt-2">
                        {{ finalChoiceActionLabel }}
                        <span v-if="!isFinalRejectChoice"> — {{ finalDivisionName }}</span>
                    </p>
                    <p v-if="!isFinalRejectChoice" class="text-muted-foreground mt-1 text-xs">
                        Divisi penempatan final mengikuti kartu evaluasi yang dipilih.
                    </p>
                </div>

                <p
                    v-if="!isFinalRejectChoice && finalConfirmForm.errors.final_division_id"
                    class="text-destructive text-xs"
                >
                    {{ finalConfirmForm.errors.final_division_id }}
                </p>

                <div v-if="isFinalRejectChoice" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="final_confirm_internal_reason">Alasan internal</Label>
                        <textarea
                            id="final_confirm_internal_reason"
                            v-model="finalConfirmForm.internal_reason"
                            rows="3"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Catatan internal untuk tim..."
                            required
                        />
                        <p
                            v-if="finalConfirmForm.errors.internal_reason"
                            class="text-destructive text-xs"
                        >
                            {{ finalConfirmForm.errors.internal_reason }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="final_confirm_public_message">Pesan untuk applicant</Label>
                        <textarea
                            id="final_confirm_public_message"
                            v-model="finalConfirmForm.public_message"
                            rows="3"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Pesan yang tampil di tracking portal..."
                            required
                        />
                        <p
                            v-if="finalConfirmForm.errors.public_message"
                            class="text-destructive text-xs"
                        >
                            {{ finalConfirmForm.errors.public_message }}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        ref="finalCancelRef"
                        type="button"
                        variant="outline"
                        @click="finalConfirmOpen = false"
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        :variant="isFinalRejectChoice ? 'destructive' : 'default'"
                        :disabled="finalConfirmForm.processing"
                        @click="submitFinalConfirm"
                    >
                        Ya, lanjutkan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-if="!readonly" v-model:open="waDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Link grup WA belum diisi</DialogTitle>
                    <DialogDescription>
                        Periode ini belum punya link grup. Isi sekarang agar email lolos menyertakan
                        link grup, atau matikan toggle bila periode ini memang tidak pakai grup.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex items-center justify-between gap-3 rounded-xl border p-3">
                    <div class="space-y-0.5">
                        <Label for="wa-include-group">Sertakan link grup di email</Label>
                        <p class="text-muted-foreground text-xs">
                            {{
                                waIncludeGroup
                                    ? 'Email lolos akan ada tombol Gabung Grup WA.'
                                    : 'Email lolos dikirim tanpa blok link grup.'
                            }}
                        </p>
                    </div>
                    <Switch id="wa-include-group" v-model="waIncludeGroup" />
                </div>

                <div class="space-y-2">
                    <Label for="drawer-wa-link">Link grup WA</Label>
                    <input
                        id="drawer-wa-link"
                        v-model="waLinkInput"
                        type="url"
                        inputmode="url"
                        placeholder="https://chat.whatsapp.com/..."
                        :disabled="!waIncludeGroup || waSaving"
                        class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <p v-if="waLocalError" class="text-destructive text-xs">
                        {{ waLocalError }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeGroupLinkDialog">
                        Batal
                    </Button>
                    <Button type="button" :disabled="waSaving" @click="submitGroupLink">
                        {{ waSaving ? 'Menyimpan…' : waIncludeGroup ? 'Simpan & loloskan' : 'Loloskan tanpa link grup' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </div>
</template>
