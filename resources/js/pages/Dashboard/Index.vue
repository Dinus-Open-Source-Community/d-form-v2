<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import KpiCard from '@/components/modules/dashboard/KpiCard.vue'
import RecentEventsCard from '@/components/modules/dashboard/RecentEventsCard.vue'
import MiniCalendar from '@/components/modules/dashboard/MiniCalendar.vue'
import RegistrationChart from '@/components/modules/dashboard/RegistrationChart.vue'
import CategoryChart from '@/components/modules/dashboard/CategoryChart.vue'
import EventCalendar from '@/components/modules/dashboard/EventCalendar.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { CalendarDays, Zap, Users, TrendingUp, Clock, MapPin, ArrowRight } from 'lucide-vue-next'
import useAuth from '@/utils/composables/useAuth'
import { dummyEvents, formatDate, categoryLabelMap, categoryColorMap } from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    recentEvents?: IEvent[]
    stats?: {
        totalEvents: number
        activeEvents: number
        totalRegistrants: number
        completionRate: number
    }
}>()

const page = usePage()
const user = useAuth(page.props)

const isAdmin = computed(() => {
    const roles = user.value?.roles
    if (!roles || roles.length === 0) return false
    return roles.includes('admin') || roles.includes('super-admin')
})

const events = computed(() => props.recentEvents ?? dummyEvents.filter(e => !e.deleted_at).slice(0, 5))

const totalEvents = computed(() => props.stats?.totalEvents ?? dummyEvents.filter(e => !e.deleted_at).length)
const activeEvents = computed(() => props.stats?.activeEvents ?? dummyEvents.filter(e => e.status === 'published' && !e.deleted_at).length)
const totalRegistrants = computed(() => props.stats?.totalRegistrants ?? dummyEvents.reduce((s, e) => s + e.registered_count, 0))
const completionRate = computed(() => props.stats?.completionRate ?? 0)

const upcomingEvents = computed(() =>
    events.value.filter(e => new Date(e.start_date) > new Date()).slice(0, 3),
)
</script>

<template>
    <Head :title="isAdmin ? 'Dashboard' : 'My Dashboard'" />

    <div class="flex flex-col gap-6">
        <!-- Admin Dashboard -->
        <template v-if="isAdmin">
            <PageHeader title="Dashboard" subtitle="Overview of your events and activity." />

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCard label="Total Events" :value="totalEvents" :trend="12" :icon="CalendarDays" color="primary" />
                <KpiCard label="Active Events" :value="activeEvents" :trend="8" :icon="Zap" color="warning" />
                <KpiCard label="Total Registrants" :value="totalRegistrants.toLocaleString()" :trend="24" :icon="Users" color="success" />
                <KpiCard label="Completion Rate" :value="completionRate + '%'" :trend="-3" :icon="TrendingUp" color="primary" />
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <RecentEventsCard :events="events" view-all-href="/dashboard/events" event-base-href="/dashboard/events" />
                </div>
                <MiniCalendar />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <RegistrationChart />
                <CategoryChart />
            </div>

            <EventCalendar />
        </template>

        <!-- User Dashboard -->
        <template v-else>
            <PageHeader title="My Dashboard" subtitle="Overview of your event participation." />

            <div class="grid gap-4 sm:grid-cols-3">
                <KpiCard label="Events Joined" :value="3" :trend="20" :icon="CalendarDays" color="primary" />
                <KpiCard label="Upcoming Events" :value="upcomingEvents.length" :trend="0" :icon="Zap" color="warning" />
                <KpiCard label="Pending Registrations" :value="1" :trend="-10" :icon="Clock" color="destructive" />
            </div>

            <Card class="rounded-xl border shadow-xs">
                <CardHeader class="flex flex-row items-center justify-between pb-3">
                    <CardTitle class="text-base font-medium">My Upcoming Events</CardTitle>
                    <Button variant="ghost" size="sm" class="text-xs" as-child>
                        <Link href="/dashboard/user/events">View All<ArrowRight class="ml-1 size-3" /></Link>
                    </Button>
                </CardHeader>
                <CardContent class="flex flex-col gap-3 pt-0">
                    <Link
                        v-for="event in upcomingEvents"
                        :key="event.id"
                        :href="`/dashboard/user/events/${event.id}`"
                        class="flex items-center gap-4 rounded-lg border p-3 transition-colors hover:bg-muted/30"
                    >
                        <div class="h-12 w-20 shrink-0 overflow-hidden rounded-md bg-muted">
                            <img :src="event.banner_url ?? ''" :alt="event.title" class="h-full w-full object-cover" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ event.title }}</p>
                            <div class="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1"><CalendarDays class="size-3" />{{ formatDate(event.start_date) }}</span>
                                <span class="flex items-center gap-1"><MapPin class="size-3" />{{ event.location }}</span>
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
                    <p v-if="upcomingEvents.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                        No upcoming events. Browse events to find something interesting!
                    </p>
                </CardContent>
            </Card>

            <EventCalendar />
        </template>
    </div>
</template>
