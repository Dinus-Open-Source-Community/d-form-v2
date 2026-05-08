import { computed, ref, watch } from 'vue'
import { Clock, ShieldCheck, ShieldX, Users } from 'lucide-vue-next'
import { REGISTRANTS_TONE_STYLES } from '@/lib/registrantsUi'

export interface RegistrantsStatCardModel {
    key: 'all' | 'pending' | 'accepted' | 'rejected'
    label: string
    helper: string
    value: number
    icon: typeof Users
    tone: 'primary' | 'warning' | 'success' | 'destructive'
}

export function useEventRegistrantsPage(props: {
    event: IEvent
    registrants: IRegistrant[]
    registrationForm: { id: string; title: string } | null
}) {
    const searchQuery = ref('')
    const activeStatusTab = ref<'all' | 'pending' | 'accepted' | 'rejected'>('all')
    const registrants = ref<IRegistrant[]>([...props.registrants])

    watch(
        () => props.registrants,
        (v) => {
            registrants.value = [...v]
        },
        { deep: true },
    )

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
            label: 'Total pendaftar',
            helper:
                statusCounts.value.all === 1
                    ? '1 pengiriman formulir'
                    : `${statusCounts.value.all} pengiriman formulir`,
            value: statusCounts.value.all,
            icon: Users,
            tone: 'primary',
        },
        {
            key: 'pending',
            label: 'Menunggu review',
            helper:
                statusCounts.value.pending === 1
                    ? '1 orang belum diputuskan'
                    : `${statusCounts.value.pending} orang belum diputuskan`,
            value: statusCounts.value.pending,
            icon: Clock,
            tone: 'warning',
        },
        {
            key: 'accepted',
            label: 'Disetujui',
            helper: `${approvalRate.value}% dari yang sudah diputus`,
            value: statusCounts.value.accepted,
            icon: ShieldCheck,
            tone: 'success',
        },
        {
            key: 'rejected',
            label: 'Ditolak',
            helper:
                statusCounts.value.rejected === 1
                    ? '1 keputusan ditolak'
                    : `${statusCounts.value.rejected} keputusan ditolak`,
            value: statusCounts.value.rejected,
            icon: ShieldX,
            tone: 'destructive',
        },
    ])

    const pendingCount = computed(() => statusCounts.value.pending)

    function setStatTab(key: 'all' | 'pending' | 'accepted' | 'rejected'): void {
        activeStatusTab.value = key
    }

    return {
        searchQuery,
        activeStatusTab,
        registrants,
        filteredRegistrants,
        statusCounts,
        statCards,
        pendingCount,
        toneStyles: REGISTRANTS_TONE_STYLES,
        setStatTab,
    }
}
