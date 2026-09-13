<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

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

const reassignForm = useForm({
    interviewer_id: '',
})

const rescheduleSessionId = ref<Record<string, string>>({})

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

function reassignInterview(interviewId: string) {
    reassignForm.post(routes.admin.recruitment.interviews.reassign(interviewId), {
        preserveScroll: true,
        onSuccess: () => reassignForm.reset('interviewer_id'),
    })
}

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

    <div class="mx-auto flex max-w-5xl flex-col gap-6">
        <PageHeader
            :title="`${session.division?.name ?? 'Interview'} · ${session.session_date}`"
            :subtitle="`${session.starts_at}–${session.ends_at} · ${session.location} · ${session.room}`"
            :back-href="routes.admin.recruitment.interviewSessions.index"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="routes.admin.recruitment.queue.show(session.id)">Monitor antrean</Link>
                </Button>
            </template>
        </PageHeader>

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

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Reassign interviewer</Label>
                            <div class="flex gap-2">
                                <select
                                    v-model="reassignForm.interviewer_id"
                                    class="border-input bg-background h-9 flex-1 rounded-md border px-3 text-sm"
                                >
                                    <option value="" disabled>Pilih interviewer</option>
                                    <option
                                        v-for="opt in interviewerOptions"
                                        :key="opt.id"
                                        :value="opt.id"
                                    >
                                        {{ opt.name }}
                                    </option>
                                </select>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="!reassignForm.interviewer_id"
                                    @click="reassignInterview(interview.id)"
                                >
                                    Ubah
                                </Button>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Reschedule ke sesi lain</Label>
                            <div class="flex gap-2">
                                <select
                                    v-model="rescheduleSessionId[interview.id]"
                                    class="border-input bg-background h-9 flex-1 rounded-md border px-3 text-sm"
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
    </div>
</template>
