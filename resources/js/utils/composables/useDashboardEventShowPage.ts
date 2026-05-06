import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import {
    destroy as destroyEvent,
    restore as restoreEvent,
} from '@/actions/App/Http/Controllers/Dashboard/Events/EventController'
import {
    formatDate,
    formatDateTime,
    sessionLabelMap,
} from '@/lib/dummyData'
import { parseEventCategories } from '@/lib/eventShowUi'
import { Banknote, CalendarDays, Clock, MapPin } from 'lucide-vue-next'

export function useDashboardEventShowPage(
    event: IEvent,
    forms: { id: string; title: string }[],
) {
    const previewRegistrants = [] as IRegistrant[]
    const totalRegistrants = event.registered_count

    const showDeleteModal = ref(false)
    const showRestoreModal = ref(false)
    const showImportModal = ref(false)
    const importFile = ref<File | null>(null)
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

    const metaBlocks = computed(() => [
        {
            title: 'Schedule',
            value: event.start_date === event.end_date ? formatDate(event.start_date) : `${formatDate(event.start_date)} — ${formatDate(event.end_date)}`,
            icon: CalendarDays,
        },
        { title: 'Location', value: event.location, icon: MapPin },
        {
            title: 'Session',
            value: parseEventCategories(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') || '—',
            icon: Clock,
        },
        {
            title: 'Price',
            value: event.price > 0 ? `Rp ${Number(event.price).toLocaleString('id-ID')}` : 'Free',
            icon: Banknote,
        },
    ])

    function handleDelete() {
        isDeleting.value = true
        router.delete(destroyEvent(event.id).url, {
            onSuccess: () => toast.success('Event has been archived.'),
            onError: () => toast.error('Failed to archive event.'),
            onFinish: () => { isDeleting.value = false; showDeleteModal.value = false },
        })
    }

    function handleRestore() {
        isRestoring.value = true
        router.post(restoreEvent(event.id).url, {}, {
            onSuccess: () => toast.success('Event has been restored.'),
            onError: () => toast.error('Failed to restore event.'),
            onFinish: () => { isRestoring.value = false; showRestoreModal.value = false },
        })
    }

    function handleExport(format: string) {
        toast.info(`Exporting as ${format}...`)
    }

    function handleImportFileChange(e: Event) {
        const input = e.target as HTMLInputElement
        if (input.files?.[0]) importFile.value = input.files[0]
    }

    function handleImport() {
        if (!importFile.value) { toast.error('Please select a file.'); return }
        toast.success(`Importing ${importFile.value.name}...`)
        importFile.value = null
        showImportModal.value = false
    }

    const cardShadow = 'shadow-sm'

    return {
        forms,
        previewRegistrants,
        totalRegistrants,
        showDeleteModal,
        showRestoreModal,
        showImportModal,
        importFile,
        isDeleting,
        isRestoring,
        fillPercent,
        remainingSeats,
        progressTone,
        statusPill,
        metaBlocks,
        parseEventCategories,
        formatDate,
        formatDateTime,
        handleDelete,
        handleRestore,
        handleExport,
        handleImportFileChange,
        handleImport,
        cardShadow,
    }
}
