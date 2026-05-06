<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Search, Calendar, MapPin, Users } from 'lucide-vue-next'
import { formatDate, categoryLabelMap } from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'

interface Row {
    readonly id: string
    readonly title: string
    readonly date: string
    readonly location: string
    readonly attendees: number
    readonly category: string
    readonly status: string
    readonly statusTone: 'success' | 'warning' | 'muted' | 'destructive'
    readonly image: string
}

const props = withDefaults(
    defineProps<{
        events?: IEvent[]
    }>(),
    { events: () => [] },
)

const listVisible = ref(false)
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) listVisible.value = true
        },
        { threshold: 0.05 },
    )
    const el = document.getElementById('event-list')
    if (el) observer.observe(el)
})

function statusFor(reg: IEvent['registration_status']): { label: string; tone: Row['statusTone'] } {
    switch (reg) {
        case 'open':
            return { label: 'Open', tone: 'success' }
        case 'full':
            return { label: 'Full', tone: 'warning' }
        case 'closed':
            return { label: 'Closed', tone: 'muted' }
        default:
            return { label: 'Coming Soon', tone: 'muted' }
    }
}

const rows = computed<Row[]>(() =>
    props.events.map((e) => {
        const cats = toCategoryList(e.category)
        const primary = cats[0] ?? ''
        const s = statusFor(e.registration_status)
        return {
            id: e.id,
            title: e.title,
            date: formatDate(e.start_date),
            location: e.location,
            attendees: e.registered_count,
            category: primary ? categoryLabelMap[primary] ?? primary : 'Event',
            status: s.label,
            statusTone: s.tone,
            image: e.banner_url ?? '',
        }
    }),
)

const searchQuery = ref('')
const activeCategory = ref('All')

const categories = computed<string[]>(() => {
    const cats = [...new Set(rows.value.map((e) => e.category))]
    return ['All', ...cats]
})

const filteredEvents = computed<Row[]>(() => {
    let result = rows.value
    if (activeCategory.value !== 'All') {
        result = result.filter((e) => e.category === activeCategory.value)
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase()
        result = result.filter(
            (e) =>
                e.title.toLowerCase().includes(q) ||
                e.location.toLowerCase().includes(q) ||
                e.category.toLowerCase().includes(q),
        )
    }
    return result
})

const statusClasses: Record<Row['statusTone'], string> = {
    success: 'border-success/25 bg-success/10 text-success',
    warning: 'border-warning/30 bg-warning/15 text-warning-foreground',
    muted: 'border-border bg-muted text-muted-foreground',
    destructive: 'border-destructive/25 bg-destructive/10 text-destructive',
}
</script>

<template>
    <section id="event-list" class="relative overflow-hidden bg-background py-20 lg:py-28">
        <div class="pointer-events-none absolute right-8 top-10 h-72 w-72 rounded-full bg-primary/8 blur-3xl"></div>
        <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-display text-3xl font-bold tracking-[-0.035em] text-foreground">All events</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ filteredEvents.length }} events available</p>
                </div>
                <label class="flex items-center gap-2 rounded-xl border border-border bg-card px-3.5 py-2 shadow-xs transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] focus-within:border-primary focus-within:ring-3 focus-within:ring-primary/15">
                    <Search class="size-4 text-muted-foreground" :stroke-width="2.2" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search events..."
                        class="w-full min-w-[200px] border-0 bg-transparent text-sm font-medium text-foreground shadow-none outline-none placeholder:text-muted-foreground"
                    />
                </label>
            </div>

            <div class="mb-10 flex flex-wrap gap-2">
                <button
                    v-for="cat in categories"
                    :key="cat"
                    type="button"
                    class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-[background-color,color,border-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]"
                    :class="
                        activeCategory === cat
                            ? 'border-primary/30 bg-primary text-primary-foreground'
                            : 'border-border bg-card text-muted-foreground hover:border-primary/30 hover:text-foreground'
                    "
                    @click="activeCategory = cat"
                >
                    {{ cat }}
                </button>
            </div>

            <div v-if="filteredEvents.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <a
                    v-for="(event, i) in filteredEvents"
                    :key="event.id"
                    :href="`/events/${event.id}`"
                    :class="[
                        'app-surface group flex flex-col overflow-hidden p-0 transition-[opacity,transform] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        listVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${i * 60}ms` }"
                >
                    <div class="relative aspect-[16/10] overflow-hidden bg-muted">
                        <img
                            v-if="event.image"
                            :src="event.image"
                            :alt="event.title"
                            class="h-full w-full object-cover transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] group-hover:scale-[1.04]"
                        />
                        <span
                            class="absolute right-3 top-3 rounded-full border bg-card/95 px-2 py-0.5 text-[10px] font-semibold backdrop-blur"
                            :class="statusClasses[event.statusTone]"
                        >
                            {{ event.status }}
                        </span>
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <span class="mb-2.5 inline-flex w-fit items-center rounded-md border border-border bg-muted/60 px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
                            {{ event.category }}
                        </span>
                        <h3 class="font-display mb-2 line-clamp-2 text-base font-bold tracking-[-0.015em] text-foreground transition-colors group-hover:text-primary">
                            {{ event.title }}
                        </h3>
                        <div class="mt-auto space-y-1.5 pt-3 text-[11px] font-medium text-muted-foreground">
                            <div class="flex items-center gap-1.5">
                                <Calendar class="size-3" :stroke-width="2" />
                                {{ event.date }}
                            </div>
                            <div class="flex items-center gap-1.5">
                                <MapPin class="size-3" :stroke-width="2" />
                                <span class="truncate">{{ event.location }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <Users class="size-3" :stroke-width="2" />
                                {{ event.attendees.toLocaleString() }} registered
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                <Search class="mb-4 size-10 text-muted-foreground/40" :stroke-width="1.5" />
                <p class="font-display text-lg font-bold text-foreground">No events found</p>
                <p class="mt-1 text-sm text-muted-foreground">Try adjusting your search or filter.</p>
            </div>
        </div>
    </section>
</template>
