/**
 * Pemetaan tampilan domain event: label, warna kategori, sesi, dan status.
 * Runtime constant murni (tanpa behavior) yang dipakai halaman/komponen; bukan fixture demo.
 */
export const statusColorMap: Record<string, string> = {
    open: '#059669',
    closed: '#6B7280',
    not_yet_open: '#D97706',
    full: '#DC2626',
    draft: '#6B7280',
    published: '#059669',
    pending: '#D97706',
    approved: '#059669',
    accepted: '#059669',
    rejected: '#DC2626',
    submitted: '#059669',
};

export const categoryColorMap: Record<string, string> = {
    rkt: '#0A84DC',
    'non-rkt': '#7C3AED',
    recruitment: '#059669',
    etc: '#D97706',
};

export const sessionLabelMap: Record<string, string> = {
    general: 'General',
    programming: 'Programming',
    network: 'Networking',
    networking: 'Networking',
    media_creative: 'Media Creative',
    data: 'Data',
};

export const categoryLabelMap: Record<string, string> = {
    rkt: 'RKT',
    'non-rkt': 'NON RKT',
    recruitment: 'Recruitment',
    etc: 'Etc',
};
