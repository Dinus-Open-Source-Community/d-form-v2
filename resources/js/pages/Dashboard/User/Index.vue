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
import { dummyEvents, formatDate, categoryLabelMap, categoryColorMap } from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'

defineOptions({ layout: DashboardLayout })

const upcomingEvents = dummyEvents
    .filter((e) => e.status === 'published' && !e.deleted_at && new Date(e.start_date) > new Date())
    .slice(0, 3)
</script>

<template>
    <Head title="My Dashboard" />

    <div class="flex flex-col gap-6">
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
    </div>
</template>
