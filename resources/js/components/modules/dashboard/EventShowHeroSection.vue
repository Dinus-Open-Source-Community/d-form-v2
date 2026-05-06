<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Pencil, Sparkles, Users, CalendarDays, MapPin } from 'lucide-vue-next'
import { categoryColorMap, categoryLabelMap, formatDate } from '@/lib/dummyData'
import { parseEventCategories } from '@/lib/eventShowUi'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'
import { edit as editEvent } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController'

type StatusPill = { label: string; classes: string }

type MetaBlock = {
    title: string
    value: string
    icon: object
}

defineProps<{
    event: IEvent
    statusPill: StatusPill
    metaBlocks: MetaBlock[]
    cardShadow: string
}>()
</script>

<template>
    <section
        :class="[
            'relative overflow-hidden rounded-3xl border border-border/60 bg-card ring-1 ring-black/5',
            cardShadow,
        ]"
    >
        <div class="relative h-56 w-full sm:h-64 lg:h-80">
            <EventBannerImage :src="event.banner_url ?? event.banner" :alt="event.title" img-class="scale-[1.02]" />
            <div
                class="absolute inset-0 bg-[linear-gradient(180deg,transparent_0%,transparent_40%,color-mix(in_oklab,var(--card)_55%,transparent)_72%,var(--card)_100%)]"
            />
            <div class="absolute left-5 top-5 flex flex-wrap items-center gap-1.5 sm:left-7 sm:top-7">
                <span
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium backdrop-blur-md',
                        'bg-background/70 border-border/70',
                        statusPill.classes,
                    ]"
                >
                    <span class="size-1.5 rounded-full bg-current" />
                    {{ statusPill.label }}
                </span>
                <Badge
                    v-for="cat in parseEventCategories(event.category)"
                    :key="cat"
                    class="rounded-full border-0 px-2.5 py-1 text-[11px] font-medium text-white shadow-sm"
                    :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                >
                    {{ categoryLabelMap[cat] ?? cat }}
                </Badge>
            </div>
        </div>

        <div class="relative -mt-16 px-5 pb-6 sm:-mt-20 sm:px-8 sm:pb-8 lg:-mt-24">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="mb-2 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.12em] text-primary">
                        <Sparkles class="size-3" />
                        Event Overview
                    </p>
                    <h1 class="text-balance text-3xl font-semibold leading-[1.15] tracking-tight text-foreground sm:text-4xl lg:text-[2.6rem]">
                        {{ event.title }}
                    </h1>
                    <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                        <span class="inline-flex items-center gap-1.5">
                            <CalendarDays class="size-3.5" />
                            {{ formatDate(event.start_date) }}
                        </span>
                        <span class="text-border">·</span>
                        <span class="inline-flex items-center gap-1.5">
                            <MapPin class="size-3.5" />
                            {{ event.location }}
                        </span>
                    </p>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" class="rounded-full" as-child>
                        <Link :href="`/dashboard/events/${event.id}/registrants`">
                            <Users class="mr-1.5 size-3.5" />
                            Registrants
                        </Link>
                    </Button>
                    <Button size="sm" class="rounded-full" as-child>
                        <Link :href="editEvent.url(event.id)">
                            <Pencil class="mr-1.5 size-3.5" />
                            Edit Event
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="m in metaBlocks"
                    :key="m.title"
                    :class="['group flex items-center gap-3 rounded-2xl border border-border/60 bg-background/70 p-3.5 backdrop-blur-sm transition-all hover:border-primary/30 hover:bg-background', cardShadow]"
                >
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/8 text-primary ring-1 ring-primary/10 transition-colors group-hover:bg-primary/12">
                        <component :is="m.icon" class="size-[18px]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">{{ m.title }}</p>
                        <p class="mt-0.5 truncate text-[13.5px] font-medium leading-snug text-foreground">{{ m.value }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
