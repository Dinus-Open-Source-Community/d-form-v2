import type { IPageSkeletonRule, TSkeletonKey } from '@/types/skeleton';

/**
 * Peta pathname ke kunci skeleton. Urutan adalah perilaku: aturan pertama yang cocok menang,
 * jadi aturan yang lebih spesifik wajib berada di atas aturan induknya.
 *
 * Path lama yang di server hanya mengalihkan (301/302) dipetakan ke skeleton tujuan,
 * bukan ke arketipe generik, agar tidak ada kedipan struktur saat redirect selesai.
 */
export const PAGE_SKELETON_RULES: readonly IPageSkeletonRule[] = [
    // /dashboard dan /admin hanya untuk organizer; peserta memakai /joined.
    { pattern: /^\/dashboard$/, skeleton: 'dashboard-index', when: (context) => context.canManageEvents },
    { pattern: /^\/admin$/, skeleton: 'dashboard-index', when: (context) => context.canManageEvents },
    { pattern: /^\/admin\/dashboard$/, skeleton: 'dashboard-index', when: (context) => context.canManageEvents },

    { pattern: /^\/admin\/events$/, skeleton: 'events-index' },
    { pattern: /^\/admin\/events\/archived$/, skeleton: 'events-index' },
    { pattern: /^\/admin\/events\/create$/, skeleton: 'event-form' },
    { pattern: /^\/admin\/events\/[^/]+\/edit$/, skeleton: 'event-form' },
    { pattern: /^\/admin\/events\/[^/]+\/forms\/create$/, skeleton: 'form-builder' },
    { pattern: /^\/admin\/events\/[^/]+\/forms\/[^/]+\/edit$/, skeleton: 'form-builder' },
    { pattern: /^\/admin\/events\/[^/]+\/forms\/[^/]+\/submissions$/, skeleton: 'form-builder' },
    { pattern: /^\/admin\/events\/[^/]+\/scan$/, skeleton: 'scan-global' },
    { pattern: /^\/admin\/events\/[^/]+\/forms\/[^/]+$/, skeleton: 'form-builder' },
    { pattern: /^\/admin\/events\/[^/]+$/, skeleton: 'events-show' },

    { pattern: /^\/admin\/users$/, skeleton: 'users-index' },
    { pattern: /^\/admin\/users\/[^/]+$/, skeleton: 'users-show' },

    { pattern: /^\/admin\/recruitment$/, skeleton: 'recruitment-index' },
    { pattern: /^\/admin\/recruitment\/periods$/, skeleton: 'recruitment-index' },
    { pattern: /^\/admin\/recruitment\/periods\/create$/, skeleton: 'recruitment-period-create' },
    { pattern: /^\/admin\/recruitment\/periods\/[^/]+\/applications\/[^/]+$/, skeleton: 'detail' },
    { pattern: /^\/admin\/recruitment\/periods\/[^/]+$/, skeleton: 'recruitment-period' },
    { pattern: /^\/admin\/recruitment\/activity-logs$/, skeleton: 'activity-logs' },
    { pattern: /^\/admin\/recruitment\/reports$/, skeleton: 'recruitment-index' },
    { pattern: /^\/admin\/recruitment\/interview-sessions$/, skeleton: 'recruitment-index' },
    { pattern: /^\/admin\/recruitment\/interview-sessions\/[^/]+$/, skeleton: 'interview-session' },
    { pattern: /^\/admin\/recruitment\/my-interviews$/, skeleton: 'my-interviews' },
    { pattern: /^\/admin\/recruitment\/my-interviews\/[^/]+$/, skeleton: 'my-interview' },
    { pattern: /^\/admin\/recruitment\/attendance-scan$/, skeleton: 'scan-global' },

    { pattern: /^\/admin\/scan$/, skeleton: 'scan-global' },
    { pattern: /^\/profile$/, skeleton: 'profile' },

    // Portal peserta: member dan super-admin.
    { pattern: /^\/(browse|joined)$/, skeleton: 'user-events' },
    { pattern: /^\/joined\/profile$/, skeleton: 'profile' },
    { pattern: /^\/joined\/team-invitations\/[^/]+$/, skeleton: 'form-fill' },
    { pattern: /^\/joined\/[^/]+\/registration$/, skeleton: 'event-registration' },
    { pattern: /^\/joined\/[^/]+\/forms\/[^/]+\/fill$/, skeleton: 'form-fill' },
    { pattern: /^\/joined\/[^/]+\/forms\/[^/]+\/submitted$/, skeleton: 'form-success' },
    { pattern: /^\/joined\/[^/]+$/, skeleton: 'user-event-detail' },
];

/** Dipakai saat URL tidak dipetakan; sengaja tidak sama dengan arketipe detail. */
export const DEFAULT_PAGE_SKELETON: TSkeletonKey = 'generic';
