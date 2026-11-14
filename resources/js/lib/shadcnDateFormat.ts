import { CalendarDate, parseDate } from '@internationalized/date';
import type { DateValue } from 'reka-ui';

export function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

export function formatIdDateLabel(isoDate: string): string {
    if (!isoDate || isoDate.length < 10) return '';
    const y = isoDate.slice(0, 4);
    const m = isoDate.slice(5, 7);
    const d = isoDate.slice(8, 10);
    if (!y || !m || !d) return isoDate;
    return `${d}/${m}/${y}`;
}

export function formatIdDateTimeLabel(iso: string): string {
    if (!iso) return '';
    const [datePart, timePart = ''] = iso.split('T');
    const t = timePart.slice(0, 5);
    if (!datePart || datePart.length < 10) return iso;
    const base = formatIdDateLabel(datePart);
    return t ? `${base}, ${t.replace(':', '.')}` : base;
}

export function modelValueToCalendarDate(value: string): CalendarDate | undefined {
    if (!value || value.length < 10) return undefined;
    try {
        return parseDate(value.slice(0, 10));
    } catch {
        return undefined;
    }
}

export function calendarDateToYmd(value: DateValue | undefined): string {
    if (!value || !('year' in value) || !('month' in value) || !('day' in value)) return '';
    const d = value as CalendarDate;
    return `${d.year}-${pad2(d.month)}-${pad2(d.day)}`;
}

export function splitLocalDateTime(value: string): { date: CalendarDate | undefined; time: string } {
    if (!value) return { date: undefined, time: '00:00' };
    const [datePart, timePart = ''] = value.split('T');
    const time = timePart.length >= 5 ? timePart.slice(0, 5) : '00:00';
    return { date: modelValueToCalendarDate(datePart), time };
}

export function combineLocalDateTime(date: CalendarDate | undefined, timeHHmm: string): string {
    if (!date) return '';
    const t = timeHHmm && timeHHmm.length >= 5 ? timeHHmm.slice(0, 5) : '00:00';
    const [hh, mm] = t.split(':').map((x) => Number(x));
    const h = Number.isFinite(hh) ? pad2(Math.min(23, Math.max(0, hh))) : '00';
    const m = Number.isFinite(mm) ? pad2(Math.min(59, Math.max(0, mm))) : '00';
    return `${date.year}-${pad2(date.month)}-${pad2(date.day)}T${h}:${m}`;
}
