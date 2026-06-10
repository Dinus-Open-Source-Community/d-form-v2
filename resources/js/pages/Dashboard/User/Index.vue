<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import KpiCard from '@/components/modules/dashboard/KpiCard.vue'
import EventCalendar from '@/components/modules/dashboard/EventCalendar.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { CalendarDays, Zap, Clock, MapPin, ArrowRight } from 'lucide-vue-next'
import { formatDate, categoryLabelMap, categoryColorMap } from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'
import { routes } from '@/lib/routes'

defineOptions({ layout: DashboardLayout })

interface Props {
    stats: {
        eventsJoined: number
        upcomingEvents: number
        pendingRegistrations: number
        acceptedRegistrations: number
    }
    upcomingEvents: IEvent[]
    pendingInvitations: Array<{
        event: IEvent
        invitationUrl: string
    }>
    calendarEvents: Array<{
        id: string | number
        title: string
        start_date: string
        end_date: string | null
        category: string | string[] | null
        href: string
    }>
}

const props = defineProps<Props>()
</script>

<template>
    <Head title="My Dashboard" />

    <div class="flex flex-col gap-6">
        <PageHeader title="My Dashboard" subtitle="Overview of your event participation." />

        <div class="grid gap-4 sm:grid-cols-3">
            <KpiCard label="Events Joined" :value="props.stats.eventsJoined" :trend="20" :icon="CalendarDays" color="primary" />
            <KpiCard label="Upcoming Events" :value="props.stats.upcomingEvents" :trend="0" :icon="Zap" color="warning" />
            <KpiCard label="Pending Registrations" :value="props.stats.pendingRegistrations" :trend="-10" :icon="Clock" color="destructive" />
        </div>

        <Card class="rounded-xl border shadow-xs">
            <CardHeader class="flex flex-row items-center justify-between pb-3">
                <CardTitle class="text-base font-medium">My Upcoming Events</CardTitle>
                <Button variant="ghost" size="sm" class="text-xs" as-child>
                    <Link :href="routes.member.browse">Lihat semua<ArrowRight class="ml-1 size-3" /></Link>
                </Button>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 pt-0">
                <Link
                    v-for="event in props.upcomingEvents"
                    :key="event.id"
                        :href="routes.member.event.show(event.slug)"
                    class="flex items-center gap-4 rounded-lg border p-3 transition-colors hover:bg-muted/30"
                >
                    <div class="aspect-[4/3] w-20 shrink-0 overflow-hidden rounded-md bg-muted">
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
                <p v-if="props.upcomingEvents.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                    No upcoming events. Browse events to find something interesting!
                </p>
            </CardContent>
        </Card>

        <EventCalendar :events="props.calendarEvents" />
    </div>
</template>
