<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Progress } from '@/components/ui/progress'
import { Plus, Search, MapPin, CalendarDays, Users, ChevronsLeft, ChevronsRight } from 'lucide-vue-next'
import { index as eventsIndex } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController'
import {
    dummyEvents, formatDate, statusColorMap,
    categoryLabelMap, categoryColorMap,
} from '@/lib/dummyData'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'

defineOptions({ layout: DashboardLayout })

interface Paginator {
    data: IEvent[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
}

const props = defineProps<{
    events?: Paginator
    filterOptions?: {
        categories: { value: string; label: string }[]
        sessions: { value: string; label: string }[]
        statuses: { value: string; label: string }[]
    }
    query?: {
        search?: string
        filter?: {
            categories?: string[]
            sessions?: string[]
            statuses?: string[]
            showTrashed?: boolean
        }
        sort?: { by: string; order: string }
        per_page?: number
    }
}>()

const isServerSide = computed(() => !!props.events)

const searchQuery = ref(props.query?.search ?? '')
const filterCategory = ref(props.query?.filter?.categories?.[0] ?? 'all')
const filterSession = ref(props.query?.filter?.sessions?.[0] ?? 'all')
const activeTab = ref(props.query?.filter?.showTrashed ? 'archived' : 'all')

const defaultCategories = [
    { value: 'rkt', label: 'RKT' },
    { value: 'non-rkt', label: 'NON RKT' },
    { value: 'recruitment', label: 'Recruitment' },
    { value: 'etc', label: 'Etc' },
]
const defaultSessions = [
    { value: 'general', label: 'General' },
    { value: 'programming', label: 'Programming' },
    { value: 'network', label: 'Networking' },
    { value: 'media_creative', label: 'Media Creative' },
    { value: 'data', label: 'Data' },
]

const categoryOptions = computed(() => props.filterOptions?.categories ?? defaultCategories)
const sessionOptions = computed(() => props.filterOptions?.sessions ?? defaultSessions)

function eventTokenList(v: unknown): string[] {
    if (Array.isArray(v)) return v.map((s) => String(s).trim()).filter(Boolean)
    if (typeof v === 'string') return v.split(',').map((s) => s.trim()).filter(Boolean)
    return []
}

function buildQueryParams(page?: number) {
    const params: Record<string, unknown> = {}
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    if (page && page > 1) params.page = page

    const filter: Record<string, unknown> = {}
    if (filterCategory.value !== 'all') filter.categories = [filterCategory.value]
    if (filterSession.value !== 'all') filter.sessions = [filterSession.value]
    if (activeTab.value === 'archived') filter.showTrashed = true
    if (Object.keys(filter).length > 0) params.filter = filter

    return params
}

function applyFilters() {
    if (!isServerSide.value) return
    router.get(eventsIndex().url, buildQueryParams(), { preserveState: true, preserveScroll: true })
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null
function onSearchInput() {
    if (!isServerSide.value) return
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 400)
}

watch([filterCategory, filterSession], applyFilters)
watch(activeTab, applyFilters)

function goToPage(page: number) {
    if (!isServerSide.value) return
    router.get(eventsIndex().url, buildQueryParams(page), { preserveState: true, preserveScroll: true })
}

const filteredEvents = computed(() => {
    if (isServerSide.value) {
        let events = props.events!.data
        const now = new Date().toISOString().split('T')[0]
        if (activeTab.value === 'upcoming') events = events.filter(e => e.start_date > now && !e.deleted_at)
        else if (activeTab.value === 'ongoing') events = events.filter(e => e.start_date <= now && e.end_date >= now && !e.deleted_at)
        else if (activeTab.value === 'completed') events = events.filter(e => e.end_date < now && !e.deleted_at)
        return events
    }

    let events = [...dummyEvents]
    const now = new Date().toISOString().split('T')[0]

    if (activeTab.value === 'upcoming') events = events.filter(e => e.start_date > now && !e.deleted_at)
    else if (activeTab.value === 'ongoing') events = events.filter(e => e.start_date <= now && e.end_date >= now && !e.deleted_at)
    else if (activeTab.value === 'completed') events = events.filter(e => e.end_date < now && !e.deleted_at)
    else if (activeTab.value === 'archived') events = events.filter(e => e.deleted_at !== null)
    else events = events.filter(e => !e.deleted_at)

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase()
        events = events.filter(e => e.title.toLowerCase().includes(q))
    }
    if (filterCategory.value !== 'all') {
        events = events.filter((e) => eventTokenList(e.category).includes(filterCategory.value))
    }
    if (filterSession.value !== 'all') events = events.filter(e => eventTokenList(e.session).includes(filterSession.value))

    return events
})

const currentPage = computed(() => props.events?.current_page ?? 1)
const lastPage = computed(() => props.events?.last_page ?? 1)
const totalEvents = computed(() => props.events?.total ?? filteredEvents.value.length)
</script>

