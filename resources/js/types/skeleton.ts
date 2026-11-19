/** Kunci skeleton yang dikenal host navigasi. */
export type TSkeletonKey =
    | 'dashboard-index'
    | 'member-dashboard'
    | 'events-index'
    | 'events-show'
    | 'users-index'
    | 'recruitment-index'
    | 'recruitment-period'
    | 'form-builder'
    | 'user-events'
    | 'user-event-detail'
    | 'list'
    | 'detail'
    | 'form'
    | 'generic';

/** Capability pengguna yang menentukan halaman tujuan pada URL yang sama. */
export interface ISkeletonContext {
    /** `auth.user.can_manage_events`; guard server memakai permission yang sama. */
    readonly canManageEvents: boolean;
}

/** Satu aturan peta URL; aturan pertama yang cocok menang. */
export interface IPageSkeletonRule {
    readonly pattern: RegExp;
    readonly skeleton: TSkeletonKey;
    /** Aturan hanya berlaku bila syarat terpenuhi; tanpa syarat berlaku untuk semua pengguna. */
    readonly when?: (context: ISkeletonContext) => boolean;
}
