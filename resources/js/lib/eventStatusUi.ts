// Sumber tunggal label dan kelas badge status acara serta persentase isi kuota.
// Dipakai `RecentEventsCard` dan `EventCard` supaya tampilan kedua permukaan tidak
// pernah berbeda; modul ini murni (tanpa Vue, DOM, atau state).

/** Label dan kelas Tailwind untuk satu badge status acara. */
export interface IEventStatusBadge {
    label: string;
    className: string;
}

/** Badge status publikasi; `null` untuk acara published agar tidak ada badge kosong. */
export function eventPublicationBadge(status: IEvent['status']): IEventStatusBadge | null {
    return status === 'draft' ? { label: 'Draf', className: 'border-border bg-muted/60 text-muted-foreground' } : null;
}

/** Badge status pendaftaran acara, selalu punya label. */
export function eventRegistrationBadge(status: IEvent['registration_status']): IEventStatusBadge {
    switch (status) {
        case 'open':
            return {
                label: 'Dibuka',
                className: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
            };
        case 'full':
            return {
                label: 'Penuh',
                className: 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-400',
            };
        case 'closed':
            return { label: 'Ditutup', className: 'border-border bg-muted/60 text-muted-foreground' };
        default:
            return {
                label: 'Segera',
                className: 'border-amber-500/25 bg-amber-500/10 text-amber-800 dark:text-amber-400',
            };
    }
}

/** Persentase isi kuota dibulatkan dan dijepit 0..100; kuota tidak valid menjadi 0. */
export function eventQuotaFillPercent({ registered, quota }: { registered: number; quota: number }): number {
    if (quota <= 0) {
        return 0;
    }

    const ratio = (Math.max(0, registered) / quota) * 100;

    return Math.min(100, Math.max(0, Math.round(ratio)));
}