<template>
    <Head title="Events" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Events" subtitle="Manage all your events in one place.">
            <template #actions>
                <Button as-child>
                    <Link href="/dashboard/events/create">
                        <Plus class="mr-1.5 size-4" />
                        Create Event
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border/60 bg-muted/30 p-3 shadow-xs">
            <div class="relative w-full max-w-xs">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    placeholder="Search events..."
                    class="pl-9"
                    @input="onSearchInput"
                />
            </div>
            <Select v-model="filterCategory">
                <SelectTrigger class="h-9 w-36 text-xs">
                    <SelectValue placeholder="Category" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Categories</SelectItem>
                    <SelectItem v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="filterSession">
                <SelectTrigger class="h-9 w-36 text-xs">
                    <SelectValue placeholder="Session" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Sessions</SelectItem>
                    <SelectItem v-for="opt in sessionOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="all">All</TabsTrigger>
                <TabsTrigger value="upcoming">Upcoming</TabsTrigger>
                <TabsTrigger value="ongoing">Ongoing</TabsTrigger>
                <TabsTrigger value="completed">Completed</TabsTrigger>
                <TabsTrigger value="archived">Archived</TabsTrigger>
            </TabsList>
        </Tabs>

        <div v-if="filteredEvents.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="event in filteredEvents"
                :key="event.id"
                :href="`/dashboard/events/${event.id}`"
                class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <Card class="overflow-hidden rounded-xl border shadow-xs transition-colors duration-150 hover:border-primary/25 hover:shadow-sm">
                    <div class="relative aspect-video w-full overflow-hidden bg-muted">
                        <div class="absolute inset-0 z-0">
                            <EventBannerImage :src="event.banner_url" :alt="event.title" />
                        </div>
                        <div class="pointer-events-none absolute inset-0 z-1 bg-linear-to-t from-black/35 via-transparent to-transparent" />
                        <div class="absolute left-2.5 top-2.5 z-2 flex flex-wrap gap-1">
                            <Badge
                                v-for="(cat, idx) in eventTokenList(event.category)"
                                :key="`${event.id}-cat-${idx}`"
                                class="text-[10px] text-white shadow-xs"
                                :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                            >
                                {{ categoryLabelMap[cat] ?? cat }}
                            </Badge>
                            <Badge v-if="event.deleted_at" variant="destructive" class="text-[10px]">Archived</Badge>
                        </div>
                        <div class="absolute right-2.5 top-2.5 z-2">
                            <Badge variant="secondary" class="bg-white/90 text-[10px] backdrop-blur-sm" :style="{ color: statusColorMap[event.status] }">
                                {{ event.status === 'published' ? 'Published' : 'Draft' }}
                            </Badge>
                        </div>
                    </div>
                    <CardContent class="p-4">
                        <h3 class="truncate text-sm font-semibold text-foreground group-hover:text-primary">{{ event.title }}</h3>
                        <div class="mt-2.5 flex flex-col gap-1.5 text-xs text-muted-foreground">
                            <div class="flex items-center gap-1.5">
                                <CalendarDays class="size-3 shrink-0" />
                                <span>{{ formatDate(event.start_date) }} — {{ formatDate(event.end_date) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <MapPin class="size-3 shrink-0" />
                                <span class="truncate">{{ event.location }}</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="mb-1.5 flex items-center justify-between text-xs">
                                <span class="flex items-center gap-1 text-muted-foreground">
                                    <Users class="size-3" />
                                    Registrants
                                </span>
                                <span class="font-medium tabular-nums">{{ event.registered_count }}/{{ event.quota }}</span>
                            </div>
                            <Progress :model-value="event.registered_count" :max="event.quota" class="h-1.5" />
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <EmptyState
            v-else
            title="No events found"
            description="Try adjusting your search or filters to find what you're looking for."
            animation-url="https://lottie.host/4e039bf3-670e-4a0f-8a6c-1bee793bfc23/JkaGBMIxOz.json"
        />

        <div v-if="isServerSide && lastPage > 1" class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
                Showing {{ props.events!.from }}–{{ props.events!.to }} of {{ totalEvents }} events
            </p>
            <div class="flex items-center gap-1">
                <Button variant="outline" size="icon" class="size-8" :disabled="currentPage <= 1" @click="goToPage(1)">
                    <ChevronsLeft class="size-4" />
                </Button>
                <Button variant="outline" size="sm" class="h-8" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
                    Previous
                </Button>
                <span class="px-3 text-sm tabular-nums">{{ currentPage }} / {{ lastPage }}</span>
                <Button variant="outline" size="sm" class="h-8" :disabled="currentPage >= lastPage" @click="goToPage(currentPage + 1)">
                    Next
                </Button>
                <Button variant="outline" size="icon" class="size-8" :disabled="currentPage >= lastPage" @click="goToPage(lastPage)">
                    <ChevronsRight class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
