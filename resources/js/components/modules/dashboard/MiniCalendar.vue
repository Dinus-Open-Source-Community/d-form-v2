<script setup lang="ts">
import { ref, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { dummyEvents, categoryColorMap } from '@/lib/dummyData';
import { primaryCategory } from '@/lib/eventCategories';

const today = new Date();
const currentMonth = ref(today.getMonth());
const currentYear = ref(today.getFullYear());

const dayNamesShort = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

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

function goThisMonth() {
    currentMonth.value = today.getMonth();
    currentYear.value = today.getFullYear();
}

const calendarDays = computed(() => {
    const firstDay = new Date(currentYear.value, currentMonth.value, 1).getDay();
    const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
    const days: { day: number; isCurrentMonth: boolean; isToday: boolean; events: typeof dummyEvents }[] = [];

    const prevMonthDays = new Date(currentYear.value, currentMonth.value, 0).getDate();
    for (let i = firstDay - 1; i >= 0; i--) {
        days.push({ day: prevMonthDays - i, isCurrentMonth: false, isToday: false, events: [] });
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const isToday =
            d === today.getDate() &&
            currentMonth.value === today.getMonth() &&
            currentYear.value === today.getFullYear();
        const dayEvents = dummyEvents.filter(
            (e) => dateStr >= e.start_date && dateStr <= e.end_date && !e.deleted_at,
        );
        days.push({ day: d, isCurrentMonth: true, isToday, events: dayEvents });
    }
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        days.push({ day: i, isCurrentMonth: false, isToday: false, events: [] });
    }
    return days;
});

const isViewingCurrentMonth = computed(
    () => currentMonth.value === today.getMonth() && currentYear.value === today.getFullYear(),
);
</script>

<template>
    <Card
        class="overflow-hidden rounded-2xl border-border/70 shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.06]"
    >
        <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-3 border-b border-border/50 bg-muted/10 px-4 py-3.5 sm:px-5">
            <CardTitle class="font-display min-w-0 text-base font-bold tracking-[-0.02em] sm:text-lg">
                {{ monthNamesId[currentMonth] }}
                <span class="text-muted-foreground font-semibold">{{ currentYear }}</span>
            </CardTitle>
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    class="mr-1 h-8 rounded-lg px-2.5 text-xs"
                    :disabled="isViewingCurrentMonth"
                    @click="goThisMonth"
                >
                    Sekarang
                </Button>
                <Button variant="ghost" size="icon-sm" class="rounded-lg" aria-label="Bulan sebelumnya" @click="prevMonth">
                    <ChevronLeft class="size-4" :stroke-width="2" />
                </Button>
                <Button variant="ghost" size="icon-sm" class="rounded-lg" aria-label="Bulan berikutnya" @click="nextMonth">
                    <ChevronRight class="size-4" :stroke-width="2" />
                </Button>
            </div>
        </CardHeader>
        <CardContent class="p-3 sm:p-4">
            <div class="overflow-hidden rounded-xl border border-border/70 bg-gradient-to-b from-card to-muted/15 shadow-inner">
                <div class="grid grid-cols-7 gap-px bg-border/40">
                    <div
                        v-for="day in dayNamesShort"
                        :key="day"
                        class="bg-muted/50 py-2 text-center text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground"
                    >
                        {{ day }}
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-px bg-border/30 p-px">
                    <div
                        v-for="(cell, idx) in calendarDays"
                        :key="idx"
                        class="relative flex aspect-square max-h-[2.75rem] items-center justify-center bg-card sm:max-h-12"
                    >
                        <span
                            :title="cell.events.length ? `${cell.events.length} acara` : undefined"
                            :class="[
                                'flex size-8 items-center justify-center rounded-lg text-xs font-semibold transition-colors',
                                cell.isCurrentMonth ? 'text-foreground' : 'text-muted-foreground/30',
                                cell.isToday
                                    ? 'bg-primary text-primary-foreground shadow-md ring-2 ring-primary/20'
                                    : 'hover:bg-muted/70',
                            ]"
                        >
                            {{ cell.day }}
                        </span>
                        <div v-if="cell.events.length > 0" class="absolute bottom-1 flex justify-center gap-0.5">
                            <span
                                v-for="ev in cell.events.slice(0, 3)"
                                :key="ev.id"
                                class="size-1 rounded-full shadow-sm ring-1 ring-background/80"
                                :style="{
                                    backgroundColor: categoryColorMap[primaryCategory(ev.category)] ?? 'var(--muted-foreground)',
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
