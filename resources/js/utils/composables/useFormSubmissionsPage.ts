import { computed, ref } from 'vue'
import { toast } from 'vue-sonner'
import {
    answerPreview,
    formatSubmissionDate,
    humanizeSubmissionKey,
    submissionFileUrl,
} from '@/lib/formSubmissionsUi'

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface SubmissionPaginator {
    data: IFormSubmission[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    links?: PaginationLink[]
}

export function useFormSubmissionsPage(props: {
    event: { id: string; title: string }
    form: { id: string; title: string }
    fields: IFormField[]
    submissions: SubmissionPaginator
}) {
    const viewType = ref<'table' | 'form'>('table')
    const selectedSubmission = ref<IFormSubmission | null>(null)
    const isDetailOpen = ref(false)

    const fieldLabelMap = computed(() => {
        const map: Record<string, string> = {}
        props.fields.forEach((field) => {
            map[field.name] = field.label
        })
        return map
    })

    const answerKeys = computed(() => {
        const keysFromFields = props.fields.map((f) => f.name)
        const keysInSubmissions = new Set<string>()
        for (const submission of props.submissions.data) {
            Object.keys(submission.answers).forEach((key) => keysInSubmissions.add(key))
        }
        const allKeys = [...new Set([...keysFromFields, ...keysInSubmissions])]
        return allKeys.slice(0, 4)
    })

    const allAnswerKeys = computed(() => {
        const keysFromFields = props.fields.map((f) => f.name)
        const keysInSubmissions = new Set<string>()
        for (const submission of props.submissions.data) {
            Object.keys(submission.answers).forEach((key) => keysInSubmissions.add(key))
        }
        return [...new Set([...keysFromFields, ...keysInSubmissions])]
    })

    const latestSubmissionDate = computed(() => {
        if (props.submissions.data.length === 0) return '-'
        const dates = props.submissions.data.map((s) => new Date(s.submitted_at).getTime())
        return formatSubmissionDate(new Date(Math.max(...dates)).toISOString())
    })

    function humanizeKey(value: string): string {
        return humanizeSubmissionKey(fieldLabelMap.value, value)
    }

    function fileUrl(value: unknown): string | null {
        return submissionFileUrl(value)
    }

    function openDetail(submission: IFormSubmission) {
        selectedSubmission.value = submission
        isDetailOpen.value = true
    }

    function submissionReviewStub(action: 'accept' | 'reject', submission: IFormSubmission) {
        const title = action === 'accept' ? 'Terima pendaftar' : 'Tolak pendaftar'
        toast.info(title, {
            description: `Aksi untuk “${submission.user?.name ?? submission.user?.email ?? submission.id}” belum tersedia. Model form_answers belum memiliki status review. Lihat docs/milestone-m1-m4-remaining.md.`,
            duration: 8000,
        })
    }

    return {
        viewType,
        selectedSubmission,
        isDetailOpen,
        fieldLabelMap,
        answerKeys,
        allAnswerKeys,
        latestSubmissionDate,
        formatDate: formatSubmissionDate,
        humanizeKey,
        answerPreview,
        fileUrl,
        openDetail,
        submissionReviewStub,
    }
}
