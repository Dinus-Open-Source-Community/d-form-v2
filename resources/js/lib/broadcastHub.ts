/** Pustaka murni broadcast hub: kontrak provisional Fase 1+2 (?event_id=/&period_id=, chip terkunci, snapshot-only). */

/** Sumber dataset snapshot yang didukung hub. */
export type TBroadcastDatasetSource = 'event_participants' | 'recruitment_applicants'

/** Status lifecycle broadcast (provisional, selaras brief Fase 1+2). */
export type TBroadcastHubStatus = 'draft' | 'scheduled' | 'processing' | 'sent'

/** Kind konteks terkunci dari query create. */
export type TBroadcastContextKind = 'event' | 'period' | 'none'

/** Opsi dropdown konteks (event / periode). */
export interface IBroadcastScopeOption {
    id: string
    name: string
}

/** Prefill query create (?event_id=&period_id=) yang diteruskan backend via props. */
export interface IBroadcastContextPrefill {
    eventId?: string | null
    eventName?: string | null
    periodId?: string | null
    periodName?: string | null
}

/** Konteks terkunci: chip read-only, bukan dropdown bebas. */
export interface IBroadcastLockedContext {
    kind: TBroadcastContextKind
    eventId: string | null
    periodId: string | null
    displayName: string | null
}

/** Parameter link create hub dengan prefill konteks. */
export interface IBroadcastCreateLink {
    eventId?: string | null
    periodId?: string | null
}

/** Pilihan dataset + konteks efektif untuk validasi scope. */
export interface IBroadcastDatasetSelection {
    source: TBroadcastDatasetSource | null
    eventId: string | null
    periodId: string | null
}

/** Ringkasan tracking read-only per broadcast (snapshot-only). */
export interface IBroadcastHubTracking {
    totalRecipients: number
    sentCount: number
    failedCount: number
    pendingCount: number
}

/** Satu baris tampilan ringkasan tracking. */
export interface IBroadcastTrackingRow {
    key: string
    label: string
    value: string
}

export const BROADCAST_BASE_PATH = '/admin/broadcasts'
export const BROADCAST_EVENT_QUERY_KEY = 'event_id'
export const BROADCAST_PERIOD_QUERY_KEY = 'period_id'
export const BROADCAST_EVENT_DATASET: TBroadcastDatasetSource = 'event_participants'
export const BROADCAST_PERIOD_DATASET: TBroadcastDatasetSource = 'recruitment_applicants'

/** Label ID sumber dataset snapshot. */
export const BROADCAST_DATASET_LABELS: Record<TBroadcastDatasetSource, string> = {
    event_participants: 'Peserta event (event_participants)',
    recruitment_applicants: 'Pelamar rekrutmen (recruitment_applicants)',
}

/** Prefill create mentah dari backend (snake_case verbatim kontrak Task 1). */
export interface IBroadcastCreatePrefill {
    event_id?: string | null
    period_id?: string | null
    event_title?: string | null
    period_name?: string | null
    locked_event?: boolean
    locked_period?: boolean
}

/** Event yang boleh dipilih (backend memakai `title`). */
export interface IBroadcastAllowedEvent {
    id: string
    title: string
}

/** Periode yang boleh dipilih (backend memakai `name`). */
export interface IBroadcastAllowedPeriod {
    id: string
    name: string
}

/** Mapper prefill snake_case backend ke konteks camelCase hub. */
export function mapBroadcastPrefill(prefill: IBroadcastCreatePrefill): IBroadcastContextPrefill {
    return {
        eventId: prefill.event_id ?? null,
        eventName: prefill.event_title ?? null,
        periodId: prefill.period_id ?? null,
        periodName: prefill.period_name ?? null,
    }
}

/** Mapper allowed_events (title) ke opsi dropdown hub. */
export function mapBroadcastEventOptions(events: IBroadcastAllowedEvent[]): IBroadcastScopeOption[] {
    return events.map((event) => ({ id: event.id, name: event.title }))
}

/** Mapper allowed_periods (name) ke opsi dropdown hub. */
export function mapBroadcastPeriodOptions(periods: IBroadcastAllowedPeriod[]): IBroadcastScopeOption[] {
    return periods.map((period) => ({ id: period.id, name: period.name }))
}

/** Satu penerima dalam snapshot (source of truth per broadcast). */
export interface IBroadcastSnapshotRecipient {
    email: string
    name: string | null
}

/** Snapshot penerima mentah dari backend (snake_case verbatim kontrak Task 1). */
export interface IBroadcastSnapshot {
    source: TBroadcastDatasetSource
    event_id: string | null
    period_id: string | null
    captured_at: string | null
    total: number
    recipients: IBroadcastSnapshotRecipient[]
}

