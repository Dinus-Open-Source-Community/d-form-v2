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
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-3">
            <CardTitle class="font-display text-lg font-bold tracking-[-0.02em]">Recent events</CardTitle>
            <Button variant="ghost" size="sm" class="text-xs text-muted-foreground" as-child>
                <Link :href="allHref">
                    View all
                    <ArrowRight class="ml-1 size-3" :stroke-width="2.4" />
                </Link>
            </Button>
        </CardHeader>
        <CardContent class="flex flex-col gap-1 pt-0">
            <Link
                v-for="event in recentEvents"
                :key="event.id"
                :href="`${baseHref}/${event.id}`"
                class="group flex items-start gap-3 rounded-xl border border-transparent p-2.5 transition-[border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:border-border hover:bg-muted/40"
            >
                <img
                    :src="event.banner_url ?? ''"
                    :alt="event.title"
                    class="size-11 shrink-0 rounded-lg border border-border object-cover"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-foreground transition-colors group-hover:text-primary">{{ event.title }}</p>
                    <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] font-medium text-muted-foreground">
                        <span class="inline-flex items-center gap-1">
                            <CalendarDays class="size-3" :stroke-width="2" />
                            {{ formatDate(event.start_date) }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <MapPin class="size-3" :stroke-width="2" />
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
                <span class="shrink-0 rounded-md border border-border bg-card px-2 py-0.5 text-[11px] font-semibold tabular-nums text-foreground shadow-xs">
                    {{ event.registered_count }}/{{ event.quota }}
                </span>
            </Link>
            <p v-if="recentEvents.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                No events yet.
            </p>
        </CardContent>
    </Card>
</template>
