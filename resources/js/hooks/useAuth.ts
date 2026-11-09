import { computed, ComputedRef } from 'vue';

/** User terautentikasi dari shared props Inertia sebagai computed read-only. */
export function useAuth(props: IProps): ComputedRef<IUser | null> {
    const user = computed<IUser | null>(() => props.auth.user);

    return user;
}
