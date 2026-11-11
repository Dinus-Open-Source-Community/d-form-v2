<script setup lang="ts">
import { DatePicker, SplitDateTimeField } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { CalendarClock, CalendarRange } from 'lucide-vue-next';

const props = defineProps<{
    startDate: string;
    endDate: string;
    registrationStart: string;
    registrationEnd: string;
    startDateError?: string;
    endDateError?: string;
    registrationStartError?: string;
    registrationEndError?: string;
    startDateShaking: boolean;
    endDateShaking: boolean;
    registrationStartShaking: boolean;
    registrationEndShaking: boolean;
    startDateRequired: boolean;
    endDateRequired: boolean;
    startDateClass: string;
    endDateClass: string;
    registrationStartRequired: boolean;
    registrationEndRequired: boolean;
}>();

const emit = defineEmits<{
    'update:startDate': [value: string];
    'update:endDate': [value: string];
    'update:registrationStart': [value: string];
    'update:registrationEnd': [value: string];
}>();
</script>

<template>
    <div class="flex flex-col gap-8 lg:col-span-5">
        <div class="border-border/60 bg-card rounded-xl border p-5 shadow-xs sm:p-6">
            <div class="mb-5 flex items-center gap-2.5">
                <CalendarRange class="text-foreground/80 size-5 shrink-0 stroke-[1.75]" aria-hidden="true" />
                <div>
                    <p class="text-foreground text-base font-semibold tracking-tight">Jadwal acara</p>
                    <p class="text-muted-foreground mt-0.5 text-xs">Kapan acara berlangsung.</p>
                </div>
            </div>
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-2">
                    <Label for="start_date" class="text-foreground text-sm font-medium"
                        >Tanggal mulai <span v-if="props.startDateRequired" class="text-destructive">*</span></Label
                    ><DatePicker
                        id="start_date"
                        :model-value="props.startDate"
                        :aria-invalid="!!props.startDateError"
                        :class="props.startDateClass"
                        @update:model-value="emit('update:startDate', String($event))"
                    />
                    <p v-if="props.startDateError" class="text-destructive text-xs">{{ props.startDateError }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="end_date" class="text-foreground text-sm font-medium"
                        >Tanggal selesai <span v-if="props.endDateRequired" class="text-destructive">*</span></Label
                    ><DatePicker
                        id="end_date"
                        :model-value="props.endDate"
                        :aria-invalid="!!props.endDateError"
                        :class="props.endDateClass"
                        @update:model-value="emit('update:endDate', String($event))"
                    />
                    <p v-if="props.endDateError" class="text-destructive text-xs">{{ props.endDateError }}</p>
                </div>
            </div>
        </div>
        <div class="border-border/60 bg-card rounded-xl border p-5 shadow-xs sm:p-6">
            <div class="mb-5 flex items-center gap-2.5">
                <CalendarClock class="text-foreground/80 size-5 shrink-0 stroke-[1.75]" aria-hidden="true" />
                <div>
                    <p class="text-foreground text-base font-semibold tracking-tight">Pendaftaran</p>
                    <p class="text-muted-foreground mt-0.5 text-xs">Periode peserta dapat mendaftar.</p>
                </div>
            </div>
            <div class="flex flex-col gap-5">
                <SplitDateTimeField
                    id-prefix="reg_open"
                    :model-value="props.registrationStart"
                    label="Pendaftaran dibuka"
                    picker-class="bg-white"
                    :required="props.registrationStartRequired"
                    :invalid="!!props.registrationStartError"
                    :error="props.registrationStartError"
                    :shaking="props.registrationStartShaking"
                    @update:model-value="emit('update:registrationStart', String($event))"
                /><SplitDateTimeField
                    id-prefix="reg_close"
                    :model-value="props.registrationEnd"
                    label="Pendaftaran ditutup"
                    picker-class="bg-white"
                    :required="props.registrationEndRequired"
                    :invalid="!!props.registrationEndError"
                    :error="props.registrationEndError"
                    :shaking="props.registrationEndShaking"
                    @update:model-value="emit('update:registrationEnd', String($event))"
                />
            </div>
        </div>
    </div>
</template>
