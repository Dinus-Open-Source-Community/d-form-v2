<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { CalendarDays, Clock, MapPin, Tag } from 'lucide-vue-next';
import { categoryColorMap, categoryLabelMap } from '@/constants/eventDisplay';
import { parseEventCategories } from '@/lib/eventShowUi';
import EventBannerImage from '@/components/core/EventBannerImage.vue';
import { EVENT_HERO_BANNER_ASPECT } from '@/lib/eventBannerAspect';

type StatusPill = { label: string; classes: string };

defineProps<{
    event: IEvent;
    statusPill: StatusPill;
    dateRange: string;
    sessionLabel: string;
    priceLabel: string;
    cardShadow: string;
}>();
</script>

<template>
    <section
        :class="[
            'border-border/60 bg-card overflow-hidden rounded-2xl border ring-1 ring-black/5 sm:rounded-3xl',
            cardShadow,
        ]"
    >
        <!-- Banner dan konten terpisah (tanpa margin negatif / scale) agar gambar tidak menumpuk teks -->
        <div class="grid min-w-0 grid-cols-1 xl:grid-cols-12 xl:items-stretch">
            <div class="bg-muted relative isolate w-full min-w-0 overflow-hidden xl:col-span-5">
                <div :class="[EVENT_HERO_BANNER_ASPECT, 'w-full xl:aspect-auto xl:h-full xl:min-h-[292px]']">
                    <EventBannerImage :src="event.banner_url" :alt="event.title" class="h-full w-full" />
                </div>
            </div>

            <div
                class="flex min-w-0 flex-col gap-5 px-4 py-5 sm:gap-6 sm:px-7 sm:py-7 xl:col-span-7 xl:justify-between xl:px-8 xl:py-8"
            >
                <div class="flex min-w-0 flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11px] font-medium',
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
                            class="border-0 px-2.5 py-1 text-[11px] font-medium text-white shadow-sm"
                            :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                        >
                            {{ categoryLabelMap[cat] ?? cat }}
                        </Badge>
                    </div>

                    <div>
                        <h1
                            class="text-foreground text-[1.45rem] leading-[1.12] font-semibold tracking-tight text-balance break-words sm:text-3xl lg:text-[1.85rem] lg:leading-snug"
                        >
                            {{ event.title }}
                        </h1>
                        <div class="mt-3 flex flex-col gap-2.5">
                            <p
                                class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[0.9375rem] leading-relaxed"
                            >
                                <span class="inline-flex min-w-0 items-center gap-1.5">
                                    <CalendarDays class="text-primary/80 size-3.5 shrink-0" aria-hidden="true" />
                                    {{ dateRange }}
                                </span>
                                <span class="text-border" aria-hidden="true">·</span>
                                <span class="inline-flex min-w-0 items-center gap-1.5">
                                    <MapPin class="text-primary/80 size-3.5 shrink-0" aria-hidden="true" />
                                    <span class="break-words sm:truncate">{{ event.location }}</span>
                                </span>
                            </p>
                            <div class="border-border/60 border-t" aria-hidden="true" />
                            <p class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                                <span class="inline-flex min-w-0 items-center gap-1.5">
                                    <Clock class="text-primary/80 size-3.5 shrink-0" aria-hidden="true" />
                                    {{ sessionLabel }}
                                </span>
                                <span class="text-border" aria-hidden="true">·</span>
                                <span class="inline-flex min-w-0 items-center gap-1.5">
                                    <Tag class="text-primary/80 size-3.5 shrink-0" aria-hidden="true" />
                                    {{ priceLabel }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
