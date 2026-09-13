import { pathWithoutQuery, routes } from '@/lib/routes';

export interface BreadcrumbItem {
    label: string;
    href: string;
}

/**
 * Base path: halaman utama → breadcrumb 1 crumb (aktif).
 * Urut dari path terpanjang dulu agar prefix paling spesifik menang
 * (mis. `/admin/events` sebelum `/admin`).
 */
const BASE_PATHS: BreadcrumbItem[] = [
    { label: 'Acara', href: routes.admin.events.index },
    { label: 'Rekrutmen', href: routes.admin.recruitment.index },
    { label: 'Acara Diikuti', href: routes.member.joined },
    { label: 'Jelajah', href: routes.member.browse },
    { label: 'Dashboard', href: routes.admin.index },
    { label: 'Dashboard', href: routes.dashboard.index },
].sort((a, b) => b.href.length - a.href.length);

/** Label Indonesia untuk segmen aksi/route setelah base path. */
export const ACTION_LABELS: Record<string, string> = {
    register: 'Daftar',
    registration: 'Pendaftaran',
    forms: 'Formulir',
    fill: 'Isi Formulir',
    submitted: 'Terkirim',
    submit: 'Kirim',
    'team-invitations': 'Undangan Tim',
    profile: 'Profil',
    avatar: 'Foto',
    password: 'Kata Sandi',
    users: 'Pengguna',
    'check-email': 'Cek Email',
    scan: 'Scan',
    registrants: 'Peserta',
    laporan: 'Laporan',
    exports: 'Ekspor',
    'attendance-scan': 'Scan Kehadiran',
    create: 'Buat',
    edit: 'Ubah',
};

/**
 * Bangun breadcrumb yang benar-benar mengikuti segmen URL:
 * - Base path (match prefix) → crumb pertama.
 * - Setiap segmen bermakna setelah base → crumb berurutan (href akumulatif).
 *   - Segmen action → label dari ACTION_LABELS.
 *   - Segmen slug/id tak dikenal → label mentah (slug), kecuali segmen terakhir
 *     → prefer pageTitle agar judul halaman tampil, bukan slug mentah.
 * - Base page (path === base) → 1 crumb aktif.
 * - Fallback tak dikenal → 1 crumb judul.
 */
export function buildBreadcrumbs(url: string, pageTitle: string): BreadcrumbItem[] {
    const path = pathWithoutQuery(url);
    const base = BASE_PATHS.find((b) => path === b.href || path.startsWith(`${b.href}/`));

    if (!base) {
        return [{ label: pageTitle, href: path }];
    }

    const crumbs: BreadcrumbItem[] = [{ label: base.label, href: base.href }];
    if (path === base.href) {
        return crumbs;
    }

    // Segmen 'events' setelah base member (joined/browse) adalah struktur portal,
    // bukan halaman — dilewati agar tidak jadi crumb menengah.
    const isMemberBase = base.href === routes.member.joined || base.href === routes.member.browse;

    const rest = path.slice(base.href.length).split('/').filter(Boolean);
    let prefix = base.href;
    rest.forEach((seg, idx) => {
        if (isMemberBase && seg === 'events') {
            return;
        }
        prefix += `/${seg}`;
        const isLast = idx === rest.length - 1;
        const actionLabel = ACTION_LABELS[seg];

        let label: string;
        if (actionLabel) {
            label = actionLabel;
        } else if (isLast && pageTitle !== seg) {
            label = pageTitle;
        } else {
            label = seg;
        }
        crumbs.push({ label, href: prefix });
    });

    return crumbs;
}
