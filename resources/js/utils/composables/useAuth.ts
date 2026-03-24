import { computed, ComputedRef } from 'vue';

type User = {
    id: string;
    name: string;
};

interface Props {
    auth: { user: User | null };
    appName: string;
}

export default function useAuth(props: Props): ComputedRef<User | null> {
    let user = computed<User | null>(() => props.auth.user);

    return user;
}
