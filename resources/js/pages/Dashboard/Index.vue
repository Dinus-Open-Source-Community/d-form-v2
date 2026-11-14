<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DeferredRender from '@/components/core/DeferredRender.vue';
import KpiCard from '@/components/modules/dashboard/KpiCard.vue';
import RecentEventsCard from '@/components/modules/dashboard/RecentEventsCard.vue';
import { CalendarCheck2, CalendarDays, Gauge, UsersRound } from 'lucide-vue-next';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import { routes } from '@/lib/routes';
import type { IWeeklyKpiTrends } from '@/types/kpi';

defineOptions({ layout: DashboardLayout });

// Chart.js + vue-chartjs hanya diunduh saat bagian analitik mendekati viewport.
const RegistrationChart = defineAsyncComponent(() => import('@/components/modules/dashboard/RegistrationChart.vue'));
const CategoryChart = defineAsyncComponent(() => import('@/components/modules/dashboard/CategoryChart.vue'));

const props = defineProps<{
    recentEvents: IEvent[];
    stats: {
        totalEvents: number;
        activeEvents: number;
        totalRegistrants: number;
        completionRate: number;
    };
    adminCharts?: {
        registrationTrend: { key: string; label: string; count: number }[];
        categoryBreakdown: { token: string; count: number }[];
    } | null;
    weeklyKpiTrends: IWeeklyKpiTrends;
}>();

const events = computed(() => props.recentEvents);
const registrationPoints = computed(
    () => props.adminCharts?.registrationTrend.map(({ label, count }) => ({ label, count })) ?? []
);
const categoryBreakdown = computed(() => props.adminCharts?.categoryBreakdown ?? []);

const totalEvents = computed(() => props.stats.totalEvents);
const activeEvents = computed(() => props.stats.activeEvents);
const totalRegistrants = computed(() => props.stats.totalRegistrants);
const completionRate = computed(() => props.stats.completionRate);

onMounted(() => {
    setTopbar({ title: 'Ringkasan acara', subtitle: 'Pantau pendaftaran, jadwal, dan performa acara' });
});
</script>

<template>
    <Head title="Beranda" />

    <div data-slot="dashboard-section-stack" class="flex flex-col gap-6 md:gap-8">
        <section class="space-y-4">
            <h2 class="font-display text-foreground text-base font-bold tracking-tight">KPI</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Total acara"
                    :value="totalEvents"
                    :icon="CalendarDays"
                    color="primary"
                    :weekly-trend="weeklyKpiTrends.totalEvents"
                />
                <KpiCard
                    label="Acara aktif"
                    :value="activeEvents"
                    :icon="CalendarCheck2"
                    color="warning"
                    :weekly-trend="weeklyKpiTrends.activeEvents"
                />
                <KpiCard
                    label="Total pendaftar"
                    :value="totalRegistrants.toLocaleString('id-ID')"
                    :icon="UsersRound"
                    color="success"
                    :weekly-trend="weeklyKpiTrends.totalRegistrants"
                />
                <KpiCard
                    label="Tingkat isi kuota"
                    :value="completionRate + '%'"
                    :icon="Gauge"
                    color="primary"
                    :weekly-trend="weeklyKpiTrends.completionRate"
                    weekly-trend-absolute-delta-unit="percentagePoints"
                />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="font-display text-foreground text-base font-bold tracking-tight">Aktivitas terbaru</h2>
            <RecentEventsCard
                :events="events"
                :view-all-href="routes.admin.events.index"
                :event-base-href="routes.admin.events.index"
            />
        </section>

        <section v-if="adminCharts" class="space-y-4">
            <h2 class="font-display text-foreground text-base font-bold tracking-tight">Analitik singkat</h2>
            <DeferredRender min-height="20rem">
                <div class="grid gap-5 lg:grid-cols-2">
                    <RegistrationChart :points="registrationPoints" />
                    <CategoryChart :breakdown="categoryBreakdown" />
                </div>
            </DeferredRender>
        </section>
    </div>
</template>
