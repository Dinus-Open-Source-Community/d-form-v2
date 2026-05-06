<script setup lang="ts">
import { computed } from 'vue'
import { ArrowRight, Calendar, MapPin, Users, Star } from 'lucide-vue-next'
import { formatDate, categoryLabelMap } from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'

interface Highlight {
    readonly id: string
    readonly title: string
    readonly description: string
    readonly date: string
    readonly location: string
    readonly attendees: number
    readonly status: string
    readonly image: string
    readonly categoryLabel: string
}

const props = defineProps<{
    events: IEvent[]
}>()

const featured = computed<IEvent | null>(() => props.events[0] ?? null)

const highlight = computed<Highlight | null>(() => {
    const e = featured.value
    if (!e) return null
    const description = e.description?.replace(/<[^>]+>/g, '') ?? ''
    const trimmed = description.length > 220 ? `${description.slice(0, 220)}…` : description
    const cats = toCategoryList(e.category)
    const primary = cats[0] ?? ''
    return {
        id: e.id,
        title: e.title,
        description: trimmed,
        date: `${formatDate(e.start_date)} — ${formatDate(e.end_date)}`,
        location: e.location,
        attendees: e.registered_count,
        status: e.registration_status === 'open' ? 'Open' : 'Featured',
        image: e.banner_url ?? '',
        categoryLabel: primary ? categoryLabelMap[primary] ?? primary : 'Event',
    }
})
</script>

<template>
    <section v-if="highlight" class="border-y border-border bg-muted/30 py-14">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mb-8 flex items-center gap-3">
                <div class="grid size-9 place-items-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                    <Star class="size-4" :stroke-width="2.4" />
                </div>
                <h2 class="font-display text-2xl font-bold tracking-[-0.025em] text-foreground">Highlighted Event</h2>
            </div>

            <div class="app-surface overflow-hidden p-0">
                <div class="grid lg:grid-cols-5">
                    <div class="relative bg-muted lg:col-span-2">
                        <img
                            v-if="highlight.image"
                            :src="highlight.image"
                            :alt="highlight.title"
                            class="h-full min-h-[280px] w-full object-cover"
                        />
                        <span class="absolute left-4 top-4 inline-flex items-center gap-1 rounded-full border border-border bg-card/95 px-2.5 py-1 text-[11px] font-semibold text-foreground backdrop-blur">
                            {{ highlight.categoryLabel }}
                        </span>
                    </div>
                    <div class="flex flex-col justify-center p-8 lg:col-span-3 lg:p-10">
                        <div class="mb-3 inline-flex w-fit items-center gap-1.5 rounded-full border border-success/25 bg-success/10 px-2.5 py-1 text-[11px] font-semibold text-success">
                            <span class="relative flex size-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success opacity-60"></span>
                                <span class="relative inline-flex size-1.5 rounded-full bg-success"></span>
                            </span>
                            <span>{{ highlight.status }}</span>
                        </div>
                        <h3 class="font-display text-2xl font-bold tracking-[-0.025em] text-foreground sm:text-3xl">{{ highlight.title }}</h3>
                        <p class="mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground">{{ highlight.description }}</p>
                        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-medium text-muted-foreground">
                            <span class="inline-flex items-center gap-1.5">
                                <Calendar class="size-3.5" :stroke-width="2" />
                                {{ highlight.date }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <MapPin class="size-3.5" :stroke-width="2" />
                                {{ highlight.location }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <Users class="size-3.5" :stroke-width="2" />
                                {{ highlight.attendees.toLocaleString() }} registered
                            </span>
                        </div>
                        <a
                            :href="`/events/${highlight.id}`"
                            class="mt-7 inline-flex w-fit items-center gap-2 rounded-xl border border-primary/15 bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-[transform,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:bg-primary/92 active:scale-[0.98]"
                        >
                            View event
                            <ArrowRight class="size-3.5" :stroke-width="2.4" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
