import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { toast } from 'vue-sonner'
import { Clock, ShieldCheck, ShieldX, Users } from 'lucide-vue-next'
import { REGISTRANTS_TONE_STYLES } from '@/lib/registrantsUi'
import FormAnswerReviewController from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/FormAnswerReviewController'

export interface RegistrantsStatCardModel {
    key: 'all' | 'pending' | 'accepted' | 'rejected'
    label: string
    helper: string
    value: number
    icon: typeof Users
    tone: 'primary' | 'warning' | 'success' | 'destructive'
}

interface ReviewDecisionJson {
    id: string
    review_status: string
    reviewed_at: string | null
    reviewed_by: string | null
    registration_code?: string | null
}

export function useEventRegistrantsPage(props: {
    event: IEvent
    registrants: IRegistrant[]
    registrationForm: { id: string; title: string } | null
}) {
    const searchQuery = ref('')
    const activeStatusTab = ref<'all' | 'pending' | 'accepted' | 'rejected'>('all')
    const viewType = ref<'table' | 'form'>('table')
    const registrants = ref<IRegistrant[]>([...props.registrants])
    const reviewBusy = ref(false)

    watch(
        () => props.registrants,
        (v) => {
            registrants.value = [...v]
        },
        { deep: true },
    )

    const selectedRegistrant = ref<IRegistrant | null>(null)
    const showDetailSheet = ref(false)
    const showApproveModal = ref(false)
    const showRejectModal = ref(false)
    const actionTarget = ref<IRegistrant | null>(null)

    const filteredRegistrants = computed(() => {
        let list = registrants.value

        if (activeStatusTab.value !== 'all') {
            list = list.filter((r) => r.status === activeStatusTab.value)
        }

        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase()
            list = list.filter(
                (r) => r.user.name.toLowerCase().includes(q) || r.user.email.toLowerCase().includes(q),
            )
        }

        return list
    })

    const statusCounts = computed(() => ({
        all: registrants.value.length,
        pending: registrants.value.filter((r) => r.status === 'pending').length,
        accepted: registrants.value.filter((r) => r.status === 'accepted').length,
        rejected: registrants.value.filter((r) => r.status === 'rejected').length,
    }))

    const approvalRate = computed(() => {
        const decided = statusCounts.value.accepted + statusCounts.value.rejected
        if (!decided) return 0
        return Math.round((statusCounts.value.accepted / decided) * 100)
    })

    const statCards = computed<RegistrantsStatCardModel[]>(() => [
        {
            key: 'all',
            label: 'Total registrants',
            helper: statusCounts.value.all === 1 ? 'submission received' : 'submissions received',
            value: statusCounts.value.all,
            icon: Users,
            tone: 'primary',
        },
        {
            key: 'pending',
            label: 'Awaiting review',
            helper: statusCounts.value.pending === 1 ? 'person needs a decision' : 'people need a decision',
            value: statusCounts.value.pending,
            icon: Clock,
            tone: 'warning',
        },
        {
            key: 'accepted',
            label: 'Approved',
            helper: `${approvalRate.value}% approval rate`,
            value: statusCounts.value.accepted,
            icon: ShieldCheck,
            tone: 'success',
        },
        {
            key: 'rejected',
            label: 'Rejected',
            helper: statusCounts.value.rejected === 1 ? 'decision recorded' : 'decisions recorded',
            value: statusCounts.value.rejected,
            icon: ShieldX,
            tone: 'destructive',
        },
    ])

    const pendingCount = computed(() => statusCounts.value.pending)

    function mergeReviewIntoRegistrant(regId: string, data: ReviewDecisionJson): void {
        const idx = registrants.value.findIndex((r) => r.id === regId)
        if (idx === -1) return

        const row = registrants.value[idx]
        registrants.value[idx] = {
            ...row,
            status: data.review_status as IRegistrant['status'],
            registration_code: data.registration_code ?? row.registration_code,
            reviewed_at: data.reviewed_at ?? row.reviewed_at,
        }

        if (selectedRegistrant.value?.id === regId) {
            selectedRegistrant.value = registrants.value[idx]
        }
    }

    function openDetail(reg: IRegistrant): void {
        selectedRegistrant.value = reg
        showDetailSheet.value = true
    }

    function startApprove(reg: IRegistrant): void {
        if (!props.registrationForm) {
            toast.error('No registration form is configured for this event.')
            return
        }
        actionTarget.value = reg
        showApproveModal.value = true
    }

    function startReject(reg: IRegistrant): void {
        if (!props.registrationForm) {
            toast.error('No registration form is configured for this event.')
            return
        }
        actionTarget.value = reg
        showRejectModal.value = true
    }

    async function confirmApprove(): Promise<void> {
        const target = actionTarget.value
        const form = props.registrationForm

        if (!target || !form || reviewBusy.value) {
            showApproveModal.value = false
            return
        }

        reviewBusy.value = true

        try {
            const { data } = await axios.patch<ReviewDecisionJson>(
                FormAnswerReviewController.url({
                    event: props.event.id,
                    form: form.id,
                    formAnswer: target.id,
                }),
                { review_status: 'accepted' },
            )

            mergeReviewIntoRegistrant(target.id, data)
            toast.success(`${target.user.name} has been approved — acceptance email queued.`)
        } catch (err: unknown) {
            if (axios.isAxiosError(err)) {
                const msg =
                    (err.response?.data as { message?: string } | undefined)?.message ??
                    err.message ??
                    'Could not approve this registrant.'
                toast.error(msg)
            } else {
                toast.error('Could not approve this registrant.')
            }
        } finally {
            reviewBusy.value = false
            showApproveModal.value = false
        }
    }

    async function confirmReject(): Promise<void> {
        const target = actionTarget.value
        const form = props.registrationForm

        if (!target || !form || reviewBusy.value) {
            showRejectModal.value = false
            return
        }

        reviewBusy.value = true

        try {
            const { data } = await axios.patch<ReviewDecisionJson>(
                FormAnswerReviewController.url({
                    event: props.event.id,
                    form: form.id,
                    formAnswer: target.id,
                }),
                { review_status: 'rejected' },
            )

            mergeReviewIntoRegistrant(target.id, data)
            toast.success(`${target.user.name} has been declined — notification queued.`)
        } catch (err: unknown) {
            if (axios.isAxiosError(err)) {
                const msg =
                    (err.response?.data as { message?: string } | undefined)?.message ??
                    err.message ??
                    'Could not reject this registrant.'
                toast.error(msg)
            } else {
                toast.error('Could not reject this registrant.')
            }
        } finally {
            reviewBusy.value = false
            showRejectModal.value = false
        }
    }

    function onExportClick(): void {
        toast.info('Exporting as CSV…')
    }

    function setStatTab(key: 'all' | 'pending' | 'accepted' | 'rejected'): void {
        activeStatusTab.value = key
    }

    return {
        searchQuery,
        activeStatusTab,
        viewType,
        registrants,
        selectedRegistrant,
        showDetailSheet,
        showApproveModal,
        showRejectModal,
        actionTarget,
        filteredRegistrants,
        statusCounts,
        statCards,
        pendingCount,
        toneStyles: REGISTRANTS_TONE_STYLES,
        openDetail,
        startApprove,
        startReject,
        confirmApprove,
        confirmReject,
        onExportClick,
        setStatTab,
    }
}
