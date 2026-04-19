<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { ChevronLeft, ChevronRight, MapPin, CalendarDays, ArrowRight } from 'lucide-vue-next'
import { dummyEvents, categoryColorMap, categoryLabelMap, formatDate } from '@/lib/dummyData'
import { toCategoryList, primaryCategory } from '@/lib/eventCategories'

const today = new Date()
const currentMonth = ref(today.getMonth())
const currentYear = ref(today.getFullYear())
const filterCategory = ref('all')
const viewMode = ref<'month' | 'week'>('month')
const currentWeekStart = ref(getWeekStart(today))

const selectedEvent = ref<(typeof dummyEvents)[0] | null>(null)
const showEventDialog = ref(false)

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function getWeekStart(date: Date): Date {
    const d = new Date(date)
    d.setDate(d.getDate() - d.getDay())
    d.setHours(0, 0, 0, 0)
    return d
}

function prevMonth() {
    if (currentMonth.value === 0) { currentMonth.value = 11; currentYear.value-- }
    else { currentMonth.value-- }
}
function nextMonth() {
    if (currentMonth.value === 11) { currentMonth.value = 0; currentYear.value++ }
    else { currentMonth.value++ }
}
function prevWeek() {
    const d = new Date(currentWeekStart.value)
    d.setDate(d.getDate() - 7)
    currentWeekStart.value = d
}
function nextWeek() {
    const d = new Date(currentWeekStart.value)
    d.setDate(d.getDate() + 7)
    currentWeekStart.value = d
}
function goToday() {
    currentMonth.value = today.getMonth()
    currentYear.value = today.getFullYear()
    currentWeekStart.value = getWeekStart(today)
}

function onEventClick(event: (typeof dummyEvents)[0]) {
    selectedEvent.value = event
    showEventDialog.value = true
}

function toDateStr(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const filteredEvents = computed(() => {
    let events = dummyEvents.filter((e) => !e.deleted_at)
    if (filterCategory.value !== 'all') events = events.filter((e) => toCategoryList(e.category).includes(filterCategory.value))
    return events
})

const calendarWeeks = computed(() => {
    const firstDay = new Date(currentYear.value, currentMonth.value, 1).getDay()
    const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate()
    const prevMonthDays = new Date(currentYear.value, currentMonth.value, 0).getDate()

    const cells: { day: number; date: Date; isCurrentMonth: boolean; isToday: boolean; events: typeof dummyEvents }[] = []

    for (let i = firstDay - 1; i >= 0; i--) {
        const d = prevMonthDays - i
        const m = currentMonth.value === 0 ? 11 : currentMonth.value - 1
        const y = currentMonth.value === 0 ? currentYear.value - 1 : currentYear.value
        const date = new Date(y, m, d)
        const dateStr = toDateStr(date)
        cells.push({ day: d, date, isCurrentMonth: false, isToday: false, events: filteredEvents.value.filter((e) => dateStr >= e.start_date && dateStr <= e.end_date) })
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(currentYear.value, currentMonth.value, d)
        const dateStr = toDateStr(date)
        const isToday = d === today.getDate() && currentMonth.value === today.getMonth() && currentYear.value === today.getFullYear()
        cells.push({ day: d, date, isCurrentMonth: true, isToday, events: filteredEvents.value.filter((e) => dateStr >= e.start_date && dateStr <= e.end_date) })
    }
    const remaining = (7 - (cells.length % 7)) % 7
    for (let i = 1; i <= remaining; i++) {
        const date = new Date(currentYear.value, currentMonth.value + 1, i)
        cells.push({ day: i, date, isCurrentMonth: false, isToday: false, events: [] })
    }

    const weeks: (typeof cells)[] = []
    for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7))
    return weeks
})

const weekDays = computed(() => {
    const start = currentWeekStart.value
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(start)
        d.setDate(d.getDate() + i)
        const dateStr = toDateStr(d)
        const isToday = d.toDateString() === today.toDateString()
        return {
            day: d.getDate(),
            dayName: dayNames[i],
            date: d,
            isToday,
            events: filteredEvents.value.filter((e) => dateStr >= e.start_date && dateStr <= e.end_date),
        }
    })
})

const weekLabel = computed(() => {
    const start = weekDays.value[0].date
    const end = weekDays.value[6].date
    return `${monthNames[start.getMonth()]} ${start.getDate()} – ${monthNames[end.getMonth()]} ${end.getDate()}, ${end.getFullYear()}`
})
</script>

