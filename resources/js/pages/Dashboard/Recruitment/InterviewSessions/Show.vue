<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import InterviewerCreateSheet from '@/components/modules/dashboard/recruitment/InterviewerCreateSheet.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'
import { usePage } from '@inertiajs/vue3'
import { ScanLine, ListOrdered, Plus } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface InterviewRow {
    id: string
    scheduled_at: string | null
    location: string
    room: string
    status: string
    status_label: string
    application: {
        id: string
        full_name: string
        registration_number: string
    } | null
    interviewer: { id: string; name: string } | null
}

interface SessionDetail {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    notes: string | null
    is_active: boolean
    interviews_count: number
    period: { id: string; name: string } | null
    division: { id: string; name: string; code: string } | null
    interviews: InterviewRow[]
}

interface ApplicantOption {
    id: string
    full_name: string
    registration_number: string
    nim: string
}

const props = defineProps<{
    session: SessionDetail
    eligibleApplicants: ApplicantOption[]
    interviewerOptions: { id: string; name: string }[]
    otherSessions: { id: string; session_date: string; starts_at: string; division: { name: string } | null }[]
}>()

const selectedApplicants = ref<string[]>([])

const scheduleForm = useForm({
    application_ids: [] as string[],
})

const reassignValues = ref<Record<string, string>>({})
const reassignErrors = ref<Record<string, string>>({})
const reassignProcessing = ref<Record<string, boolean>>({})

const rescheduleSessionId = ref<Record<string, string>>({})

const page = usePage()
const user = useAuth(page.props)
const canViewQueue = computed(() => user.value?.can_view_recruitment_queue === true)
const canScanAttendance = computed(() => user.value?.can_scan_recruitment_attendance === true)

onMounted(() => {
    setTopbar({
        title: 'Detail sesi interview',
        subtitle: props.session.division?.name ?? '',
    })
})

const allSelected = computed({
    get: () =>
        props.eligibleApplicants.length > 0 &&
        selectedApplicants.value.length === props.eligibleApplicants.length,
    set: (value: boolean) => {
        selectedApplicants.value = value ? props.eligibleApplicants.map((a) => a.id) : []
    },
})

function scheduleSelected() {
    scheduleForm.application_ids = selectedApplicants.value
    scheduleForm.post(routes.admin.recruitment.interviewSessions.schedule(props.session.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedApplicants.value = []
            scheduleForm.reset()
        },
    })
}

function initialsOf(name: string): string {
    const words: string[] = name.trim().split(/\s+/).filter((w) => w.length > 0)
    if (words.length === 0) return '—'
    const first: string = words[0]?.charAt(0) ?? ''
    const second: string = words.length > 1 ? (words[1]?.charAt(0) ?? '') : ''
    const letters: string = `${first}${second}`.toUpperCase()
    return letters.length > 0 ? letters : '—'
}

function reassignOptionsFor(currentInterviewerId: string | null): SearchableSelectOption[] {
    return props.interviewerOptions.map((opt) => ({
        value: opt.id,
        label: opt.name,
        initials: initialsOf(opt.name),
        disabled: currentInterviewerId !== null && opt.id === currentInterviewerId,
    }))
}

function isReassignUnchanged(interviewId: string, currentInterviewerId: string | null): boolean {
    const next: string = reassignValues.value[interviewId] ?? ''
    if (next.length === 0) return true
    if (currentInterviewerId !== null && next === currentInterviewerId) return true
    return false
}

function canSubmitReassign(interviewId: string, currentInterviewerId: string | null): boolean {
    if (reassignProcessing.value[interviewId] === true) return false
    return !isReassignUnchanged(interviewId, currentInterviewerId)
}

function reassignInterview(interviewId: string, currentInterviewerId: string | null): void {
    const next: string = reassignValues.value[interviewId] ?? ''
    if (next.length === 0 || reassignProcessing.value[interviewId] === true) return
    if (currentInterviewerId !== null && next === currentInterviewerId) return
    reassignProcessing.value[interviewId] = true
    reassignErrors.value[interviewId] = ''
    router.post(
        routes.admin.recruitment.interviews.reassign(interviewId),
        { interviewer_id: next },
        {
            preserveScroll: true,
            onSuccess: () => {
                reassignValues.value[interviewId] = ''
                reassignErrors.value[interviewId] = ''
            },
            onError: (errors: Record<string, string>) => {
                const message: string = errors.interviewer_id ?? errors.interviewerId ?? ''
                reassignErrors.value[interviewId] =
                    message.length > 0 ? message : 'Gagal menyimpan. Coba lagi.'
            },
            onFinish: () => {
                reassignProcessing.value[interviewId] = false
            },
        },
    )
}

const isCreateSheetOpen = ref<boolean>(false)
const createTargetInterviewId = ref<string | null>(null)
const awaitingCreatedInterviewer = ref<boolean>(false)
const knownInterviewerIds = ref<Set<string>>(new Set())

function openCreateSheetFor(interviewId: string): void {
    createTargetInterviewId.value = interviewId
    knownInterviewerIds.value = new Set(props.interviewerOptions.map((o) => o.id))
    isCreateSheetOpen.value = true
}

function closeCreateSheet(): void {
    isCreateSheetOpen.value = false
    if (!awaitingCreatedInterviewer.value) {
        createTargetInterviewId.value = null
    }
}

function adoptNewInterviewer(): void {
    const target: string | null = createTargetInterviewId.value
    if (target === null || !awaitingCreatedInterviewer.value) return
    const fresh: { id: string; name: string } | undefined = props.interviewerOptions.find(
        (o) => !knownInterviewerIds.value.has(o.id),
    )
    if (!fresh) return
    reassignValues.value[target] = fresh.id
    reassignErrors.value[target] = ''
    awaitingCreatedInterviewer.value = false
    createTargetInterviewId.value = null
}

