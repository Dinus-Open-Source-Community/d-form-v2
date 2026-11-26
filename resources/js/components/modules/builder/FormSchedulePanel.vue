<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { DatePicker } from '@/components/ui/date-picker';
import type { IFormSession } from '@/types/form';
import { useFormSchedule } from '@/hooks/useFormSchedule';
import { formatDateTime } from '@/lib/dateTime';

const props = withDefaults(
    defineProps<{
        eventId: string;
        formId: string;
        sessions: IFormSession[];
        /** Tampilkan sakelar `Check-in di setiap pertemuan` (form pendaftaran). */
        toggleable?: boolean;
        enabled?: boolean;
    }>(),
    { toggleable: false, enabled: true }
);

const emit = defineEmits<{
    (event: 'update:enabled', value: boolean): void;
}>();

const schedule = useFormSchedule(props.sessions, props.eventId, props.formId);
const sessionDate = ref('');

watch(
    () => props.sessions,
    (nextSessions) => schedule.syncSessions(nextSessions)
);

function addSession(): void {
    if (schedule.addSession(sessionDate.value)) sessionDate.value = '';
}

const showDates = (): boolean => !props.toggleable || props.enabled;

const panelDescription = (): string =>
    props.toggleable
        ? 'Aktifkan check-in per pertemuan bila acara memiliki lebih dari satu pertemuan.'
        : 'Tentukan tanggal pertemuan tempat peserta check-in.';
</script>

<template>
    <section
        class="border-border bg-card mt-6 scroll-mt-6 space-y-5 rounded-2xl border p-6 shadow-xs"
        aria-labelledby="schedule-title"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h2 id="schedule-title" class="text-foreground text-sm font-semibold">Jadwal pertemuan</h2>
                <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                    {{ panelDescription() }}
                </p>
            </div>
            <div v-if="props.toggleable" class="flex shrink-0 items-center gap-2 sm:pt-0.5">
                <Label for="meeting-schedule-toggle" class="text-xs font-medium">Check-in per pertemuan</Label>
                <Switch
                    id="meeting-schedule-toggle"
                    aria-label="Check-in per pertemuan"
                    :model-value="props.enabled"
                    @update:model-value="(value: boolean) => emit('update:enabled', value)"
                />
            </div>
        </div>

        <template v-if="showDates()">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1 space-y-2">
                    <Label for="session-date-input">Tanggal pertemuan</Label>
                    <DatePicker
                        id="session-date-input"
                        v-model="sessionDate"
                        placeholder="Pilih tanggal"
                        class="h-11 w-full bg-white text-sm"
                    />
                </div>
                <Button
                    type="button"
                    data-testid="session-add-button"
                    class="h-11"
                    :disabled="sessionDate === ''"
                    @click="addSession"
                    >Tambah tanggal</Button
                >
            </div>

            <p v-if="schedule.error.value" class="text-destructive text-sm" role="alert">{{ schedule.error.value }}</p>

            <div v-if="schedule.sessions.value.length > 0" class="space-y-2">
                <div
                    v-for="session in schedule.sessions.value"
                    :key="session.id"
                    data-testid="session-row"
                    class="border-border/70 bg-background flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3"
                >
                    <div class="min-w-0">
                        <p class="text-foreground text-sm font-medium">
                            {{ formatDateTime({ value: session.session_date, variant: 'date-long' }) }}
                        </p>
                        <p v-if="session.label" class="text-muted-foreground text-xs">{{ session.label }}</p>
                        <p v-if="session.attendance_count > 0" class="text-muted-foreground mt-1 text-xs">
                            Tidak dapat dihapus karena sudah memiliki absensi.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        data-testid="session-remove-button"
                        :disabled="!schedule.canRemove(session.id)"
                        @click="schedule.removeSession(session.id)"
                        >Hapus</Button
                    >
                </div>
            </div>
            <p v-else class="text-muted-foreground text-xs">Belum ada tanggal pertemuan.</p>
        </template>
    </section>
</template>
