// global.d.ts
import '@inertiajs/core';
import type { SharedSeoProps } from './seo';

interface IUser {
    id: string;
    name: string;
    email: string;
    /** URL tampilan (path `storage/` sudah di-resolve di `HandleInertiaRequests`). */
    avatar: string | null;
    email_verified_at?: string | null;
    roles?: string[];
    /** True when the user has a password hash (email/password accounts); false for OAuth-only signups. */
    has_local_password?: boolean;
    /** Selaras middleware organizer: permission events.list */
    can_manage_events?: boolean;
    /** Permission recruitment.dashboard.view */
    can_access_recruitment?: boolean;
    /** Permission recruitment.periods.list */
    can_manage_recruitment_periods?: boolean;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string;
}

interface IProps {
    auth: { user: IUser | null };
    appName: string;
    seo: SharedSeoProps;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: IProps;
        flashDataType: {
            toast?: { type: 'success' | 'error'; message: string };
        };
        errorValueType: string;
    }
}

declare global {
    type User = IUser;
    type Props = IProps;
}

export {};
