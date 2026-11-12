<script setup lang="ts">
import type { DateValue } from 'reka-ui'
import { CalendarDate } from '@internationalized/date'
import { ref, shallowRef, watch } from 'vue'
import { Calendar as CalendarIcon } from 'lucide-vue-next'
import { Calendar } from '@/components/ui/calendar'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import {
    combineLocalDateTime,
    formatIdDateTimeLabel,
    splitLocalDateTime,
} from '@/lib/shadcnDateFormat'

const props = withDefaults(
    defineProps<{
        id?: string
        modelValue: string
        placeholder?: string
        disabled?: boolean
        class?: string
    }>(),
    { placeholder: 'Pilih tanggal & jam', disabled: false },
)

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const open = ref(false)
// shallowRef: DateValue punya private field; ref() akan mem-flatten tipenya.
const selectedDate = shallowRef<DateValue | undefined>(undefined)
const timeStr = ref('00:00')

function syncFromModel(): void {
    const { date, time } = splitLocalDateTime(props.modelValue)
    selectedDate.value = date
    timeStr.value = time
}

watch(() => props.modelValue, syncFromModel, { immediate: true })

function emitCombined(): void {
    // DateValue (CalendarDate/CalendarDateTime/ZonedDateTime) semuanya punya year/month/day.
    const date = selectedDate.value instanceof CalendarDate ? selectedDate.value : undefined
    emit('update:modelValue', combineLocalDateTime(date, timeStr.value))
}

function onCalendarUpdate(v: DateValue | undefined): void {
    selectedDate.value = v
    emitCombined()
}

function onTimeChange(): void {
    emitCombined()
}

const label = (): string => formatIdDateTimeLabel(props.modelValue)
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                :disabled="disabled"
                :class="
 cn(
 'h-9 w-full justify-start gap-2 px-3 text-left text-xs font-normal shadow-none',
 !modelValue && 'text-muted-foreground',
 props.class,
 )
 "
            >
                <CalendarIcon class="size-4 shrink-0 opacity-60" aria-hidden="true" />
                <span class="truncate">{{ label() || placeholder }}</span>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto max-w-[min(100vw-2rem,20rem)] p-0" align="start">
            <div class="flex flex-col gap-3 p-3">
                <Calendar
                    layout="month-and-year"
                    locale="id-ID"
                    :model-value="selectedDate"
                    initial-focus
                    @update:model-value="onCalendarUpdate"
                />
                <div class="flex flex-col gap-1.5 border-t border-border pt-3">
                    <Label class="text-[11px] font-medium text-muted-foreground">Jam (lokal)</Label>
                    <Input
                        type="time"
                        step="60"
                        class="h-9 text-xs"
                        :model-value="timeStr"
                        @update:model-value="
                            (v: string | number) => {
                                timeStr = String(v).slice(0, 5)
                                onTimeChange()
                            }
                        "
                    />
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
