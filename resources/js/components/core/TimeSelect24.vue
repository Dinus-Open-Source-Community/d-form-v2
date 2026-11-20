<script setup lang="ts">
import { computed } from 'vue';
import { clampTimePart } from '@/lib/timeOfDayInput';

/**
 * Pemilih waktu 24 jam dengan dua kolom isian angka (jam dan menit).
 * Nilai disimpan sebagai `HH:MM`; nilai kosong berarti peserta belum memilih.
 */
const modelValue = defineModel<string>({ required: true });

const parts = computed<{ hour: string; minute: string }>(() => {
    const [hour = '', minute = ''] = modelValue.value.split(':');

    return { hour, minute };
});

/**
 * Tulis satu bagian dari DOM. Nilai di luar rentang dijepit langsung, jadi kolom
 * tidak pernah menampilkan angka yang tidak sah (`00` sampai `23` dan `00` sampai `59`).
 */
function applyPart(part: 'hour' | 'minute', input: HTMLInputElement, finishTyping: boolean): void {
    const digits = clampTimePart(part, input.value, finishTyping);
    input.value = digits;

    const next = { ...parts.value, [part]: digits };

    modelValue.value =
        next.hour === '' || next.minute === '' ? '' : `${next.hour.padStart(2, '0')}:${next.minute.padStart(2, '0')}`;
}
</script>

<template>
    <div class="flex items-center gap-2">
        <input
            :value="parts.hour"
            type="text"
            inputmode="numeric"
            maxlength="2"
            placeholder="JJ"
            aria-label="Jam"
            class="border-input bg-background text-foreground placeholder:text-muted-foreground/70 focus-visible:ring-ring/30 h-11 w-16 rounded-lg border text-center text-sm tabular-nums shadow-xs outline-none focus-visible:ring-[3px]"
            @input="applyPart('hour', $event.target as HTMLInputElement, false)"
            @blur="applyPart('hour', $event.target as HTMLInputElement, true)"
        />

        <span class="text-muted-foreground text-sm">:</span>

        <input
            :value="parts.minute"
            type="text"
            inputmode="numeric"
            maxlength="2"
            placeholder="MM"
            aria-label="Menit"
            class="border-input bg-background text-foreground placeholder:text-muted-foreground/70 focus-visible:ring-ring/30 h-11 w-16 rounded-lg border text-center text-sm tabular-nums shadow-xs outline-none focus-visible:ring-[3px]"
            @input="applyPart('minute', $event.target as HTMLInputElement, false)"
            @blur="applyPart('minute', $event.target as HTMLInputElement, true)"
        />
    </div>
</template>
