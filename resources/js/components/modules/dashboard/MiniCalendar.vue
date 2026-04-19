<script setup lang="ts">
import { ref, computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { dummyEvents, categoryColorMap } from '@/lib/dummyData'
import { primaryCategory } from '@/lib/eventCategories'

const today = new Date()
const currentMonth = ref(today.getMonth())
const currentYear = ref(today.getFullYear())

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const dayNames = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']

function prevMonth() {
    if (currentMonth.value === 0) { currentMonth.value = 11; currentYear.value-- }
    else { currentMonth.value-- }
}
function nextMonth() {
    if (currentMonth.value === 11) { currentMonth.value = 0; currentYear.value++ }
    else { currentMonth.value++ }
}

const calendarDays = computed(() => {
    const firstDay = new Date(currentYear.value, currentMonth.value, 1).getDay()
    const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate()
    const days: { day: number; isCurrentMonth: boolean; isToday: boolean; events: typeof dummyEvents }[] = []

    const prevMonthDays = new Date(currentYear.value, currentMonth.value, 0).getDate()
    for (let i = firstDay - 1; i >= 0; i--) {
        days.push({ day: prevMonthDays - i, isCurrentMonth: false, isToday: false, events: [] })
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
        const isToday = d === today.getDate() && currentMonth.value === today.getMonth() && currentYear.value === today.getFullYear()
        const dayEvents = dummyEvents.filter((e) => dateStr >= e.start_date && dateStr <= e.end_date && !e.deleted_at)
        days.push({ day: d, isCurrentMonth: true, isToday, events: dayEvents })
    }
    const remaining = 42 - days.length
    for (let i = 1; i <= remaining; i++) {
        days.push({ day: i, isCurrentMonth: false, isToday: false, events: [] })
    }
    return days
})
</script>

<template>
    <Card class="rounded-xl border shadow-xs">
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <CardTitle class="text-base font-medium">
                    {{ monthNames[currentMonth] }} {{ currentYear }}
                </CardTitle>
                <div class="flex items-center gap-0.5">
                    <Button variant="ghost" size="icon" class="size-7" @click="prevMonth">
                        <ChevronLeft class="size-4" />
                    </Button>
                    <Button variant="ghost" size="icon" class="size-7" @click="nextMonth">
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </CardHeader>
        <CardContent class="pt-0">
            <div class="grid grid-cols-7 gap-0">
                <div v-for="day in dayNames" :key="day" class="py-2 text-center text-[11px] font-medium text-muted-foreground">
                    {{ day }}
                </div>
            </div>
            <div class="grid grid-cols-7 gap-0">
                <div v-for="(cell, idx) in calendarDays" :key="idx" class="relative flex h-9 items-center justify-center">
                    <span
                        :class="[
                            'flex size-7 items-center justify-center rounded-lg text-xs transition-colors',
                            cell.isCurrentMonth ? 'text-foreground' : 'text-muted-foreground/30',
                            cell.isToday ? 'bg-primary font-semibold text-primary-foreground' : 'hover:bg-muted/60',
                        ]"
                    >
                        {{ cell.day }}
                    </span>
                    <div v-if="cell.events.length > 0" class="absolute bottom-0.5 flex gap-0.5">
                        <span
                            v-for="ev in cell.events.slice(0, 3)"
                            :key="ev.id"
                            class="size-1 rounded-full"
                            :style="{ backgroundColor: categoryColorMap[primaryCategory(ev.category)] ?? 'var(--muted-foreground)' }"
                        />
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
