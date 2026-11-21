<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { IFormSession } from '@/types/form';
import { useFormSchedule } from '@/hooks/useFormSchedule';

const props = defineProps<{ eventId: string; formId: string; sessions: IFormSession[] }>();
const schedule = useFormSchedule(props.sessions, props.eventId, props.formId);
const sessionDate = ref('');

watch(
    () => props.sessions,
    (nextSessions) => schedule.syncSessions(nextSessions)
);

function addSession(): void {
    if (schedule.addSession(sessionDate.value)) sessionDate.value = '';
}
</script>

<template>
    <section class="border-border bg-card space-y-4 rounded-2xl border p-6 shadow-xs" aria-labelledby="schedule-title">
        <div>
            <h2 id="schedule-title" class="text-foreground text-sm font-semibold">Jadwal kelas</h2>
            <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                Atur tanggal pertemuan yang tersedia untuk peserta.
            </p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1 space-y-2">
                <Label for="session-date-input">Tanggal pertemuan</Label>
                <Input
                    id="session-date-input"
                    v-model="sessionDate"
                    type="date"
                    data-testid="session-date-input"
                    class="min-h-11"
                />
            </div>
            <Button
                type="button"
                data-testid="session-add-button"
                class="min-h-11"
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
                    <p class="text-foreground text-sm font-medium">{{ session.session_date }}</p>
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
        <p v-else class="text-muted-foreground rounded-xl border border-dashed px-4 py-5 text-center text-sm">
            Belum ada jadwal.
        </p>
    </section>
</template>
