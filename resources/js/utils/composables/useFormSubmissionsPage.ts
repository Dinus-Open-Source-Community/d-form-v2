import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import {
    answerPreview,
    formatSubmissionDate,
    formSubmissionReviewIsPending,
    humanizeSubmissionKey,
    submissionFileUrl,
} from '@/lib/formSubmissionsUi'
import FormAnswerReviewController from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/FormAnswerReviewController'

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

function readXsrfToken(): string | null {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)
    return m?.[1] ? decodeURIComponent(m[1]) : null
}

export function useFormSubmissionsPage(props: {
    event: { id: string; title: string }
    form: { id: string; title: string }
    fields: IFormField[]
    submissions: SubmissionPaginator
}) {
    const selectedSubmission = ref<IFormSubmission | null>(null)
    const isDetailOpen = ref(false)
    const reviewingIds = ref<Set<string>>(new Set())

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

    const pendingReviewCount = computed(
        () => props.submissions.data.filter((s) => formSubmissionReviewIsPending(s)).length,
    )

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

    function isSubmissionReviewing(submissionId: string): boolean {
        return reviewingIds.value.has(submissionId)
    }

    function submitSubmissionReview(action: 'accept' | 'reject', submission: IFormSubmission) {
        const review_status = action === 'accept' ? 'accepted' : 'rejected'
        const id = submission.id
        const nextReviewing = new Set(reviewingIds.value)
        nextReviewing.add(id)
        reviewingIds.value = nextReviewing

        const { url, method } = FormAnswerReviewController.patch({
            event: props.event.id,
            form: props.form.id,
            formAnswer: submission.id,
        })

        const clearReviewing = () => {
            const s = new Set(reviewingIds.value)
            s.delete(id)
            reviewingIds.value = s
        }

        void (async () => {
            try {
                const token = readXsrfToken()
                const res = await fetch(url, {
                    method: method.toUpperCase(),
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ review_status }),
                })

                const body = (await res.json().catch(() => ({}))) as { message?: string }

                if (!res.ok) {
                    if (res.status === 409) {
                        toast.error(body.message ?? 'Submission ini sudah pernah direview.')
                    } else if (res.status === 422) {
                        toast.error(body.message ?? 'Status review tidak valid.')
                    } else if (res.status === 403) {
                        toast.error('Anda tidak punya izin untuk mereview submission ini.')
                    } else if (res.status === 404) {
                        toast.error('Submission tidak ditemukan.')
                    } else if (res.status === 429) {
                        toast.error(
                            'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
                        )
                    } else {
                        toast.error(body.message ?? 'Gagal memperbarui status review.')
                    }
                    if (res.status === 409 || res.status === 422) {
                        router.reload({ only: ['submissions'] })
                    }
                    return
                }

                toast.success(action === 'accept' ? 'Submission diterima.' : 'Submission ditolak.')

                router.reload({
                    only: ['submissions'],
                    onSuccess: () => {
                        if (selectedSubmission.value?.id === id) {
                            const next = props.submissions.data.find((s) => s.id === id)
                            if (next) selectedSubmission.value = next
                        }
                    },
                })
            } catch {
                toast.error('Tidak dapat menghubungi server. Coba lagi.')
            } finally {
                clearReviewing()
            }
        })()
    }

    return {
        selectedSubmission,
        isDetailOpen,
        fieldLabelMap,
        answerKeys,
        allAnswerKeys,
        latestSubmissionDate,
        pendingReviewCount,
        formatDate: formatSubmissionDate,
        humanizeKey,
        answerPreview,
        fileUrl,
        openDetail,
        submitSubmissionReview,
        isSubmissionReviewing,
    }
}
