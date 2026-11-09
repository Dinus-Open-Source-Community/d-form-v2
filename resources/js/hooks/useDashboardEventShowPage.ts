import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { handleInertiaFormErrors, humanizeErrorMessage } from '@/lib/error-message'
import {
    destroy as destroyEvent,
    restore as restoreEvent,
} from '@/actions/App/Http/Controllers/Dashboard/Events/EventController'
import { sessionLabelMap } from '@/lib/dummyData'
import { parseEventCategories } from '@/lib/eventShowUi'
import { formatDateTime } from '@/lib/dateTime'

export function useDashboardEventShowPage(
    event: IEvent,
    forms: { id: string; title: string }[],
) {
    const showDeleteModal = ref(false)
    const showRestoreModal = ref(false)
    const isDeleting = ref(false)
    const isRestoring = ref(false)

    const fillPercent = computed(() => {
        if (!event.quota) return 0
        return Math.min(100, Math.round((event.registered_count / event.quota) * 100))
    })

    const remainingSeats = computed(() => Math.max(0, event.quota - event.registered_count))

    const progressTone = computed(() => {
        const p = fillPercent.value
        if (p >= 90) return { ring: 'text-destructive', label: 'Almost full', pill: 'bg-destructive/10 text-destructive' }
        if (p >= 60) return { ring: 'text-warning', label: 'Filling fast', pill: 'bg-warning/15 text-warning-foreground' }
        return { ring: 'text-success', label: 'Seats available', pill: 'bg-success/10 text-success' }
    })

    const statusPill = computed(() => {
        if (event.deleted_at) return { label: 'Archived', classes: 'bg-muted text-muted-foreground border-border' }
        if (event.status === 'published') return { label: 'Published', classes: 'bg-success/10 text-success border-success/20' }
        return { label: 'Draft', classes: 'bg-muted text-muted-foreground border-border' }
    })

    const dateRange = computed(() =>
        formatDateTime({ value: event.start_date, variant: 'date-range', end: event.end_date }),
    )

    const sessionLabel = computed(
        () => parseEventCategories(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') || '—',
    )

    const priceLabel = computed(() =>
        event.price > 0 ? `Rp ${Number(event.price).toLocaleString('id-ID')}` : 'Free',
    )

    function handleDelete() {
        isDeleting.value = true
        router.delete(destroyEvent(event.id).url, {
            onSuccess: () => toast.success(humanizeErrorMessage('Event has been archived.')),
            onError: (errors) => handleInertiaFormErrors(errors, { title: 'Gagal mengarsipkan event' }),
            onFinish: () => { isDeleting.value = false; showDeleteModal.value = false },
        })
    }

    function handleRestore() {
        isRestoring.value = true
        router.post(restoreEvent(event.id).url, {}, {
            onSuccess: () => toast.success(humanizeErrorMessage('Event has been restored.')),
            onError: (errors) => handleInertiaFormErrors(errors, { title: 'Gagal memulihkan event' }),
            onFinish: () => { isRestoring.value = false; showRestoreModal.value = false },
        })
    }

    const cardShadow = 'shadow-sm'

    return {
        forms,
        showDeleteModal,
        showRestoreModal,
        isDeleting,
        isRestoring,
        fillPercent,
        remainingSeats,
        progressTone,
        statusPill,
        dateRange,
        sessionLabel,
        priceLabel,
        handleDelete,
        handleRestore,
        cardShadow,
    }
}
