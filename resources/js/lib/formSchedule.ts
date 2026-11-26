import type { IFormSession } from '@/types/form';

/** Returns true when a date is already represented in the schedule. */
export function hasScheduledDate(sessions: readonly IFormSession[], sessionDate: string): boolean {
    return sessions.some((session) => session.session_date === sessionDate);
}

/** Returns the localized reason why a session cannot be removed. */
export function sessionRemovalReason(session: IFormSession | undefined): string | null {
    return session !== undefined && session.attendance_count > 0
        ? 'Tanggal ini sudah memiliki absensi dan tidak dapat dihapus.'
        : null;
}
