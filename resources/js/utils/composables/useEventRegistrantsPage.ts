import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { Clock, ShieldCheck, ShieldX, Users } from 'lucide-vue-next'
import { REGISTRANTS_TONE_STYLES } from '@/lib/registrantsUi'

export interface RegistrantsStatCardModel {
    key: 'all' | 'pending' | 'approved' | 'rejected'
    label: string
    helper: string
    value: number
    icon: typeof Users
    tone: 'primary' | 'warning' | 'success' | 'destructive'
}

export function useEventRegistrantsPage(props: { registrants: IRegistrant[] }) {
    const searchQuery = ref('')
    const activeStatusTab = ref<'all' | 'pending' | 'approved' | 'rejected'>('all')
    const viewType = ref<'table' | 'form'>('table')
    const registrants = ref<IRegistrant[]>([...props.registrants])

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
        approved: registrants.value.filter((r) => r.status === 'approved').length,
        rejected: registrants.value.filter((r) => r.status === 'rejected').length,
    }))

    const approvalRate = computed(() => {
        const decided = statusCounts.value.approved + statusCounts.value.rejected
        if (!decided) return 0
        return Math.round((statusCounts.value.approved / decided) * 100)
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
            key: 'approved',
            label: 'Approved',
            helper: `${approvalRate.value}% approval rate`,
            value: statusCounts.value.approved,
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

    function openDetail(reg: IRegistrant): void {
        selectedRegistrant.value = reg
        showDetailSheet.value = true
    }

    function startApprove(reg: IRegistrant): void {
        actionTarget.value = reg
        showApproveModal.value = true
    }

    function startReject(reg: IRegistrant): void {
        actionTarget.value = reg
        showRejectModal.value = true
    }

    function confirmApprove(): void {
        if (actionTarget.value) {
            actionTarget.value.status = 'approved'
            toast.success(`${actionTarget.value.user.name} is in — approval email queued.`)
        }
        showApproveModal.value = false
    }

    function confirmReject(): void {
        if (actionTarget.value) {
            actionTarget.value.status = 'rejected'
            toast.success(`${actionTarget.value.user.name} has been declined — notification queued.`)
        }
        showRejectModal.value = false
    }

    function onExportClick(): void {
        toast.info('Exporting as CSV…')
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
    }
}
