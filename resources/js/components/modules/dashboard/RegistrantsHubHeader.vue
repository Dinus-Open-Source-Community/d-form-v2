<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'
import { Button } from '@/components/ui/button'
import { Sparkles, CalendarDays, MapPin, Users, ArrowUpRight } from 'lucide-vue-next'
import { formatDate } from '@/lib/dummyData'

defineProps<{
    event: IEvent
    cardShadow: string
}>()
</script>

<template>
    <section
        :class="[
            'relative flex items-stretch gap-4 overflow-hidden rounded-3xl border border-border/60 bg-card p-4 ring-1 ring-black/5 sm:gap-5 sm:p-5',
            cardShadow,
        ]"
    >
        <div class="hidden h-28 w-44 shrink-0 overflow-hidden rounded-2xl ring-1 ring-black/5 sm:block">
            <EventBannerImage :src="event.banner_url" :alt="event.title" />
        </div>
        <div class="flex min-w-0 flex-1 flex-col justify-center gap-1.5">
            <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.12em] text-primary">
                <Sparkles class="size-3" />
                Registrants Hub
            </p>
            <h1 class="truncate text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                {{ event.title }}
            </h1>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-1.5">
                    <CalendarDays class="size-3.5" />
                    {{ formatDate(event.start_date) }}
                </span>
                <span class="text-border">·</span>
                <span class="inline-flex items-center gap-1.5">
                    <MapPin class="size-3.5" />
                    {{ event.location }}
                </span>
                <span class="text-border">·</span>
                <span class="inline-flex items-center gap-1.5">
                    <Users class="size-3.5" />
                    <span class="tabular-nums">
                        <span class="font-medium text-foreground">{{ event.registered_count }}</span> /
                        {{ event.quota }} seats
                    </span>
                </span>
            </p>
        </div>
        <div class="hidden shrink-0 items-center sm:flex">
            <Button variant="outline" size="sm" class="rounded-full" as-child>
                <Link :href="`/dashboard/events/${event.id}`">
                    View event
                    <ArrowUpRight class="ml-1 size-3.5" />
                </Link>
            </Button>
        </div>
    </section>
</template>
