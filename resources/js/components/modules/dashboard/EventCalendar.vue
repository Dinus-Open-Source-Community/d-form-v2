<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ChevronLeft, ChevronRight, MapPin, CalendarDays, ArrowRight, Filter } from 'lucide-vue-next';
import { dummyEvents, categoryColorMap, categoryLabelMap, formatDate } from '@/lib/dummyData';
import { toCategoryList, primaryCategory } from '@/lib/eventCategories';

const today = new Date();
const currentMonth = ref(today.getMonth());
const currentYear = ref(today.getFullYear());
const filterCategory = ref('all');
const viewMode = ref<'month' | 'week'>('month');
const currentWeekStart = ref(getWeekStart(today));

const selectedEvent = ref<(typeof dummyEvents)[0] | null>(null);
const showEventDialog = ref(false);

const monthNamesId = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];
/** Kolom dimulai Minggu — sama dengan Date.getDay(). */
const dayNamesShort = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

function getWeekStart(date: Date): Date {
    const d = new Date(date);
    d.setDate(d.getDate() - d.getDay());
    d.setHours(0, 0, 0, 0);
    return d;
}

function prevMonth() {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
}
function nextMonth() {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
}
function prevWeek() {
    const d = new Date(currentWeekStart.value);
    d.setDate(d.getDate() - 7);
    currentWeekStart.value = d;
}
function nextWeek() {
    const d = new Date(currentWeekStart.value);
    d.setDate(d.getDate() + 7);
    currentWeekStart.value = d;
}
function goToday() {
    currentMonth.value = today.getMonth();
    currentYear.value = today.getFullYear();
    currentWeekStart.value = getWeekStart(today);
}

function onEventClick(event: (typeof dummyEvents)[0]) {
    selectedEvent.value = event;
    showEventDialog.value = true;
}

function toDateStr(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const filteredEvents = computed(() => {
    let events = dummyEvents.filter((e) => !e.deleted_at);
    if (filterCategory.value !== 'all')
        events = events.filter((e) => toCategoryList(e.category).includes(filterCategory.value));
    return events;
});

const calendarWeeks = computed(() => {
    const firstDay = new Date(currentYear.value, currentMonth.value, 1).getDay();
    const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
    const prevMonthDays = new Date(currentYear.value, currentMonth.value, 0).getDate();

    const cells: { day: number; date: Date; isCurrentMonth: boolean; isToday: boolean; events: typeof dummyEvents }[] =
        [];

    for (let i = firstDay - 1; i >= 0; i--) {
        const d = prevMonthDays - i;
        const m = currentMonth.value === 0 ? 11 : currentMonth.value - 1;
        const y = currentMonth.value === 0 ? currentYear.value - 1 : currentYear.value;
        const date = new Date(y, m, d);
        const dateStr = toDateStr(date);
        cells.push({
            day: d,
            date,
            isCurrentMonth: false,
            isToday: false,
            events: filteredEvents.value.filter((e) => dateStr >= e.start_date && dateStr <= e.end_date),
        });
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(currentYear.value, currentMonth.value, d);
        const dateStr = toDateStr(date);
        const isToday =
            d === today.getDate() &&
            currentMonth.value === today.getMonth() &&
            currentYear.value === today.getFullYear();
        cells.push({
            day: d,
            date,
            isCurrentMonth: true,
            isToday,
            events: filteredEvents.value.filter((e) => dateStr >= e.start_date && dateStr <= e.end_date),
        });
    }
    const remaining = (7 - (cells.length % 7)) % 7;
    for (let i = 1; i <= remaining; i++) {
        const date = new Date(currentYear.value, currentMonth.value + 1, i);
        cells.push({ day: i, date, isCurrentMonth: false, isToday: false, events: [] });
    }

    const weeks: (typeof cells)[] = [];
    for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));
    return weeks;
});

const weekDays = computed(() => {
    const start = currentWeekStart.value;
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(start);
        d.setDate(d.getDate() + i);
        const dateStr = toDateStr(d);
        const isToday = d.toDateString() === today.toDateString();
        return {
            day: d.getDate(),
            dayName: dayNamesShort[i],
            date: d,
            isToday,
            events: filteredEvents.value.filter((e) => dateStr >= e.start_date && dateStr <= e.end_date),
        };
    });
});

const weekLabel = computed(() => {
    const start = weekDays.value[0].date;
    const end = weekDays.value[6].date;
    return `${start.getDate()} ${monthNamesId[start.getMonth()]} – ${end.getDate()} ${monthNamesId[end.getMonth()]} ${end.getFullYear()}`;
});

