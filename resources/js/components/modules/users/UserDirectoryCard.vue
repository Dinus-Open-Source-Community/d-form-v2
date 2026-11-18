<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import UserRowActions from './UserRowActions.vue';
import { userRoleLabel } from '@/lib/userRoleLabel';
import type { IManagedUser, IUserRoleOption } from '@/types/user-management';

defineProps<{
    user: IManagedUser;
    roleOptions: IUserRoleOption[];
}>();

const emit = defineEmits<{
    edit: [user: IManagedUser];
    delete: [user: IManagedUser];
}>();
</script>

<template>
    <article
        data-testid="user-row"
        class="border-border bg-card hover:border-primary/30 flex min-w-0 flex-col gap-3 rounded-xl border p-3 transition-colors"
    >
        <div class="flex min-w-0 items-center gap-3">
            <UserAvatarFallback
                :src="user.avatar_url"
                :seed="user.id"
                avatar-class="size-10 shrink-0"
                fallback-round-class="rounded-full"
            />
            <div class="min-w-0">
                <p data-testid="user-row-name" class="text-foreground truncate text-sm font-medium">{{ user.name }}</p>
                <p data-testid="user-row-email" class="text-muted-foreground truncate text-xs">{{ user.email }}</p>
            </div>
        </div>

        <div data-testid="user-row-role" class="flex flex-wrap gap-1.5">
            <Badge v-for="role in user.roles" :key="`${user.id}-${role}`" variant="outline">
                {{ userRoleLabel(role, roleOptions) }}
            </Badge>
            <span v-if="user.roles.length === 0" class="text-muted-foreground text-xs">Tanpa role</span>
        </div>

        <UserRowActions :user="user" layout="card" @edit="emit('edit', $event)" @delete="emit('delete', $event)" />
    </article>
</template>
