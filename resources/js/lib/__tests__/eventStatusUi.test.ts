import { describe, expect, it } from 'vitest';

import { eventPublicationBadge, eventQuotaFillPercent, eventRegistrationBadge } from '@/lib/eventStatusUi';

/** Label + kelas yang wajib sama dengan tampilan `EventCard.vue`. */
const REGISTRATION_EXPECTATION: Record<IEvent['registration_status'], { label: string; className: string }> = {
    open: {
        label: 'Dibuka',
        className: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    },
    full: {
        label: 'Penuh',
        className: 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-400',
    },
    closed: {
        label: 'Ditutup',
        className: 'border-border bg-muted/60 text-muted-foreground',
    },
    not_yet_open: {
        label: 'Segera',
        className: 'border-amber-500/25 bg-amber-500/10 text-amber-800 dark:text-amber-400',
    },
};

/** Panggilan ringkas agar setiap kasus kuota terbaca sebagai satu baris. */
function quotaFillPercent(registered: number, quota: number): number {
    return eventQuotaFillPercent({ registered, quota });
}

describe('eventPublicationBadge', () => {
    it('memberi badge Draf untuk acara draft', () => {
        expect(eventPublicationBadge('draft')).toEqual({
            label: 'Draf',
            className: 'border-border bg-muted/60 text-muted-foreground',
        });
    });

    it('tidak memberi badge untuk acara published', () => {
        expect(eventPublicationBadge('published')).toBeNull();
    });
});

describe('eventRegistrationBadge', () => {
    const statuses: Array<IEvent['registration_status']> = ['open', 'full', 'closed', 'not_yet_open'];

    for (const status of statuses) {
        it(`mengembalikan label dan kelas untuk status ${status}`, () => {
            expect(eventRegistrationBadge(status)).toEqual(REGISTRATION_EXPECTATION[status]);
        });
    }
});

describe('eventQuotaFillPercent', () => {
    it('menghitung persentase terisi secara normal', () => {
        expect(quotaFillPercent(12, 50)).toBe(24);
    });

    it('membatasi di 100 saat pendaftar melebihi kuota', () => {
        expect(quotaFillPercent(80, 50)).toBe(100);
    });

    it('mengembalikan 0 saat kuota tidak tersedia', () => {
        expect(quotaFillPercent(12, 0)).toBe(0);
    });

    it('memperlakukan pendaftar negatif sebagai 0', () => {
        expect(quotaFillPercent(-5, 50)).toBe(0);
    });
});
