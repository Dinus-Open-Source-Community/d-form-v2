<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import KpiCard from '@/components/modules/dashboard/KpiCard.vue';
import RecentEventsCard from '@/components/modules/dashboard/RecentEventsCard.vue';
import MiniCalendar from '@/components/modules/dashboard/MiniCalendar.vue';
import RegistrationChart from '@/components/modules/dashboard/RegistrationChart.vue';
import CategoryChart from '@/components/modules/dashboard/CategoryChart.vue';
import EventCalendar from '@/components/modules/dashboard/EventCalendar.vue';
import { CalendarDays, Zap, Users, TrendingUp } from 'lucide-vue-next';
import useAuth from '@/utils/composables/useAuth';
import { routes } from '@/lib/routes';

defineOptions({ layout: DashboardLayout });

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
}>();

const page = usePage();
const user = useAuth(page.props);

const events = computed(() => props.recentEvents);

const totalEvents = computed(() => props.stats.totalEvents);
const activeEvents = computed(() => props.stats.activeEvents);
const totalRegistrants = computed(() => props.stats.totalRegistrants);
const completionRate = computed(() => props.stats.completionRate);

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 11) return 'Selamat pagi';
    if (h < 15) return 'Selamat siang';
    if (h < 19) return 'Selamat sore';
    return 'Selamat malam';
});

const firstName = computed(() => {
    const n = user.value?.name?.trim();
    if (!n) return '';
    return n.split(/\s+/)[0] ?? n;
});
</script>

<template>
    <Head title="Beranda" />

    <div class="flex flex-col gap-10 md:gap-12">
        <PageHeader
            title="Ringkasan acara"
            :subtitle="`${greeting}${firstName ? ', ' + firstName : ''}. Pantau pendaftaran, jadwal, dan performa acara dalam satu layar.`"
        />

        <section class="space-y-4">
            <h2 class="font-display text-base font-bold tracking-tight text-foreground">Metrik utama</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Total acara" :value="totalEvents" :icon="CalendarDays" color="primary" />
                <KpiCard label="Acara aktif" :value="activeEvents" :icon="Zap" color="warning" />
                <KpiCard
                    label="Total pendaftar"
                    :value="totalRegistrants.toLocaleString('id-ID')"
                    :icon="Users"
                    color="success"
                />
                <KpiCard
                    label="Tingkat isi kuota"
                    :value="completionRate + '%'"
                    :icon="TrendingUp"
                    color="primary"
                />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="font-display text-base font-bold tracking-tight text-foreground">Aktivitas & kalender</h2>
            <div class="grid gap-5 lg:grid-cols-3 lg:items-start">
                <div class="lg:col-span-2">
                    <RecentEventsCard
                        :events="events"
                        :view-all-href="routes.admin.events.index"
                        :event-base-href="routes.admin.events.index"
                    />
                </div>
                <MiniCalendar />
            </div>
        </section>

        <section v-if="adminCharts" class="space-y-4">
            <h2 class="font-display text-base font-bold tracking-tight text-foreground">Analitik singkat</h2>
            <div class="grid gap-5 lg:grid-cols-2">
                <RegistrationChart
                    :points="adminCharts.registrationTrend.map(({ label, count }) => ({ label, count }))"
                />
                <CategoryChart :breakdown="adminCharts.categoryBreakdown" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="font-display text-base font-bold tracking-tight text-foreground">Linimasa acara</h2>
            <EventCalendar />
        </section>
    </div>
</template>
