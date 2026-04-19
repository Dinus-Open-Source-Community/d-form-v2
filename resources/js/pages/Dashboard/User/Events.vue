<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Progress } from '@/components/ui/progress'
import { Search, MapPin, CalendarDays, Users } from 'lucide-vue-next'
import { formatDate, categoryLabelMap, categoryColorMap } from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    events?: IEvent[]
}>()

const searchQuery = ref('')
const filterCategory = ref('all')
const activeTab = ref('all')

const allEvents = computed(() => props.events ?? [])

const filteredEvents = computed(() => {
    let list = allEvents.value

    if (activeTab.value === 'joined') {
        // TODO: filter by joined events when backend provides user registration data
        list = list.filter(() => false)
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase()
        list = list.filter((e) => e.title.toLowerCase().includes(q))
    }
    if (filterCategory.value !== 'all') list = list.filter((e) => toCategoryList(e.category).includes(filterCategory.value))
    return list
})
</script>

<template>
    <Head title="All Events" />

    <div class="flex flex-col gap-6">
        <PageHeader title="All Events" subtitle="Browse and register for upcoming events." />

        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border/60 bg-muted/30 p-3 shadow-xs">
            <div class="relative w-full max-w-xs">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="searchQuery" placeholder="Search events..." class="pl-9" />
            </div>
            <Select v-model="filterCategory">
                <SelectTrigger class="h-9 w-36 text-xs"><SelectValue placeholder="Category" /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Categories</SelectItem>
                    <SelectItem value="rkt">RKT</SelectItem>
                    <SelectItem value="non-rkt">NON RKT</SelectItem>
                    <SelectItem value="recruitment">Recruitment</SelectItem>
                    <SelectItem value="etc">Etc</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="all">All Events</TabsTrigger>
                <TabsTrigger value="joined">Joined</TabsTrigger>
            </TabsList>
        </Tabs>

        <div v-if="filteredEvents.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="event in filteredEvents"
                :key="event.id"
                :href="`/dashboard/user/events/${event.id}`"
                class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <Card class="overflow-hidden rounded-xl border shadow-xs transition-colors duration-150 hover:border-primary/25 hover:shadow-sm">
                    <div class="relative aspect-video w-full overflow-hidden bg-muted">
                        <div class="absolute inset-0 z-0">
                            <EventBannerImage :src="event.banner_url" :alt="event.title" />
                        </div>
                        <div class="pointer-events-none absolute inset-0 z-1 bg-linear-to-t from-black/35 via-transparent to-transparent" />
                        <div class="absolute left-2.5 top-2.5 z-2 flex flex-wrap gap-1.5">
                            <Badge
                                v-for="cat in toCategoryList(event.category)"
                                :key="cat"
                                class="text-[10px] text-white shadow-xs"
                                :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                            >
                                {{ categoryLabelMap[cat] ?? cat }}
                            </Badge>
                        </div>
                    </div>
                    <CardContent class="p-4">
                        <h3 class="truncate text-sm font-semibold text-foreground group-hover:text-primary">{{ event.title }}</h3>
                        <div class="mt-2.5 flex flex-col gap-1.5 text-xs text-muted-foreground">
                            <div class="flex items-center gap-1.5"><CalendarDays class="size-3 shrink-0" /><span>{{ formatDate(event.start_date) }}</span></div>
                            <div class="flex items-center gap-1.5"><MapPin class="size-3 shrink-0" /><span class="truncate">{{ event.location }}</span></div>
                        </div>
                        <div class="mt-3">
                            <div class="mb-1.5 flex items-center justify-between text-xs">
                                <span class="flex items-center gap-1 text-muted-foreground"><Users class="size-3" />Spots</span>
                                <span class="font-medium tabular-nums">{{ event.registered_count }}/{{ event.quota }}</span>
                            </div>
                            <Progress :model-value="event.registered_count" :max="event.quota" class="h-1.5" />
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <EmptyState v-else title="No events found" description="Try adjusting your search or filters." animation-url="https://lottie.host/4e039bf3-670e-4a0f-8a6c-1bee793bfc23/JkaGBMIxOz.json" />
    </div>
</template>
