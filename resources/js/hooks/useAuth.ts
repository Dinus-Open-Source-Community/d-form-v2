import { computed, ComputedRef } from 'vue';

export function useAuth(props: Props): ComputedRef<User | null> {
    const user = computed<User | null>(() => props.auth.user);

    return user;
}
