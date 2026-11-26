/** Kunci skeleton yang dikenal host navigasi. */
export type TSkeletonKey =
    | 'dashboard-index'
    | 'events-index'
    | 'events-show'
    | 'event-form'
    | 'users-index'
    | 'users-show'
    | 'profile'
    | 'scan-global'
    | 'recruitment-index'
    | 'recruitment-period'
    | 'recruitment-period-create'
    | 'activity-logs'
    | 'interview-session'
    | 'my-interviews'
    | 'my-interview'
    | 'form-builder'
    | 'form-fill'
    | 'form-success'
    | 'user-events'
    | 'user-event-detail'
    | 'event-registration'
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
