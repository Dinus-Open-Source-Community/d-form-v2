/** Data peserta yang dikembalikan scan global (event maupun oprec). */
export interface IScanAttendee {
    name?: string;
    email?: string;
    registration_number?: string;
    application_id?: string;
    interview_status_label?: string;
    queue_number?: number | null;
    form_answer_id?: string;
}

/** Envelope sukses `POST /admin/scan`. */
export interface IScanEnvelope {
    type: 'event' | 'recruitment';
    eventTitle: string;
    attendee: IScanAttendee;
    status: 'success' | 'duplicate';
    scannedAt: string;
}

/** Body error scan (409/422/429) yang tetap membawa konteks peserta. */
export interface IScanErrorBody {
    message?: string;
    type?: string;
    eventTitle?: string;
    attendee?: IScanAttendee;
    errors?: Record<string, string[]>;
}

/** Satu baris feed scan lintas meja. Ini subset yang dipakai UI, bukan baris server lengkap. */
export interface IScanFeedRow {
    id: string;
    ts: string;
    type: 'recruitment' | 'event';
    eventTitle: string;
    name: string;
    identifier: string;
    queueNumber: number | null;
}

/** Payload feed scan: cursor + baris baru. Adapter menormalkan cursor null/absen menjadi `''`. */
export interface IScanFeedPayload {
    cursor: string;
    rows: IScanFeedRow[];
}

/** Divisi pada sesi interview yang dipakai filter target scan global. */
export interface IScanTargetDivision {
    name?: string | null;
}

/** Satu sesi interview dari props Inertia `/admin/scan`. */
export interface IScanTargetSession {
    id: string;
    session_date?: string | null;
    division?: IScanTargetDivision | null;
}

/** Satu event dari props Inertia `/admin/scan`. */
export interface IScanTargetEvent {
    id: string | number;
    title?: string | null;
}
