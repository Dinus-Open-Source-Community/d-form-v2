<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import EventBannerImage from '@/components/core/EventBannerImage.vue';
import { categoryLabelMap } from '@/constants/eventDisplay';
import { formatDateTime } from '@/lib/dateTime';
import { toCategoryList } from '@/lib/eventCategories';
import { eventPublicationBadge, eventQuotaFillPercent, eventRegistrationBadge } from '@/lib/eventStatusUi';
import type { IEventStatusBadge } from '@/lib/eventStatusUi';
import { routes } from '@/lib/routes';
import { CalendarDays, CalendarX2, SquarePen, UsersRound } from 'lucide-vue-next';

defineProps<{
    events?: IEvent[];
    createEventHref?: string | null;
}>();

// 44px ditulis literal karena `resources/css/app.css` memakai `html { font-size: 15px }`
// di bawah 640px, sehingga `h-11` hanya 41.25px dan gagal target sentuh 44px.
const ACTION_BUTTON_CLASS = 'h-[44px] min-w-[44px] flex-1 md:h-8 md:min-w-0 md:flex-none';

/** Chip status satu baris: badge publikasi (draf) lalu badge pendaftaran, maksimal dua. */
function statusBadges(event: IEvent): IEventStatusBadge[] {
    const publication = eventPublicationBadge(event.status);
    const registration = eventRegistrationBadge(event.registration_status);

    return publication ? [publication, registration] : [registration];
}

/** Teks kuota baris: `terdaftar/kuota`, atau `-` bila kuota tidak tersedia. */
function quotaText(event: IEvent): string {
    return event.quota > 0 ? `${event.registered_count}/${event.quota}` : '-';
}

/** Persentase isi kuota untuk bar dan label aksesibilitas. */
function quotaFillPercent(event: IEvent): number {
    return eventQuotaFillPercent({ registered: event.registered_count, quota: event.quota });
}

/** Label kategori pertama acara, string kosong bila tidak ada. */
function categoryLabel(event: IEvent): string {
    const [first] = toCategoryList(event.category);

    return first ? (categoryLabelMap[first] ?? first) : '';
}
</script>

<template>
    <Card class="gap-0 overflow-hidden rounded-2xl py-0 shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.06]">
        <ul v-if="(events ?? []).length > 0" class="divide-border/60 divide-y">
            <li
                v-for="event in events ?? []"
                :key="event.id"
                data-testid="recent-event-row"
                class="flex flex-col gap-3 px-3 py-3 md:flex-row md:items-center md:gap-4 md:px-4"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <Link
                        :href="routes.admin.events.show(event.id)"
                        class="app-focus-ring group hover:bg-muted/40 flex min-w-0 flex-1 items-center gap-3 rounded-xl p-1.5 transition-colors duration-150"
                    >
                        <div
                            class="border-border/60 bg-muted h-[42px] w-[56px] shrink-0 overflow-hidden rounded-lg border md:h-[60px] md:w-[80px]"
                        >
                            <EventBannerImage
                                :src="event.banner_url"
                                :alt="event.title"
                                img-class="size-full object-cover"
                            />
                        </div>
                        <div class="min-w-0 flex-1 space-y-1">
                            <h3
                                class="text-foreground group-hover:text-primary min-w-0 truncate text-sm leading-snug font-semibold tracking-tight transition-colors"
                            >
                                {{ event.title }}
                            </h3>
                            <div class="flex flex-wrap items-center gap-1">
                                <Badge
                                    v-for="badge in statusBadges(event)"
                                    :key="`${event.id}-${badge.label}`"
                                    variant="outline"
                                    :class="['px-2 py-0.5 text-[11px] font-medium whitespace-nowrap', badge.className]"
                                >
                                    {{ badge.label }}
                                </Badge>
                            </div>
                            <div class="text-muted-foreground flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                <span class="inline-flex items-center gap-1">
                                    <CalendarDays class="size-3 shrink-0" aria-hidden="true" />
                                    {{ formatDateTime({ value: event.start_date, variant: 'date-medium' }) }}
                                </span>
                                <span v-if="event.location" class="min-w-0 truncate">
                                    {{ event.location.split('—')[0]?.trim() ?? event.location }}
                                </span>
                                <span v-if="categoryLabel(event)" class="hidden sm:inline">
                                    {{ categoryLabel(event) }}
                                </span>
                            </div>
                        </div>
                    </Link>

                    <div
                        class="flex w-20 shrink-0 flex-col items-end gap-1"
                        role="group"
                        :aria-label="`${event.registered_count} dari ${event.quota} pendaftar (${quotaFillPercent(event)}%)`"
                    >
                        <span data-testid="recent-event-quota" class="text-foreground text-xs font-semibold tabular-nums">
                            {{ quotaText(event) }}
                        </span>
                        <Progress
                            v-if="event.quota > 0"
                            :model-value="quotaFillPercent(event)"
                            :max="100"
                            aria-hidden="true"
                            class="bg-muted/70 h-1 w-16"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-2 md:shrink-0">
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="['app-focus-ring', ACTION_BUTTON_CLASS]"
                        as-child
                    >
                        <Link
                            :href="routes.admin.events.show(event.id)"
                            data-testid="recent-event-action-manage"
                            class="text-xs"
                        >
                            <SquarePen aria-hidden="true" />
                            Kelola
                        </Link>
                    </Button>
                    <Button
                        v-if="event.status === 'published'"
                        variant="ghost"
                        size="sm"
                        :class="['app-focus-ring', ACTION_BUTTON_CLASS]"
                        as-child
                    >
                        <Link
                            :href="routes.admin.events.registrants(event.id)"
                            data-testid="recent-event-action-registrants"
                            class="text-xs"
                        >
                            <UsersRound aria-hidden="true" />
                            Pendaftar
                        </Link>
                    </Button>
                </div>
            </li>
        </ul>

        <div v-else class="flex flex-col items-center gap-1.5 px-4 py-10 text-center">
            <CalendarX2 class="text-muted-foreground size-8 shrink-0" aria-hidden="true" />
            <p class="text-foreground text-sm font-semibold">Belum ada acara di workspace ini</p>
            <p class="text-muted-foreground text-xs leading-snug">Acara yang dibuat akan muncul di sini.</p>
            <Button
                v-if="createEventHref"
                variant="outline"
                size="sm"
                class="app-focus-ring mt-2 h-[44px] gap-1 text-xs sm:h-8"
                as-child
            >
                <Link :href="createEventHref">Buat acara</Link>
            </Button>
        </div>
    </Card>
</template>
