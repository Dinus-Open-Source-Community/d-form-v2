<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PeriodStatusHero from '@/components/modules/dashboard/recruitment/PeriodStatusHero.vue'
import PeriodPhaseTimeline from '@/components/modules/dashboard/recruitment/PeriodPhaseTimeline.vue'
import PeriodApplicantSection from '@/components/modules/dashboard/recruitment/PeriodApplicantSection.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { routes } from '@/lib/routes'
import type { PeriodStatusValue } from '@/lib/recruitmentPeriodPhase'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'

defineOptions({ layout: DashboardLayout })

interface Period {
    id: string
    name: string
    slug: string
    status: PeriodStatusValue
    status_label: string
    description: string | null
    registration_opens_at: string | null
    registration_closes_at: string | null
    interview_starts_at: string | null
    interview_ends_at: string | null
    finalization_deadline_at: string | null
    applications_count: number
}

interface ApplicationPaginator {
    data: {
        id: string
        registration_number: string
        full_name: string
        nim: string
        semester: number
        stage: string
        stage_label: string
        result: string
        result_label: string
        revision_required: boolean
        submitted_at: string | null
        primary_division: { id: string; name: string } | null
        period: { id: string; name: string } | null
    }[]
    current_page: number
    last_page: number
    total: number
}

interface SessionRow {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    is_active: boolean
    interviews_count: number
    period: { id: string; name: string } | null
    division: { id: string; name: string; code: string } | null
}

interface SessionPaginator {
    data: SessionRow[]
    current_page: number
    last_page: number
    total: number
}

interface ReportPayload {
    period: { id: string; name: string } | null
    funnel: { stage: string; label: string; count: number }[]
    by_division: { division: string; count: number }[]
    by_semester: { semester: number; count: number }[]
    interview_stats: Record<string, number>
    feedback: { count: number; averages: Record<string, number | null> }
}

const props = withDefaults(
    defineProps<{
        period: Period
        applications: ApplicationPaginator | null
        queue_counts: Record<string, number>
        divisionOptions: { id: string; name: string; code: string }[]
        stageOptions: { value: string; label: string }[]
        semesterOptions?: { value: string; label: string }[]
        query: {
            search?: string
            division_id?: string
            stage?: string
            queue?: string
            semester?: string
        }
        tab: string
        sessions?: SessionPaginator | null
        today_sessions?: SessionRow[]
        report?: ReportPayload | null
    }>(),
    { semesterOptions: () => [], today_sessions: () => [] },
)

const page = usePage()
const user = useAuth(page.props)
const canListApplications = computed(() => user.value?.can_list_recruitment_applications === true)
const canScheduleInterviews = computed(() => user.value?.can_schedule_recruitment_interviews === true)
const canViewReports = computed(() => user.value?.can_view_recruitment_reports === true)

const activeTab = ref(props.tab === 'interview' || props.tab === 'laporan' ? props.tab : 'peserta')
watch(
    () => props.tab,
    (value) => {
        activeTab.value = value === 'interview' || value === 'laporan' ? value : 'peserta'
    },
)

function onTabChange(value: string | number): void {
    const next = String(value)
    if (next !== 'peserta' && next !== 'interview' && next !== 'laporan') return
    if (next === activeTab.value) return
    router.get(
        routes.admin.recruitment.periods.show(props.period.id),
        { tab: next === 'peserta' ? undefined : next },
        { preserveState: true, preserveScroll: true },
    )
}

const phaseInput = computed(() => ({
    status: props.period.status,
    registrationOpensAt: props.period.registration_opens_at,
    registrationClosesAt: props.period.registration_closes_at,
    interviewStartsAt: props.period.interview_starts_at,
    interviewEndsAt: props.period.interview_ends_at,
    finalizationDeadlineAt: props.period.finalization_deadline_at,
}))

onMounted(() => {
    setTopbar({ title: props.period.name, subtitle: 'Detail periode Open Recruitment' })
})

function openPeriod() {
    router.post(routes.admin.recruitment.periods.open(props.period.id))
}

function closePeriod() {
    router.post(routes.admin.recruitment.periods.close(props.period.id))
}
</script>

<template>
    <Head :title="period.name" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <PeriodStatusHero
            :period="{
                id: period.id,
                name: period.name,
                slug: period.slug,
                ...phaseInput,
            }"
            @open="openPeriod"
            @close="closePeriod"
        />

        <PeriodPhaseTimeline :period="phaseInput" :status="period.status" />

        <Card class="rounded-2xl border-border/70 shadow-sm">
            <CardContent class="p-6">
                <h2 class="text-base font-medium">Deskripsi</h2>
                <p class="mt-2 text-sm" :class="period.description ? '' : 'text-muted-foreground'">
                    {{ period.description || 'Belum ada deskripsi.' }}
                </p>
            </CardContent>
        </Card>

        <Tabs :model-value="activeTab" @update:model-value="onTabChange" class="w-full">
            <TabsList class="flex w-full justify-start overflow-x-auto">
                <TabsTrigger v-if="canListApplications" value="peserta">Peserta</TabsTrigger>
                <TabsTrigger v-if="canScheduleInterviews" value="interview">Interview</TabsTrigger>
                <TabsTrigger v-if="canViewReports" value="laporan">Laporan</TabsTrigger>
            </TabsList>

            <TabsContent value="peserta" class="mt-4">
                <PeriodApplicantSection
                    v-if="canListApplications"
                    :period-id="period.id"
                    :tab="activeTab"
                    :applications="applications"
                    :queue-counts="queue_counts"
                    :division-options="divisionOptions"
                    :stage-options="stageOptions"
                    :semester-options="semesterOptions"
                    :query="query"
                />
            </TabsContent>

            <TabsContent value="interview" class="mt-4">
                <Card v-if="canScheduleInterviews" class="rounded-2xl border-border/70 shadow-sm">
                    <CardContent class="p-6">
                        <p class="text-sm text-muted-foreground">
                            Bagian interview segera hadir di Task 4.
                        </p>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="laporan" class="mt-4">
                <Card v-if="canViewReports" class="rounded-2xl border-border/70 shadow-sm">
                    <CardContent class="p-6">
                        <p class="text-sm text-muted-foreground">Bagian laporan segera hadir di Task 5.</p>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>
</template>
