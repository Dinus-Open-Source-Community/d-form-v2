<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import UserRowActions from './UserRowActions.vue';
import { formatDateTime } from '@/lib/dateTime';
import { userRoleLabel } from '@/lib/userRoleLabel';
import type { IManagedUser, IUserRoleOption } from '@/types/user-management';

defineProps<{
    users: IManagedUser[];
    roleOptions: IUserRoleOption[];
}>();

const emit = defineEmits<{
    edit: [user: IManagedUser];
    delete: [user: IManagedUser];
}>();

function joinedLabel(value: string | null): string {
    return formatDateTime({ value, variant: 'date-medium' }) || '-';
}
</script>

<template>
    <div class="hidden lg:block">
        <Table>
            <TableCaption class="sr-only">Daftar akun pengguna</TableCaption>
            <TableHeader>
                <TableRow class="border-border/70 hover:bg-transparent">
                    <TableHead scope="col" class="text-muted-foreground pl-4 text-xs font-medium">Akun</TableHead>
                    <TableHead scope="col" class="text-muted-foreground text-xs font-medium">Role</TableHead>
                    <TableHead scope="col" class="text-muted-foreground hidden text-xs font-medium xl:table-cell">
                        Bergabung
                    </TableHead>
                    <TableHead scope="col" class="text-muted-foreground pr-4 text-right text-xs font-medium">
                        Aksi
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody class="divide-border/70 divide-y">
                <TableRow v-for="row in users" :key="row.id" data-testid="user-row" class="hover:bg-muted/40 border-0">
                    <TableCell class="py-3 pl-4 whitespace-normal">
                        <div class="flex min-w-0 items-center gap-3">
                            <UserAvatarFallback
                                :src="row.avatar_url"
                                :seed="row.id"
                                avatar-class="size-10 shrink-0"
                                fallback-round-class="rounded-full"
                            />
                            <div class="min-w-0">
                                <p data-testid="user-row-name" class="text-foreground text-sm font-medium">
                                    {{ row.name }}
                                </p>
                                <p data-testid="user-row-email" class="text-muted-foreground truncate text-xs">
                                    {{ row.email }}
                                </p>
                            </div>
                        </div>
                    </TableCell>

                    <TableCell class="py-3 whitespace-normal">
                        <div data-testid="user-row-role" class="flex flex-wrap gap-1.5">
                            <Badge v-for="role in row.roles" :key="`${row.id}-${role}`" variant="outline">
                                {{ userRoleLabel(role, roleOptions) }}
                            </Badge>
                            <span v-if="row.roles.length === 0" class="text-muted-foreground text-xs">Tanpa role</span>
                        </div>
                    </TableCell>

                    <TableCell class="text-muted-foreground hidden py-3 text-xs xl:table-cell">
                        {{ joinedLabel(row.created_at) }}
                    </TableCell>

                    <TableCell class="py-3 pr-4">
                        <UserRowActions
                            :user="row"
                            layout="table"
                            @edit="emit('edit', $event)"
                            @delete="emit('delete', $event)"
                        />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
