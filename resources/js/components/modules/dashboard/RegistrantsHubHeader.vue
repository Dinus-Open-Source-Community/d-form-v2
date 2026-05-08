<script setup lang="ts">
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'
import { CalendarDays, MapPin, Users } from 'lucide-vue-next'
import { formatDate } from '@/lib/dummyData'

defineProps<{
    event: IEvent
    cardShadow: string
}>()

function bannerSrc(ev: IEvent): string | null {
    return ev.banner_url ?? (ev.banner ? String(ev.banner) : null)
}
</script>

<template>
    <section
        :class="[
            'overflow-hidden rounded-2xl border border-border/60 bg-card ring-1 ring-black/5',
            cardShadow,
        ]"
    >
        <!-- Banner: selalu ditampilkan (termasuk mobile); sebelumnya tersembunyi di breakpoint kecil -->
        <div class="flex flex-col sm:flex-row sm:items-stretch">
            <div
                class="relative h-44 w-full shrink-0 overflow-hidden sm:h-auto sm:min-h-[7.5rem] sm:w-52 md:w-60"
            >
                <EventBannerImage :src="bannerSrc(event)" :alt="event.title" />
            </div>
            <div class="flex min-w-0 flex-1 flex-col justify-center gap-3 p-4 sm:p-5">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
                        Ringkasan acara
                    </p>
                    <p class="mt-1 text-sm leading-snug text-foreground sm:text-base">
                        {{ event.title }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5">
                        <CalendarDays class="size-3.5 shrink-0 text-primary/80" aria-hidden="true" />
                        {{ formatDate(event.start_date) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <MapPin class="size-3.5 shrink-0 text-primary/80" aria-hidden="true" />
                        {{ event.location }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <Users class="size-3.5 shrink-0 text-primary/80" aria-hidden="true" />
                        <span class="tabular-nums">
                            <span class="font-medium text-foreground">{{ event.registered_count }}</span>
                            /
                            {{ event.quota }} kuota
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
