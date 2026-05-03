<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { MapPin, CalendarDays, Clock, DollarSign, Users, Send } from 'lucide-vue-next'
import {
    formatDate, formatDateTime, statusColorMap,
    categoryLabelMap, categoryColorMap, sessionLabelMap,
} from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    event: IEvent
    isRegistered: boolean
    registrationStatus: 'submitted' | 'pending' | 'approved' | 'rejected' | null
}>()

const event = props.event
const isRegistered = computed(() => props.isRegistered)
const registrationStatus = computed(() => props.registrationStatus)

const registrationStatusLabel: Record<IEvent['registration_status'], string> = {
    not_yet_open: 'Coming soon',
    open: 'Registration open',
    closed: 'Closed',
    full: 'Full',
}

const metaBlocks = [
    {
        title: 'Schedule',
        value: `${formatDate(event.start_date)} — ${formatDate(event.end_date)}`,
        icon: CalendarDays,
    },
    { title: 'Location', value: event.location, icon: MapPin },
    {
        title: 'Session',
        value: toCategoryList(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') || '—',
        icon: Clock,
    },
    {
        title: 'Price',
        value: event.price > 0 ? `Rp ${Number(event.price).toLocaleString('id-ID')}` : 'Free',
        icon: DollarSign,
    },
]
</script>

<template>
    <Head :title="event.title" />

    <div class="flex flex-col gap-6">
        <PageHeader :title="event.title" backHref="/dashboard/user/events">
            <template #actions>
                <div class="flex flex-wrap items-center gap-1.5">
                    <Badge
                        v-for="cat in toCategoryList(event.category)"
                        :key="cat"
                        class="text-[10px] text-white"
                        :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                    >
                        {{ categoryLabelMap[cat] ?? cat }}
                    </Badge>
                    <Badge variant="outline" class="text-[10px] capitalize">
                        {{ registrationStatusLabel[event.registration_status] }}
                    </Badge>
                </div>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="relative h-52 w-full lg:h-64">
                        <EventBannerImage :src="event.banner_url" :alt="event.title" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="m in metaBlocks"
                        :key="m.title"
                        class="flex gap-3 rounded-lg border border-border bg-card px-3 py-2.5 shadow-xs"
                    >
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted/60 text-primary">
                            <component :is="m.icon" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{{ m.title }}</p>
                            <p class="mt-0.5 text-sm font-medium leading-snug text-foreground">{{ m.value }}</p>
                        </div>
                    </div>
                </div>

                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">About this event</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <div class="prose prose-sm max-w-3xl text-pretty text-foreground/85" v-html="event.description" />
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-4">
                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium">Registration</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 pt-0">
                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="flex items-center gap-1.5 text-muted-foreground">
                                    <Users class="size-4 shrink-0" />
                                    Spots
                                </span>
                                <span class="font-semibold tabular-nums text-foreground">{{ event.registered_count }}/{{ event.quota }}</span>
                            </div>
                            <Progress :model-value="event.registered_count" :max="event.quota" class="h-2" />
                        </div>
                        <div class="space-y-1 text-xs text-muted-foreground">
                            <p><span class="text-foreground/80">Opens</span> {{ formatDateTime(event.registration_start) }}</p>
                            <p><span class="text-foreground/80">Closes</span> {{ formatDateTime(event.registration_end) }}</p>
                        </div>
                        <div v-if="!isRegistered && event.registration_status === 'open'">
                            <Button class="w-full" as-child>
                                <Link :href="`/dashboard/user/events/${event.id}/register`">
                                    <Send class="mr-1.5 size-4" />Register for this event
                                </Link>
                            </Button>
                        </div>
                        <div v-else-if="!isRegistered" class="rounded-lg border border-dashed bg-muted/20 px-3 py-2 text-center text-xs text-muted-foreground">
                            Registration is not available yet or has ended.
                        </div>
                        <div v-else class="flex flex-col gap-4">
                            <div class="rounded-xl border bg-success/5 p-4 text-center shadow-xs border-success/20">
                                <p class="text-sm font-bold text-success">You are registered</p>
                                <Badge
                                    variant="secondary"
                                    class="mt-1.5 text-[10px] capitalize"
                                    :style="{ color: statusColorMap[registrationStatus ?? 'submitted'] }"
                                >
                                    {{ registrationStatus === 'submitted' ? 'Submitted' : registrationStatus }}
                                </Badge>
                            </div>

                            <!-- QR Code Section -->
                            <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-4 bg-white shadow-sm">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Your Entry Ticket</p>
                                <div class="bg-muted size-32 rounded-lg flex items-center justify-center border">
                                    <!-- Placeholder QR Code -->
                                    <svg class="size-24 text-muted-foreground/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                        <rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 7h.01"/><path d="M17 7h.01"/><path d="M7 17h.01"/><path d="M17 17h.01"/><path d="M12 12h.01"/><path d="M12 7h.01"/><path d="M12 17h.01"/><path d="M7 12h.01"/><path d="M17 12h.01"/>
                                    </svg>
                                </div>
                                <p class="text-[9px] text-center text-muted-foreground leading-tight">Show this QR code at the event entrance for check-in.</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
