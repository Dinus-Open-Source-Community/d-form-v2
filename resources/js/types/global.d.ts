// global.d.ts
import '@inertiajs/core';

interface IUser {
    id: string;
    name: string;
    email: string;
    avatar: string;
    roles?: string[];
    /** True when the user has a password hash (email/password accounts); false for OAuth-only signups. */
    has_local_password?: boolean;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string;
}

interface IProps {
    auth: { user: IUser | null };
    appName: string;
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
