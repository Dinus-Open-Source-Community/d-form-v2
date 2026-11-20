import type { IPageSkeletonRule, TSkeletonKey } from '@/types/skeleton';

/**
 * Peta pathname ke kunci skeleton. Urutan adalah perilaku: aturan pertama yang cocok menang,
 * jadi aturan yang lebih spesifik wajib berada di atas aturan induknya.
 */
export const PAGE_SKELETON_RULES: readonly IPageSkeletonRule[] = [
    // /dashboard hanya untuk organizer; peserta memakai /joined. Capability sama dengan guard server.
    { pattern: /^\/dashboard$/, skeleton: 'dashboard-index', when: (context) => context.canManageEvents },

    { pattern: /^\/admin\/events$/, skeleton: 'events-index' },
    { pattern: /^\/admin\/events\/archived$/, skeleton: 'list' },
    { pattern: /^\/admin\/events\/create$/, skeleton: 'form' },
    { pattern: /^\/admin\/events\/[^/]+\/edit$/, skeleton: 'form' },
    { pattern: /^\/admin\/events\/[^/]+\/forms\/create$/, skeleton: 'form' },
    { pattern: /^\/admin\/events\/[^/]+\/forms\/[^/]+\/edit$/, skeleton: 'form' },
    { pattern: /^\/admin\/events\/[^/]+\/forms\/[^/]+\/submissions$/, skeleton: 'list' },
    { pattern: /^\/admin\/events\/[^/]+\/forms\/[^/]+\/fill$/, skeleton: 'form' },
    { pattern: /^\/admin\/events\/[^/]+\/forms\/[^/]+$/, skeleton: 'form-builder' },
    { pattern: /^\/admin\/events\/[^/]+\/registration-status$/, skeleton: 'detail' },
    { pattern: /^\/admin\/events\/[^/]+$/, skeleton: 'events-show' },

    { pattern: /^\/admin\/users$/, skeleton: 'users-index' },
    { pattern: /^\/admin\/users\/[^/]+$/, skeleton: 'detail' },

    { pattern: /^\/admin\/recruitment$/, skeleton: 'recruitment-index' },
    { pattern: /^\/admin\/recruitment\/periods$/, skeleton: 'list' },
    { pattern: /^\/admin\/recruitment\/periods\/create$/, skeleton: 'form' },
    { pattern: /^\/admin\/recruitment\/periods\/[^/]+\/applications\/[^/]+$/, skeleton: 'detail' },
    { pattern: /^\/admin\/recruitment\/periods\/[^/]+$/, skeleton: 'recruitment-period' },
    { pattern: /^\/admin\/recruitment\/activity-logs$/, skeleton: 'list' },
    { pattern: /^\/admin\/recruitment\/reports$/, skeleton: 'detail' },
    { pattern: /^\/admin\/recruitment\/interview-sessions$/, skeleton: 'list' },
    { pattern: /^\/admin\/recruitment\/interview-sessions\/[^/]+$/, skeleton: 'detail' },
    { pattern: /^\/admin\/recruitment\/my-interviews$/, skeleton: 'list' },
    { pattern: /^\/admin\/recruitment\/my-interviews\/[^/]+$/, skeleton: 'detail' },

    { pattern: /^\/admin\/scan$/, skeleton: 'detail' },
    { pattern: /^\/profile$/, skeleton: 'detail' },

    // Portal peserta: member dan super-admin.
    { pattern: /^\/(browse|joined)$/, skeleton: 'user-events' },
    { pattern: /^\/joined\/team-invitations\/[^/]+$/, skeleton: 'form' },
    { pattern: /^\/joined\/[^/]+\/registration$/, skeleton: 'detail' },
    { pattern: /^\/joined\/[^/]+$/, skeleton: 'user-event-detail' },
];

/** Dipakai saat URL tidak dipetakan; sengaja tidak sama dengan arketipe detail. */
export const DEFAULT_PAGE_SKELETON: TSkeletonKey = 'generic';
