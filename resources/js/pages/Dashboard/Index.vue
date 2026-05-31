<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import KpiCard from '@/components/modules/dashboard/KpiCard.vue';
import RecentEventsCard from '@/components/modules/dashboard/RecentEventsCard.vue';
import MiniCalendar from '@/components/modules/dashboard/MiniCalendar.vue';
import RegistrationChart from '@/components/modules/dashboard/RegistrationChart.vue';
import CategoryChart from '@/components/modules/dashboard/CategoryChart.vue';
import EventCalendar from '@/components/modules/dashboard/EventCalendar.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CalendarDays, Zap, Users, TrendingUp, Clock, MapPin, ArrowRight } from 'lucide-vue-next';
import useAuth from '@/utils/composables/useAuth';
import { formatDate, categoryLabelMap, categoryColorMap } from '@/lib/dummyData';
import { toCategoryList } from '@/lib/eventCategories';

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

const isAdmin = computed(() => {
    const roles = user.value?.roles;
    if (!roles || roles.length === 0) return false;
    return roles.includes('admin') || roles.includes('super-admin');
});

const events = computed(() => props.recentEvents);

const totalEvents = computed(() => props.stats.totalEvents);
const activeEvents = computed(() => props.stats.activeEvents);
const totalRegistrants = computed(() => props.stats.totalRegistrants);
const completionRate = computed(() => props.stats.completionRate);

const upcomingEvents = computed(() => events.value.filter((e) => new Date(e.start_date) > new Date()).slice(0, 3));

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
    <Head :title="isAdmin ? 'Beranda' : 'Dasbor saya'" />

    <div class="flex flex-col gap-10 md:gap-12">
        <!-- Admin -->
        <template v-if="isAdmin">
            <PageHeader
                title="Ringkasan acara"
                :subtitle="`${greeting}${firstName ? ', ' + firstName : ''}. Pantau pendaftaran, jadwal, dan performa acara dalam satu layar.`"
            />

            <section class="space-y-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <h2 class="font-display text-foreground text-base font-bold tracking-tight">Metrik utama</h2>
                </div>
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
                <h2 class="font-display text-foreground text-base font-bold tracking-tight">Aktivitas & kalender</h2>
                <div class="grid gap-5 lg:grid-cols-3 lg:items-start">
                    <div class="lg:col-span-2">
                        <RecentEventsCard
                            :events="events"
                            view-all-href="/admin/dashboard/events"
                            event-base-href="/admin/dashboard/events"
                        />
                    </div>
                    <MiniCalendar />
                </div>
            </section>

            <section v-if="adminCharts" class="space-y-4">
                <h2 class="font-display text-foreground text-base font-bold tracking-tight">Analitik singkat</h2>
                <div class="grid gap-5 lg:grid-cols-2">
                    <RegistrationChart
                        :points="adminCharts.registrationTrend.map(({ label, count }) => ({ label, count }))"
                    />
                    <CategoryChart :breakdown="adminCharts.categoryBreakdown" />
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="font-display text-foreground text-base font-bold tracking-tight">Linimasa acara</h2>
                <EventCalendar />
            </section>
        </template>

        <!-- Member -->
        <template v-else>
            <PageHeader
                title="Dasbor saya"
                :subtitle="`${greeting}! Kelola partisipasi Anda dan lihat acara yang akan datang.`"
            >
                <template #actions>
                    <Button as-child variant="outline" size="sm" class="h-9 rounded-xl">
                        <Link href="/events/joined" class="inline-flex items-center gap-1.5">
                            <CalendarDays class="size-4" />
                            Acara saya
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <section class="space-y-4">
                <h2 class="font-display text-foreground text-base font-bold tracking-tight">Ringkasan</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <KpiCard label="Acara diikuti" :value="3" :trend="20" :icon="CalendarDays" color="primary" />
                    <KpiCard
                        label="Akan datang"
                        :value="upcomingEvents.length"
                        :trend="0"
                        :icon="Zap"
                        color="warning"
                    />
                    <KpiCard label="Menunggu review" :value="1" :trend="-10" :icon="Clock" color="destructive" />
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="font-display text-foreground text-base font-bold tracking-tight">Acara mendatang</h2>
                <Card class="border-border/70 rounded-2xl shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.06]">
                    <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-3 pb-3">
                        <div>
                            <CardTitle class="text-base font-semibold">Daftar singkat</CardTitle>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Ketuk acara untuk detail dan status pendaftaran
                            </p>
                        </div>
                        <Button variant="outline" size="sm" class="h-8 rounded-lg text-xs" as-child>
                            <Link href="/events/joined" class="inline-flex items-center gap-1">
                                Semua
                                <ArrowRight class="size-3" />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2 pt-0">
                        <Link
                            v-for="event in upcomingEvents"
                            :key="event.id"
                            :href="`/events/joined/events/${event.slug}`"
                            class="border-border/70 hover:bg-muted/40 flex items-center gap-4 rounded-xl border p-3 transition-colors"
                        >
                            <div class="bg-muted aspect-[4/3] w-20 shrink-0 overflow-hidden rounded-lg">
                                <img
                                    :src="event.banner_url ?? ''"
                                    :alt="event.title"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ event.title }}</p>
                                <div class="text-muted-foreground mt-1 flex flex-wrap items-center gap-3 text-xs">
                                    <span class="inline-flex items-center gap-1">
                                        <CalendarDays class="size-3" />
                                        {{ formatDate(event.start_date) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <MapPin class="size-3" />
                                        {{ event.location }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-wrap justify-end gap-1">
                                <Badge
                                    v-for="cat in toCategoryList(event.category)"
                                    :key="cat"
                                    class="text-[10px] text-white"
                                    :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                                >
                                    {{ categoryLabelMap[cat] ?? cat }}
                                </Badge>
                            </div>
                        </Link>
                        <p v-if="upcomingEvents.length === 0" class="text-muted-foreground py-8 text-center text-sm">
                            Belum ada acara mendatang. Jelajahi acara dari beranda publik.
                        </p>
                    </CardContent>
                </Card>
            </section>

            <section class="space-y-4">
                <h2 class="font-display text-foreground text-base font-bold tracking-tight">Kalender</h2>
                <EventCalendar />
            </section>
        </template>
    </div>
</template>
