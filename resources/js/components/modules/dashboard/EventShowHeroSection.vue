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
            'overflow-hidden rounded-3xl border border-border/60 bg-card ring-1 ring-black/5',
            cardShadow,
        ]"
    >
        <!-- Banner dan konten terpisah (tanpa margin negatif / scale) agar gambar tidak menumpuk teks -->
        <div class="grid grid-cols-1 lg:grid-cols-12 lg:items-stretch">
            <div class="relative isolate w-full overflow-hidden bg-muted lg:col-span-5">
                <div
                    class="aspect-[5/3] w-full max-h-[220px] sm:aspect-[16/9] sm:max-h-[260px] lg:aspect-auto lg:h-full lg:min-h-[292px] lg:max-h-none"
                >
                    <EventBannerImage
                        :src="event.banner_url ?? event.banner"
                        :alt="event.title"
                        class="h-full w-full"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-6 px-5 py-6 sm:px-7 sm:py-7 lg:col-span-7 lg:justify-between lg:px-8 lg:py-8">
                <div class="flex min-w-0 flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium',
                                'border-border/70 bg-muted/60 text-foreground',
                                statusPill.classes,
                            ]"
                        >
                            <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true" />
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

                    <div>
                        <p
                            class="mb-2 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.12em] text-primary"
                        >
                            <Sparkles class="size-3 shrink-0" aria-hidden="true" />
                            Ringkasan acara
                        </p>
                        <h1
                            class="text-balance text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl lg:text-[1.85rem] lg:leading-snug"
                        >
                            {{ event.title }}
                        </h1>
                        <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm text-muted-foreground">
                            <span class="inline-flex min-w-0 items-center gap-1.5">
                                <CalendarDays class="size-3.5 shrink-0 text-primary/80" aria-hidden="true" />
                                {{ formatDate(event.start_date) }}
                            </span>
                            <span class="text-border" aria-hidden="true">·</span>
                            <span class="inline-flex min-w-0 items-center gap-1.5">
                                <MapPin class="size-3.5 shrink-0 text-primary/80" aria-hidden="true" />
                                <span class="truncate">{{ event.location }}</span>
                            </span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <Button variant="outline" size="sm" class="rounded-full" as-child>
                            <Link :href="`/admin/dashboard/events/${event.id}/registrants`">
                                <Users class="mr-1.5 size-3.5" aria-hidden="true" />
                                Pendaftar
                            </Link>
                        </Button>
                        <Button size="sm" class="rounded-full" as-child>
                            <Link :href="editEvent.url(event.id)">
                                <Pencil class="mr-1.5 size-3.5" aria-hidden="true" />
                                Ubah acara
                            </Link>
                        </Button>
                    </div>
                </div>

                <div class="grid gap-2.5 sm:grid-cols-2 sm:gap-3">
                    <div
                        v-for="m in metaBlocks"
                        :key="m.title"
                        :class="[
                            'flex min-h-[4.25rem] items-center gap-3 rounded-2xl border border-border/60 bg-muted/25 p-3 sm:p-3.5',
                            'transition-colors hover:border-primary/25 hover:bg-muted/40',
                            cardShadow,
                        ]"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15"
                        >
                            <component :is="m.icon" class="size-[18px]" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
                                {{ m.title }}
                            </p>
                            <p
                                class="mt-0.5 line-clamp-2 text-[13px] font-medium leading-snug text-foreground sm:line-clamp-none sm:truncate"
                            >
                                {{ m.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
