<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import DatePicker from '@/components/ui/date-picker/DatePicker.vue'
import TimeAmPmInput from '@/components/ui/date-picker/TimeAmPmInput.vue'
import { Label } from '@/components/ui/label'

/**
 * Satu field tanggal + jam yang nilai keluar/masuknya tetap
 * `YYYY-MM-DDTHH:mm` (satu string utuh). Bagian tanggal & jam dipisah
 * hanya untuk interaksi, lalu digabung kembali saat berubah.
 */
const props = withDefaults(
    defineProps<{
        /** Nilai utuh `YYYY-MM-DDTHH:mm`. */
        modelValue: string
        label: string
        idPrefix: string
        /** True saat field punya error validasi. */
        invalid?: boolean
        error?: string
        /** True untuk menampilkan asterisk wajib di samping label. */
        required?: boolean
        /** True saat field bergoyang (shake) karena error validasi. */
        shaking?: boolean
        /** Class tambahan untuk tombol pilih tanggal (mis. warna permukaan sesuai konteks). */
        pickerClass?: string
        /**
         * `col` → tanggal di atas, jam di bawah (hemat lebar, cocok panel sempit).
         * `row` → tanggal kiri melebar, jam kanan (compact di form lebar).
         */
        layout?: 'col' | 'row'
    }>(),
    { invalid: false, error: '', required: false, shaking: false, pickerClass: '', layout: 'row' },
)

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

function splitDateTimeParts(value: string): { date: string; time: string } {
    if (!value) return { date: '', time: '' }
    const [d, t = ''] = value.split('T')
    return { date: d.length >= 10 ? d.slice(0, 10) : '', time: t.length >= 5 ? t.slice(0, 5) : '' }
}

function combineDateTime(date: string, time: string): string {
    if (!date) return ''
    const t = time && time.length >= 5 ? time.slice(0, 5) : '00:00'
    return `${date}T${t}`
}

const parts = ref(splitDateTimeParts(props.modelValue))

watch(
    parts,
    () => {
        emit('update:modelValue', combineDateTime(parts.value.date, parts.value.time))
    },
    { deep: true },
)

watch(
    () => props.modelValue,
    (v) => {
        const next = splitDateTimeParts(v)
        if (next.date !== parts.value.date || next.time !== parts.value.time) {
            parts.value = next
        }
    },
)

const dateId = computed(() => `${props.idPrefix}-date`)
const timeId = computed(() => `${props.idPrefix}-time`)
</script>

<template>
    <div class="flex flex-col gap-2">
        <Label :for="dateId" class="text-foreground text-sm font-medium">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>
        <div
            :class="[
                layout === 'row' ? 'grid items-center gap-3 sm:grid-cols-[1fr_auto]' : 'flex flex-col gap-2',
                shaking ? 'animate-shake' : '',
            ]"
        >
            <DatePicker
                :id="dateId"
                :model-value="parts.date"
                placeholder="Pilih tanggal"
                :aria-invalid="invalid"
                :class="[
                    'h-9 text-sm',
                    invalid
                        ? 'border-destructive/70 bg-red-50 focus-visible:border-destructive focus-visible:ring-destructive/20 dark:bg-red-500/10 dark:focus-visible:border-destructive/70'
                        : '',
                    pickerClass,
                ]"
                @update:model-value="parts.date = $event"
            />
            <TimeAmPmInput
                :id="timeId"
                :model-value="parts.time"
                :aria-invalid="invalid"
                class="h-9 w-full"
                @update:model-value="parts.time = String($event)"
            />
        </div>
        <p v-if="error" class="text-destructive text-xs">{{ error }}</p>
    </div>
</template>