function onInterviewerCreated(): void {
    awaitingCreatedInterviewer.value = true
    adoptNewInterviewer()
}

watch(
    () => props.interviewerOptions,
    () => {
        adoptNewInterviewer()
    },
)

function rescheduleInterview(interviewId: string) {
    const sessionId = rescheduleSessionId.value[interviewId]
    if (!sessionId) return

    router.post(
        routes.admin.recruitment.interviews.reschedule(interviewId),
        { recruitment_interview_session_id: sessionId },
        { preserveScroll: true },
    )
}
</script>

<template>
    <Head title="Detail Sesi Interview" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <Button v-if="canScanAttendance" variant="secondary" as-child>
                <Link :href="routes.admin.scan.index">
                    <ScanLine class="mr-2 size-4" />
                    Scan absensi
                </Link>
            </Button>
            <Button v-if="canViewQueue" as-child>
                <Link :href="routes.admin.recruitment.queue.show(session.id)">
                    <ListOrdered class="mr-2 size-4" />
                    Monitor antrean
                </Link>
            </Button>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Informasi sesi</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-2 text-sm sm:grid-cols-2">
                <p><span class="text-muted-foreground">Periode:</span> {{ session.period?.name ?? '—' }}</p>
                <p><span class="text-muted-foreground">Terjadwal:</span> {{ session.interviews.length }} applicant</p>
                <p v-if="session.notes" class="sm:col-span-2">{{ session.notes }}</p>
            </CardContent>
        </Card>

        <Card v-if="eligibleApplicants.length > 0" class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Jadwalkan applicant</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="allSelected" type="checkbox" />
                    Pilih semua ({{ eligibleApplicants.length }})
                </label>
                <div class="space-y-2">
                    <label
                        v-for="applicant in eligibleApplicants"
                        :key="applicant.id"
                        class="flex items-center gap-3 rounded-lg border p-3 text-sm"
                    >
                        <input v-model="selectedApplicants" type="checkbox" :value="applicant.id" />
                        <div>
                            <p class="font-medium">{{ applicant.full_name }}</p>
                            <p class="text-muted-foreground font-mono text-xs">
                                {{ applicant.registration_number }} · {{ applicant.nim }}
                            </p>
                        </div>
                    </label>
                </div>
                <Button :disabled="selectedApplicants.length === 0 || scheduleForm.processing" @click="scheduleSelected">
                    Jadwalkan terpilih
                </Button>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Daftar terjadwal</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-for="interview in session.interviews"
                    :key="interview.id"
                    class="rounded-xl border p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ interview.application?.full_name ?? '—' }}</p>
                            <p class="text-muted-foreground font-mono text-xs">
                                {{ interview.application?.registration_number }}
                            </p>
                            <p class="text-muted-foreground mt-1 text-sm">
                                Interviewer: {{ interview.interviewer?.name ?? '—' }} · {{ interview.status_label }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label :for="`reassign-${interview.id}`">Reassign interviewer</Label>
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <SearchableSelect
                                        :id="`reassign-${interview.id}`"
                                        :model-value="reassignValues[interview.id] ?? ''"
                                        :options="reassignOptionsFor(interview.interviewer?.id ?? null)"
                                        placeholder="Pilih interviewer"
                                        search-placeholder="Cari interviewer…"
                                        class="h-9 text-[13px]"
                                        :aria-invalid="reassignErrors[interview.id] ? true : undefined"
                                        @update:model-value="(v: string) => { reassignValues[interview.id] = v }"
                                        @create="openCreateSheetFor(interview.id)"
                                    >
                                        <template #action>
                                            <Plus class="size-4" aria-hidden="true" />
                                            <span>Tambah interviewer baru</span>
                                        </template>
                                    </SearchableSelect>
                                    <p
                                        v-if="reassignErrors[interview.id]"
                                        role="alert"
                                        class="mt-1.5 text-xs text-destructive"
                                    >
                                        {{ reassignErrors[interview.id] }}
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="mt-0.5 shrink-0"
                                    :disabled="!canSubmitReassign(interview.id, interview.interviewer?.id ?? null)"
                                    @click="reassignInterview(interview.id, interview.interviewer?.id ?? null)"
                                >
                                    {{ reassignProcessing[interview.id] === true ? 'Menyimpan…' : 'Ubah' }}
                                </Button>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Reschedule ke sesi lain</Label>
                            <div class="flex items-start gap-2">
                                <select
                                    v-model="rescheduleSessionId[interview.id]"
                                    class="border-input bg-background h-9 min-w-0 flex-1 rounded-md border px-3 text-sm"
                                >
                                    <option value="" disabled>Pilih sesi</option>
                                    <option
                                        v-for="other in otherSessions"
                                        :key="other.id"
                                        :value="other.id"
                                    >
                                        {{ other.session_date }} {{ other.starts_at }}
                                        <template v-if="other.division"> · {{ other.division.name }}</template>
                                    </option>
                                </select>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="mt-0.5 shrink-0"
                                    :disabled="!rescheduleSessionId[interview.id]"
                                    @click="rescheduleInterview(interview.id)"
                                >
                                    Pindah
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="session.interviews.length === 0" class="text-muted-foreground text-sm">
                    Belum ada applicant dijadwalkan pada sesi ini.
                </p>
            </CardContent>
        </Card>

        <InterviewerCreateSheet
            :open="isCreateSheetOpen"
            :divisions="session.division ? [session.division] : []"
            :initial-division-id="session.division?.id ?? ''"
            @close="closeCreateSheet"
            @created="onInterviewerCreated"
        />
    </div>
</template>
