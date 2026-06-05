import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import {
    answerPreview,
    formatSubmissionDate,
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
    data?: IFormSubmission[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    links?: PaginationLink[]
}

interface BundleGroupPaginator {
    data?: IBundleSubmissionGroup[]
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

function submissionRows(paginator: SubmissionPaginator | undefined): IFormSubmission[] {
    return paginator?.data ?? []
}

function bundleGroupRows(paginator: BundleGroupPaginator | undefined): IBundleSubmissionGroup[] {
    return paginator?.data ?? []
}

export function useFormSubmissionsPage(props: {
    event: { id: string; title: string }
    form: { id: string; title: string; registration_mode?: 'single' | 'bundle' | 'team' }
    fields?: IFormField[]
    submissions?: SubmissionPaginator
    bundleGroups?: BundleGroupPaginator
}) {
    const formFields = computed(() => props.fields ?? [])
    const selectedSubmission = ref<IFormSubmission | null>(null)
    const isDetailOpen = ref(false)
    const selectedGroup = ref<IBundleSubmissionGroup | null>(null)
    const isGroupDetailOpen = ref(false)
    const reviewingIds = ref<Set<string>>(new Set())

    const activeBundleGroup = computed(() => {
        const rows = bundleGroupRows(props.bundleGroups)
        if (rows.length === 0) {
            return null
        }

        if (!selectedGroup.value) {
            return rows[0]
        }

        return rows.find((g) => g.group_token === selectedGroup.value?.group_token) ?? rows[0]
    })

    const fieldLabelMap = computed(() => {
        const map: Record<string, string> = {}
        formFields.value.forEach((field) => {
            map[field.name] = field.label
        })
        return map
    })

    const answerKeys = computed(() => {
        const keysFromFields = formFields.value.map((f) => f.name)
        const keysInSubmissions = new Set<string>()
        
        for (const submission of submissionRows(props.submissions)) {
            Object.keys(submission.answers ?? {}).forEach((key) => keysInSubmissions.add(key))
        }

        for (const group of bundleGroupRows(props.bundleGroups)) {
            Object.keys(group.leader?.answers ?? {}).forEach((key) => keysInSubmissions.add(key))
            for (const member of group.members ?? []) {
                Object.keys(member.answers ?? {}).forEach((key) => keysInSubmissions.add(key))
            }
        }
        
        const allKeys = [...new Set([...keysFromFields, ...keysInSubmissions])]
        return allKeys.slice(0, 4)
    })

    const allAnswerKeys = computed(() => {
        const keysFromFields = formFields.value.map((f) => f.name)
        const keysInSubmissions = new Set<string>()
        
        for (const submission of submissionRows(props.submissions)) {
            Object.keys(submission.answers ?? {}).forEach((key) => keysInSubmissions.add(key))
        }

        for (const group of bundleGroupRows(props.bundleGroups)) {
            Object.keys(group.leader?.answers ?? {}).forEach((key) => keysInSubmissions.add(key))
            for (const member of group.members ?? []) {
                Object.keys(member.answers ?? {}).forEach((key) => keysInSubmissions.add(key))
            }
        }
        
        return [...new Set([...keysFromFields, ...keysInSubmissions])]
    })

    function humanizeKey(value: string): string {
        return humanizeSubmissionKey(fieldLabelMap.value, value)
    }

    function fileUrl(value: unknown): string | null {
        return submissionFileUrl(value)
    }

    function openDetail(submission: IFormSubmission | IBundleSubmissionMember) {
        if ('can_open_detail' in submission && !submission.can_open_detail) {
            return
        }

        isGroupDetailOpen.value = false
        selectedSubmission.value = submission
        isDetailOpen.value = true
    }

    function openGroupDetail(group: IBundleSubmissionGroup) {
        isDetailOpen.value = false
        selectedSubmission.value = null
        selectedGroup.value = group
        isGroupDetailOpen.value = true
    }

    function closeGroupDetail() {
        isGroupDetailOpen.value = false
    }

    function isSubmissionReviewing(submissionId: string): boolean {
        return reviewingIds.value.has(submissionId)
    }

    function submitSubmissionReview(action: 'accept' | 'reject', submission: IFormSubmission | IBundleSubmissionMember) {
        // Check can_review for bundle members
        if ('can_review' in submission && !submission.can_review) {
            return
        }
        
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
                        router.reload({
                            only: props.form.registration_mode === 'bundle' ? ['bundleGroups'] : ['submissions'],
                        })
                    }
                    return
                }

                toast.success(action === 'accept' ? 'Submission diterima.' : 'Submission ditolak.')

                const reloadKeys = props.form.registration_mode === 'bundle' 
                    ? ['bundleGroups'] 
                    : ['submissions']

                router.reload({
                    only: reloadKeys,
                    onSuccess: () => {
                        if (selectedSubmission.value?.id === id) {
                            // Try to find updated submission in either submissions or bundleGroups
                            let next: IFormSubmission | IBundleSubmissionMember | null = null
                            
                            next = submissionRows(props.submissions).find((s) => s.id === id) ?? null

                            if (!next) {
                                for (const group of bundleGroupRows(props.bundleGroups)) {
                                    if (group.leader.id === id) {
                                        next = group.leader
                                        break
                                    }
                                    const member = (group.members ?? []).find((m) => m.id === id)
                                    if (member) {
                                        next = member
                                        break
                                    }
                                }
                            }
                            
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
        selectedGroup,
        activeBundleGroup,
        isGroupDetailOpen,
        fieldLabelMap,
        answerKeys,
        allAnswerKeys,
        formatDate: formatSubmissionDate,
        humanizeKey,
        answerPreview,
        fileUrl,
        openDetail,
        openGroupDetail,
        closeGroupDetail,
        submitSubmissionReview,
        isSubmissionReviewing,
    }
}
