<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { categoryLabelMap } from '@/constants/eventDisplay';
import { formatDateTime } from '@/lib/dateTime';
import { toCategoryList } from '@/lib/eventCategories';
import { CalendarDays, MapPin, ArrowRight } from 'lucide-vue-next';
import { routes } from '@/lib/routes';
import { EVENT_CARD_BANNER_ASPECT } from '@/lib/eventBannerAspect';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';

const props = defineProps<{
    events?: IEvent[];
    viewAllHref?: string;
    eventBaseHref?: string;
}>();

const recentEvents = computed(() =>
    [...(props.events ?? [])]
        .filter((e) => !e.deleted_at)
        .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
        .slice(0, 4)
);

/** Banner server sudah absolut; normalisasi menjaga payload lama/relatif tetap inert. */
function bannerSrc(event: IEvent): string {
    return normalizePublicStorageUrl(event.banner_url ?? '');
}

const allHref = computed(() => props.viewAllHref ?? routes.admin.events.index);
const baseHref = computed(() => props.eventBaseHref ?? routes.admin.events.index);
</script>

<template>
    <Card class="border-border/70 rounded-2xl shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.06]">
        <CardHeader class="flex flex-row items-start justify-between gap-3 space-y-0 pb-3">
            <div>
                <CardTitle class="font-display text-lg font-bold tracking-[-0.02em]">Acara terbaru</CardTitle>
                <p class="text-muted-foreground mt-1 text-xs leading-snug">Aktivitas terakhir di workspace Anda</p>
            </div>
            <Button variant="outline" size="sm" class="h-8 shrink-0 gap-1 text-xs" as-child>
                <Link :href="allHref">
                    Lihat semua
                    <ArrowRight class="size-3" :stroke-width="2.4" />
                </Link>
            </Button>
        </CardHeader>
        <CardContent class="flex flex-col gap-1 pt-0">
            <Link
                v-for="event in recentEvents"
                :key="event.id"
                :href="`${baseHref}/${event.id}`"
                class="group hover:border-border hover:bg-muted/40 flex items-start gap-3 rounded-xl border border-transparent p-2 transition-[border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] sm:p-2.5"
            >
                <div
                    :class="[
                        'border-border bg-muted hidden w-16 shrink-0 overflow-hidden rounded-lg border sm:block',
                        EVENT_CARD_BANNER_ASPECT,
                    ]"
                >
                    <img :src="bannerSrc(event)" :alt="event.title" class="size-full object-cover" />
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        class="text-foreground group-hover:text-primary truncate text-[13px] font-semibold transition-colors sm:text-sm"
                    >
                        {{ event.title }}
                    </p>
                    <div
                        class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] font-medium"
                    >
                        <span class="inline-flex items-center gap-1">
                            <CalendarDays class="size-3" :stroke-width="2" />
                            {{ formatDateTime({ value: event.start_date }) }}
                        </span>
                        <span class="xs:inline-flex hidden items-center gap-1 sm:inline-flex">
                            <MapPin class="size-3" :stroke-width="2" />
                            {{ event.location?.split('—')[0]?.trim() ?? event.location }}
                        </span>
                    </div>
                    <div class="mt-1.5 hidden flex-wrap gap-1 sm:flex">
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
                <span
                    class="border-border bg-card text-foreground shrink-0 rounded-md border px-1.5 py-0.5 text-[10px] font-semibold tabular-nums shadow-xs sm:px-2 sm:text-[11px]"
                >
                    {{ event.registered_count }}/{{ event.quota }}
                </span>
            </Link>
            <p v-if="recentEvents.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                Belum ada acara. Buat acara pertama untuk mulai.
            </p>
        </CardContent>
    </Card>
</template>
