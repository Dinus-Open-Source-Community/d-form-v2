<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PeriodStatusHero from '@/components/modules/dashboard/recruitment/PeriodStatusHero.vue'
import PeriodStatCards from '@/components/modules/dashboard/recruitment/PeriodStatCards.vue'
import PeriodPhaseTimeline from '@/components/modules/dashboard/recruitment/PeriodPhaseTimeline.vue'
import PeriodApplicantSection from '@/components/modules/dashboard/recruitment/PeriodApplicantSection.vue'
import { Card, CardContent } from '@/components/ui/card'
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

const props = defineProps<{
    period: Period
    applications: ApplicationPaginator | null
    queue_counts: Record<string, number>
    divisionOptions: { id: string; name: string; code: string }[]
    stageOptions: { value: string; label: string }[]
    query: {
        search?: string
        division_id?: string
        stage?: string
        queue?: string
        semester?: string
    }
}>()

const page = usePage()
const user = useAuth(page.props)
const canListApplications = computed(() => user.value?.can_list_recruitment_applications === true)

const phaseInput = computed(() => ({
    status: props.period.status,
    registrationOpensAt: props.period.registration_opens_at,
    registrationClosesAt: props.period.registration_closes_at,
    interviewStartsAt: props.period.interview_starts_at,
    interviewEndsAt: props.period.interview_ends_at,
    finalizationDeadlineAt: props.period.finalization_deadline_at,
}))

const activeQueue = computed(() => props.query.queue ?? '')

onMounted(() => {
    setTopbar({ title: props.period.name, subtitle: 'Detail periode Open Recruitment' })
})

function openPeriod() {
    router.post(routes.admin.recruitment.periods.open(props.period.id))
}

function closePeriod() {
    router.post(routes.admin.recruitment.periods.close(props.period.id))
}

function selectQueue(queue: string) {
    router.get(
        routes.admin.recruitment.periods.show(props.period.id),
        {
            search: props.query.search || undefined,
            division_id: props.query.division_id || undefined,
            stage: queue ? undefined : props.query.stage || undefined,
            queue: queue || undefined,
            semester: props.query.semester || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    )
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

        <PeriodStatCards
            v-if="canListApplications"
            :applications-count="period.applications_count"
            :queue-counts="queue_counts"
            :active-queue="activeQueue"
            @select="selectQueue"
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

        <PeriodApplicantSection
            v-if="canListApplications"
            :period-id="period.id"
            :applications="applications"
            :queue-counts="queue_counts"
            :division-options="divisionOptions"
            :stage-options="stageOptions"
            :query="query"
        />
    </div>
</template>
