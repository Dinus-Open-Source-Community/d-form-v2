<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { dummyEvents, formatDate, categoryLabelMap } from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'
import { CalendarDays, MapPin, ArrowRight } from 'lucide-vue-next'

const props = defineProps<{
    events?: IEvent[]
    viewAllHref?: string
    eventBaseHref?: string
}>()

const recentEvents = computed(() =>
    props.events ??
    dummyEvents
        .filter((e) => !e.deleted_at)
        .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
        .slice(0, 4),
)

const allHref = computed(() => props.viewAllHref ?? '/dashboard/events')
const baseHref = computed(() => props.eventBaseHref ?? '/dashboard/events')
</script>

<template>
    <Card class="rounded-xl border shadow-xs">
        <CardHeader class="flex flex-row items-center justify-between pb-3">
            <CardTitle class="text-base font-medium">Recent Events</CardTitle>
            <Button variant="ghost" size="sm" class="text-xs text-muted-foreground" as-child>
                <Link :href="allHref">
                    View all
                    <ArrowRight class="ml-1 size-3" />
                </Link>
            </Button>
        </CardHeader>
        <CardContent class="flex flex-col gap-2 pt-0">
            <Link
                v-for="event in recentEvents"
                :key="event.id"
                :href="`${baseHref}/${event.id}`"
                class="group flex items-start gap-3 rounded-lg border border-transparent p-3 transition-colors hover:border-border hover:bg-muted/40"
            >
                <img
                    :src="event.banner_url ?? ''"
                    :alt="event.title"
                    class="size-10 shrink-0 rounded-lg object-cover"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium group-hover:text-primary">{{ event.title }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1">
                            <CalendarDays class="size-3" />
                            {{ formatDate(event.start_date) }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <MapPin class="size-3" />
                            {{ event.location?.split('—')[0]?.trim() ?? event.location }}
                        </span>
                    </div>
                    <div class="mt-1.5 flex flex-wrap gap-1">
                        <Badge
                            v-for="cat in toCategoryList(event.category)"
                            :key="cat"
                            variant="secondary"
                            class="text-[10px]"
                        >
                            {{ categoryLabelMap[cat] ?? cat }}
                        </Badge>
                    </div>
                </div>
                <span class="shrink-0 text-xs tabular-nums text-muted-foreground">
                    {{ event.registered_count }}/{{ event.quota }}
                </span>
            </Link>
            <p v-if="recentEvents.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                No events yet.
            </p>
        </CardContent>
    </Card>
</template>
