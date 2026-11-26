import type { IDuSubmission, TDuRedeemStatus } from '@/types/du';

/** Token warna badge per status penerbitan; `issued` tidak memakai badge (kode adalah sinyalnya). */
const DU_ISSUANCE_BADGE_CLASS: Record<Exclude<TDuRedeemStatus, 'issued'>, string> = {
    failed: 'border-destructive/35 text-destructive',
    unknown: 'border-amber-500/40 text-amber-700 dark:text-amber-300',
    pending: 'border-primary/30 text-primary',
};

const DU_ISSUANCE_BADGE_LABEL: Record<Exclude<TDuRedeemStatus, 'issued'>, string> = {
    failed: 'Gagal',
    unknown: 'Menunggu DU',
    pending: 'Diproses',
};

/**
 * Badge status penerbitan kode DU untuk tabel/list jawaban; `null` bila jawaban
 * tidak punya data DU sama sekali atau sudah terbit (kode menjadi sinyalnya).
 * Tanpa issuance, mapping menentukan apakah jawaban siap diterbitkan
 * (`Belum terbit`) atau belum terhubung ke course.
 */
export function duIssuanceBadge(du: IDuSubmission | null): { label: string; className: string } | null {
    if (du === null) return null;

    const status: TDuRedeemStatus | undefined = du.issuance?.status;
    if (status === undefined) {
        return du.mapping
            ? { label: 'Belum terbit', className: 'border-primary/30 text-primary' }
            : {
                  label: 'Belum terhubung',
                  className: 'border-amber-500/40 text-amber-700 dark:text-amber-300',
              };
    }
    if (status === 'issued') return null;

    return {
        label: DU_ISSUANCE_BADGE_LABEL[status],
        className: DU_ISSUANCE_BADGE_CLASS[status],
    };
}
