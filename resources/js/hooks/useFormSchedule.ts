import { ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { store, destroy } from '@/routes/dashboard/events/forms/sessions';
import type { IFormSession } from '@/types/form';
import { hasScheduledDate, sessionRemovalReason } from '@/lib/formSchedule';

interface IUseFormScheduleResult {
    sessions: Ref<IFormSession[]>;
    syncSessions: (next: IFormSession[]) => void;
    addSession: (sessionDate: string) => boolean;
    removeSession: (sessionId: string) => boolean;
    canRemove: (sessionId: string) => boolean;
    error: Ref<string | null>;
}

/** Manages class sessions while keeping duplicate validation client-side. */
export function useFormSchedule(initial: IFormSession[], eventId: string, formId: string): IUseFormScheduleResult {
    const sessions = ref<IFormSession[]>([...initial]);
    const error = ref<string | null>(null);

    function syncSessions(next: IFormSession[]): void {
        sessions.value = [...next];
    }

    function addSession(sessionDate: string): boolean {
        error.value = null;
        if (sessionDate === '') return false;
        if (hasScheduledDate(sessions.value, sessionDate)) {
            error.value = 'Tanggal ini sudah ada di jadwal.';
            return false;
        }
        router.post(
            store.url([eventId, formId]),
            { session_date: sessionDate },
            {
                preserveScroll: true,
                onError: (errors) => {
                    error.value = errors.session_date ?? 'Jadwal gagal disimpan.';
                },
                onSuccess: () => {
                    error.value = null;
                },
            }
        );
        return true;
    }

    function removeSession(sessionId: string): boolean {
        const session = sessions.value.find((entry) => entry.id === sessionId);
        const reason = sessionRemovalReason(session);
        if (reason !== null) {
            error.value = reason;
            return false;
        }
        router.delete(destroy.url([eventId, formId, sessionId]), {
            preserveScroll: true,
            onError: () => {
                error.value = 'Jadwal gagal dihapus.';
            },
            onSuccess: () => {
                error.value = null;
            },
        });
        return true;
    }

    function canRemove(sessionId: string): boolean {
        return sessionRemovalReason(sessions.value.find((entry) => entry.id === sessionId)) === null;
    }

    return { sessions, syncSessions, addSession, removeSession, canRemove, error };
}