/** Konteks tampilan show mentah dari backend (snake_case verbatim). */
export interface IBroadcastShowContext {
    event_title: string | null
    period_name: string | null
}

/** Broadcast show mentah dari backend (snake_case verbatim kontrak Task 1). */
export interface IBroadcastShownBroadcast {
    id: string
    name: string
    source: TBroadcastDatasetSource
    event_id: string | null
    period_id: string | null
    scheduled_at: string | null
    send_delay_seconds: number | null
    status: TBroadcastHubStatus
    recipient_count: number
    created_at: string | null
}

/** Href create hub dengan prefill konteks query (?event_id=&period_id=). */
export function buildBroadcastCreateHref(link: IBroadcastCreateLink): string {
    const query = new URLSearchParams()
    if (link.eventId) query.set(BROADCAST_EVENT_QUERY_KEY, link.eventId)
    if (link.periodId) query.set(BROADCAST_PERIOD_QUERY_KEY, link.periodId)
    const suffix = query.toString()
    return suffix.length > 0 ? `${BROADCAST_BASE_PATH}/create?${suffix}` : `${BROADCAST_BASE_PATH}/create`
}

/** Konteks terkunci dari prefill query create (chip, bukan dropdown bebas). */
export function resolveBroadcastLockedContext(prefill: IBroadcastContextPrefill): IBroadcastLockedContext {
    if (prefill.eventId) {
        return {
            kind: 'event',
            eventId: prefill.eventId,
            periodId: null,
            displayName: prefill.eventName ?? prefill.eventId,
        }
    }
    if (prefill.periodId) {
        return {
            kind: 'period',
            eventId: null,
            periodId: prefill.periodId,
            displayName: prefill.periodName ?? prefill.periodId,
        }
    }
    return { kind: 'none', eventId: null, periodId: null, displayName: null }
}

/** Pesan error konteks kosong saat akses langsung (dropdown wajib), null bila ada konteks. */
export function resolveBroadcastContextError(context: IBroadcastLockedContext): string | null {
    if (context.kind !== 'none') return null
    return 'Pilih event atau periode terlebih dahulu.'
}

/** Pesan error scope salah (cermin 422 backend), null bila kombinasi dataset+konteks valid. */
export function resolveBroadcastScopeError(selection: IBroadcastDatasetSelection): string | null {
    if (!selection.source) return 'Pilih sumber dataset terlebih dahulu.'
    if (selection.source === BROADCAST_EVENT_DATASET && !selection.eventId) {
        return 'Sumber peserta event wajib memilih event (event_id).'
    }
    if (selection.source === BROADCAST_PERIOD_DATASET && !selection.periodId) {
        return 'Sumber pelamar wajib memilih periode (period_id).'
    }
    return null
}

/** True bila dataset/composer wajib terkunci (scheduled/processing, snapshot-only). */
export function isBroadcastHubLocked(status: TBroadcastHubStatus): boolean {
    return status === 'scheduled' || status === 'processing'
}

/** True bila ringkasan tracking boleh tampil (draft menyembunyikan tracking kosong). */
export function canShowBroadcastTracking(
    status: TBroadcastHubStatus,
    tracking: IBroadcastHubTracking | null,
): boolean {
    if (!tracking) return status !== 'draft'
    if (status === 'draft' && tracking.totalRecipients === 0) return false
    return true
}

/** Baris tampilan read-only dari payload tracking. */
export function buildBroadcastTrackingRows(tracking: IBroadcastHubTracking): IBroadcastTrackingRow[] {
    const formatCount = (value: number): string => value.toLocaleString('id-ID')
    return [
        { key: 'total', label: 'Total penerima', value: formatCount(tracking.totalRecipients) },
        { key: 'sent', label: 'Terkirim', value: formatCount(tracking.sentCount) },
        { key: 'pending', label: 'Menunggu', value: formatCount(tracking.pendingCount) },
        { key: 'failed', label: 'Gagal', value: formatCount(tracking.failedCount) },
    ]
}

/** Baris tampilan read-only dari snapshot + konteks show. */
export function buildBroadcastSnapshotRows(
    snapshot: IBroadcastSnapshot | null,
    context: IBroadcastShowContext | null,
): IBroadcastTrackingRow[] {
    if (!snapshot) return []
    return [
        { key: 'source', label: 'Sumber', value: BROADCAST_DATASET_LABELS[snapshot.source] },
        {
            key: 'context',
            label: 'Konteks',
            value: context?.event_title ?? context?.period_name ?? '—',
        },
        { key: 'captured', label: 'Diambil', value: snapshot.captured_at ?? '—' },
        { key: 'total', label: 'Total penerima', value: snapshot.total.toLocaleString('id-ID') },
    ]
}
