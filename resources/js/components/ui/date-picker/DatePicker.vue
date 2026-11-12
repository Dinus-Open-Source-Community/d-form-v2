<script setup lang="ts">
import type { DateValue } from 'reka-ui'
import { ref, shallowRef, watch } from 'vue'
import { Calendar as CalendarIcon } from 'lucide-vue-next'
import { Calendar } from '@/components/ui/calendar'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import { calendarDateToYmd, formatIdDateLabel, modelValueToCalendarDate } from '@/lib/shadcnDateFormat'

const props = withDefaults(
    defineProps<{
        id?: string
        modelValue: string
        placeholder?: string
        disabled?: boolean
        /** True saat field punya error validasi: border/ring destructive (lewat trigger outline). */
        ariaInvalid?: boolean
        /** Trigger + calendar width */
        class?: string
    }>(),
    { placeholder: 'Pilih tanggal', disabled: false, ariaInvalid: false },
)

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const open = ref(false)
// shallowRef: DateValue punya private field; ref() akan mem-flatten tipenya.
const selected = shallowRef<DateValue | undefined>(undefined)

watch(
    () => props.modelValue,
    (v) => {
        selected.value = modelValueToCalendarDate(v)
    },
    { immediate: true },
)

function onCalendarUpdate(v: DateValue | undefined): void {
    selected.value = v
    emit('update:modelValue', calendarDateToYmd(v))
    open.value = false
}

const label = (): string => formatIdDateLabel(props.modelValue)
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                :disabled="disabled"
                :aria-invalid="ariaInvalid === true ? true : undefined"
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
        <PopoverContent class="w-auto p-0" align="start">
            <Calendar
                layout="month-and-year"
                locale="id-ID"
                :model-value="selected"
                initial-focus
                @update:model-value="onCalendarUpdate"
            />
        </PopoverContent>
    </Popover>
</template>