<template>
    <Card class="rounded-xl border shadow-xs">
        <CardHeader class="pb-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <CardTitle class="text-base font-medium">
                        <template v-if="viewMode === 'month'">{{ monthNames[currentMonth] }} {{ currentYear }}</template>
                        <template v-else>{{ weekLabel }}</template>
                    </CardTitle>
                    <div class="flex items-center gap-0.5">
                        <Button variant="ghost" size="icon" class="size-7" @click="viewMode === 'month' ? prevMonth() : prevWeek()"><ChevronLeft class="size-4" /></Button>
                        <Button variant="ghost" size="icon" class="size-7" @click="viewMode === 'month' ? nextMonth() : nextWeek()"><ChevronRight class="size-4" /></Button>
                    </div>
                    <Button variant="outline" size="sm" class="h-7 text-xs" @click="goToday">Today</Button>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex h-8 items-center overflow-hidden rounded-lg border bg-muted/40">
                        <button :class="['px-3 py-1 text-xs font-medium transition-colors', viewMode === 'month' ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground']" @click="viewMode = 'month'">Month</button>
                        <button :class="['px-3 py-1 text-xs font-medium transition-colors', viewMode === 'week' ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground']" @click="viewMode = 'week'">Week</button>
                    </div>
                    <Select v-model="filterCategory">
                        <SelectTrigger class="h-8 w-36 text-xs"><SelectValue placeholder="All Categories" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Categories</SelectItem>
                            <SelectItem value="rkt">RKT</SelectItem>
                            <SelectItem value="non-rkt">NON RKT</SelectItem>
                            <SelectItem value="recruitment">Recruitment</SelectItem>
                            <SelectItem value="etc">Etc</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </CardHeader>
        <CardContent class="pt-0">
            <!-- Month View -->
            <template v-if="viewMode === 'month'">
                <div class="grid grid-cols-7 border-b">
                    <div v-for="day in dayNames" :key="day" class="py-2 text-center text-xs font-medium text-muted-foreground">{{ day }}</div>
                </div>
                <div class="divide-y">
                    <div v-for="(week, wIdx) in calendarWeeks" :key="wIdx" class="grid grid-cols-7 divide-x">
                        <div v-for="(cell, dIdx) in week" :key="dIdx" :class="['min-h-20 p-1.5', !cell.isCurrentMonth ? 'bg-muted/20' : '']">
                            <span :class="['mb-1 inline-flex size-6 items-center justify-center rounded-md text-xs', cell.isToday ? 'bg-primary font-semibold text-primary-foreground' : cell.isCurrentMonth ? 'text-foreground' : 'text-muted-foreground/30']">
                                {{ cell.day }}
                            </span>
                            <div class="flex flex-col gap-0.5">
                                <button
                                    v-for="ev in cell.events.slice(0, 2)"
                                    :key="ev.id"
                                    class="w-full truncate rounded-md px-1.5 py-0.5 text-left text-[10px] font-medium text-white transition-opacity hover:opacity-80"
                                    :style="{ backgroundColor: categoryColorMap[primaryCategory(ev.category)] ?? '#6B7280' }"
                                    @click="onEventClick(ev)"
                                >
                                    {{ ev.title }}
                                </button>
                                <span v-if="cell.events.length > 2" class="text-[10px] text-muted-foreground">+{{ cell.events.length - 2 }} more</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Week View -->
            <template v-else>
                <div class="grid grid-cols-7 divide-x border-y">
                    <div v-for="day in weekDays" :key="day.dayName" class="min-h-40 p-2">
                        <div class="mb-2 text-center">
                            <div class="text-[10px] font-medium uppercase text-muted-foreground">{{ day.dayName }}</div>
                            <span :class="['inline-flex size-7 items-center justify-center rounded-md text-sm', day.isToday ? 'bg-primary font-semibold text-primary-foreground' : '']">
                                {{ day.day }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <button
                                v-for="ev in day.events"
                                :key="ev.id"
                                class="w-full truncate rounded-md px-2 py-1 text-left text-[10px] font-medium text-white transition-opacity hover:opacity-80"
                                :style="{ backgroundColor: categoryColorMap[primaryCategory(ev.category)] ?? '#6B7280' }"
                                @click="onEventClick(ev)"
                            >
                                {{ ev.title }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div class="mt-3 flex flex-wrap gap-4">
                <div v-for="(color, cat) in categoryColorMap" :key="cat" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span class="size-2 rounded-full" :style="{ backgroundColor: color }" />
                    {{ categoryLabelMap[cat] ?? cat }}
                </div>
            </div>
        </CardContent>
    </Card>

    <Dialog :open="showEventDialog" @update:open="showEventDialog = $event">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ selectedEvent?.title }}</DialogTitle>
                <DialogDescription>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <Badge
                            v-for="cat in toCategoryList(selectedEvent?.category)"
                            :key="cat"
                            class="text-[10px] text-white"
                            :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                        >
                            {{ categoryLabelMap[cat] ?? cat }}
                        </Badge>
                    </div>
                </DialogDescription>
            </DialogHeader>
            <div v-if="selectedEvent" class="flex flex-col gap-3 pt-1">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <CalendarDays class="size-4 shrink-0" />
                    <span>{{ formatDate(selectedEvent.start_date) }} — {{ formatDate(selectedEvent.end_date) }}</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <MapPin class="size-4 shrink-0" />
                    <span>{{ selectedEvent.location }}</span>
                </div>
                <Button variant="outline" size="sm" class="mt-1 w-full" as-child>
                    <Link :href="`/dashboard/events/${selectedEvent.id}`">View Details<ArrowRight class="ml-1.5 size-3.5" /></Link>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
