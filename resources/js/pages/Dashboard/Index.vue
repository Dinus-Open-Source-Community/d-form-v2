<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import KpiCard from '@/components/modules/dashboard/KpiCard.vue';
import RecentEventsCard from '@/components/modules/dashboard/RecentEventsCard.vue';
import { Button } from '@/components/ui/button';
import { ArrowRight, CalendarCheck2, CalendarDays, Gauge, UsersRound } from 'lucide-vue-next';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import { routes } from '@/lib/routes';
import type { IWeeklyKpiTrends } from '@/types/kpi';

defineOptions({ layout: DashboardLayout });

const props = defineProps<{
    recentEvents: IEvent[];
    canCreateEvent: boolean;
    stats: {
        totalEvents: number;
        activeEvents: number;
        totalRegistrants: number;
        completionRate: number;
    };
    weeklyKpiTrends: IWeeklyKpiTrends;
}>();

const events = computed(() => props.recentEvents);
const createEventHref = computed<string | null>(() => (props.canCreateEvent ? routes.admin.events.create : null));

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

        <section class="space-y-4" data-testid="recent-events-section">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="font-display text-foreground text-base font-bold tracking-tight">Aktivitas terbaru</h2>
                    <p class="text-muted-foreground mt-1 text-xs leading-snug">
                        {{ events.length }} acara terbaru di workspace Anda
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="app-focus-ring h-[44px] shrink-0 gap-1 self-start text-xs sm:h-8 sm:self-auto"
                    as-child
                >
                    <Link :href="routes.admin.events.index">
                        Lihat semua
                        <ArrowRight class="size-3" :stroke-width="2.4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
            <RecentEventsCard :events="events" :create-event-href="createEventHref" />
        </section>
    </div>
</template>
