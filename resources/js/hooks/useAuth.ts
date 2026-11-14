import { computed, ComputedRef } from 'vue';

export function useAuth(props: IProps): ComputedRef<IUser | null> {
    const user = computed<IUser | null>(() => props.auth.user);

    return user;
}