const legendEntries = computed(() =>
    Object.entries(categoryColorMap).map(([token, color]) => ({
        token,
        color,
        label: categoryLabelMap[token] ?? token,
    })),
);
</script>

<template>
    <Card class="rounded-2xl border-border/70 shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.06]">
        <CardHeader class="space-y-0 border-b border-border/50 bg-muted/10 p-0">
            <div class="flex flex-col gap-3 px-4 py-4 sm:px-5 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    <CardTitle class="font-display text-lg font-bold tracking-[-0.02em] sm:text-xl">
                        Kalender acara
                    </CardTitle>
                    <span class="bg-border/80 hidden h-4 w-px shrink-0 lg:inline-block" aria-hidden="true" />
                    <p class="font-display text-muted-foreground text-base font-semibold tracking-tight">
                        <template v-if="viewMode === 'month'">
                            {{ monthNamesId[currentMonth] }} {{ currentYear }}
                        </template>
                        <template v-else>
                            {{ weekLabel }}
                        </template>
                    </p>
                    <div class="border-border/70 flex items-center rounded-lg border bg-background/80 p-0.5 shadow-xs">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="size-8 rounded-md"
                            :aria-label="viewMode === 'month' ? 'Bulan sebelumnya' : 'Minggu sebelumnya'"
                            @click="viewMode === 'month' ? prevMonth() : prevWeek()"
                        >
                            <ChevronLeft class="size-4" :stroke-width="2" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="size-8 rounded-md"
                            :aria-label="viewMode === 'month' ? 'Bulan berikutnya' : 'Minggu berikutnya'"
                            @click="viewMode === 'month' ? nextMonth() : nextWeek()"
                        >
                            <ChevronRight class="size-4" :stroke-width="2" />
                        </Button>
                    </div>
                    <Button variant="outline" size="sm" class="h-8 rounded-lg text-xs" @click="goToday">Hari ini</Button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div
                        class="border-border/70 inline-flex items-center gap-0.5 rounded-xl border bg-background/80 p-0.5 shadow-inner"
                        role="group"
                        aria-label="Mode tampilan"
                    >
                        <button
                            type="button"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200',
                                viewMode === 'month'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="viewMode = 'month'"
                        >
                            Bulan
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200',
                                viewMode === 'week'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="viewMode = 'week'"
                        >
                            Minggu
                        </button>
                    </div>

                    <Select v-model="filterCategory">
                        <SelectTrigger class="h-8 w-[10.5rem] rounded-lg text-xs">
                            <span class="flex min-w-0 flex-1 items-center gap-2">
                                <Filter class="text-muted-foreground size-3.5 shrink-0" aria-hidden="true" />
                                <SelectValue placeholder="Kategori" />
                            </span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua kategori</SelectItem>
                            <SelectItem value="rkt">RKT</SelectItem>
                            <SelectItem value="non-rkt">Non RKT</SelectItem>
                            <SelectItem value="recruitment">Recruitment</SelectItem>
                            <SelectItem value="etc">Lainnya</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 p-4 sm:p-5">
            <template v-if="viewMode === 'month'">
                <div class="overflow-hidden rounded-xl border border-border/70 bg-gradient-to-b from-muted/20 to-card shadow-inner">
                    <div class="grid grid-cols-7 gap-px bg-border/45">
                        <div
                            v-for="day in dayNamesShort"
                            :key="day"
                            class="bg-muted/50 py-2.5 text-center text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground"
                        >
                            {{ day }}
                        </div>
                    </div>
                    <div class="divide-y divide-border/50 bg-card">
                        <div v-for="(week, wIdx) in calendarWeeks" :key="wIdx" class="grid grid-cols-7 divide-x divide-border/50">
                            <div
                                v-for="(cell, dIdx) in week"
                                :key="dIdx"
                                :class="[
                                    'min-h-[5.5rem] p-1.5 transition-colors sm:min-h-24 sm:p-2',
                                    !cell.isCurrentMonth ? 'bg-muted/25' : 'hover:bg-muted/20',
                                ]"
                            >
                                <span
                                    :class="[
                                        'mb-1 inline-flex size-7 items-center justify-center rounded-lg text-xs font-semibold transition-colors',
                                        cell.isToday
                                            ? 'bg-primary text-primary-foreground shadow-md ring-2 ring-primary/30'
                                            : cell.isCurrentMonth
                                              ? 'text-foreground'
                                              : 'text-muted-foreground/40',
                                    ]"
                                >
                                    {{ cell.day }}
                                </span>
                                <div class="flex flex-col gap-1">
                                    <button
                                        v-for="ev in cell.events.slice(0, 2)"
                                        :key="ev.id"
                                        type="button"
                                        class="w-full truncate rounded-md border border-white/10 px-1.5 py-0.5 text-left text-[10px] font-semibold leading-tight text-white shadow-sm transition-opacity hover:opacity-90"
                                        :style="{
                                            backgroundColor:
                                                categoryColorMap[primaryCategory(ev.category)] ?? 'var(--muted-foreground)',
                                        }"
                                        @click="onEventClick(ev)"
                                    >
                                        {{ ev.title }}
                                    </button>
                                    <span
                                        v-if="cell.events.length > 2"
                                        class="text-[10px] font-medium text-muted-foreground"
                                    >
                                        +{{ cell.events.length - 2 }} lainnya
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="overflow-hidden rounded-xl border border-border/70 bg-card shadow-inner">
                    <div class="grid grid-cols-7 divide-x divide-border/50">
                        <div v-for="day in weekDays" :key="day.dayName" class="flex min-h-44 flex-col p-2 sm:min-h-48 sm:p-3">
                            <div class="mb-2 text-center">
                                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted-foreground">
                                    {{ day.dayName }}
                                </div>
                                <span
                                    :class="[
                                        'mt-1 inline-flex size-8 items-center justify-center rounded-lg text-sm font-semibold',
                                        day.isToday
                                            ? 'bg-primary text-primary-foreground shadow-md ring-2 ring-primary/25'
                                            : 'text-foreground',
                                    ]"
                                >
                                    {{ day.day }}
                                </span>
                            </div>
                            <div class="flex flex-1 flex-col gap-1 overflow-y-auto">
                                <button
                                    v-for="ev in day.events"
                                    :key="ev.id"
                                    type="button"
                                    class="w-full truncate rounded-md border border-white/10 px-2 py-1 text-left text-[10px] font-semibold text-white shadow-sm transition-opacity hover:opacity-90"
                                    :style="{
                                        backgroundColor:
                                            categoryColorMap[primaryCategory(ev.category)] ?? 'var(--muted-foreground)',
                                    }"
                                    @click="onEventClick(ev)"
                                >
                                    {{ ev.title }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div class="flex flex-wrap justify-center gap-2 border-t border-border/40 pt-4 sm:justify-start">
                <div
                    v-for="item in legendEntries"
                    :key="item.token"
                    class="flex items-center gap-1.5 rounded-full border border-border/50 bg-muted/20 px-2.5 py-1 text-xs"
                >
                    <span class="size-2.5 shrink-0 rounded-full shadow-sm" :style="{ backgroundColor: item.color }" />
                    <span class="font-medium text-foreground">{{ item.label }}</span>
                </div>
            </div>
        </CardContent>
    </Card>

    <Dialog :open="showEventDialog" @update:open="showEventDialog = $event">
        <DialogContent class="max-w-sm rounded-2xl">
            <DialogHeader>
                <DialogTitle class="font-display text-lg">{{ selectedEvent?.title }}</DialogTitle>
                <p class="sr-only">Kategori acara ditampilkan di bawah.</p>
                <div class="mt-2 flex flex-wrap gap-1" role="group" aria-label="Kategori">
                    <Badge
                        v-for="cat in toCategoryList(selectedEvent?.category)"
                        :key="cat"
                        class="text-[10px] text-white"
                        :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                    >
                        {{ categoryLabelMap[cat] ?? cat }}
                    </Badge>
                </div>
            </DialogHeader>
            <div v-if="selectedEvent" class="flex flex-col gap-3 pt-1">
                <div class="text-muted-foreground flex items-start gap-2 text-sm">
                    <CalendarDays class="mt-0.5 size-4 shrink-0 text-primary" />
                    <span>
                        {{ formatDate(selectedEvent.start_date) }} — {{ formatDate(selectedEvent.end_date) }}
                    </span>
                </div>
                <div class="text-muted-foreground flex items-start gap-2 text-sm">
                    <MapPin class="mt-0.5 size-4 shrink-0 text-primary" />
                    <span>{{ selectedEvent.location }}</span>
                </div>
                <Button variant="default" size="sm" class="mt-2 w-full rounded-xl" as-child>
                    <Link :href="`/admin/dashboard/events/${selectedEvent.id}`" class="inline-flex items-center justify-center gap-2">
                        Buka detail acara
                        <ArrowRight class="size-3.5" />
                    </Link>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
