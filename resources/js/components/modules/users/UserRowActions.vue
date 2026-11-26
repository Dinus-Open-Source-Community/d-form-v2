<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { routes } from '@/lib/routes';
import type { IManagedUser } from '@/types/user-management';

const props = defineProps<{
    user: IManagedUser;
    /** `table` memakai tombol ringkas di baris tabel; `card` memberi target sentuh 44px. */
    layout: 'table' | 'card';
}>();

const emit = defineEmits<{
    edit: [user: IManagedUser];
    delete: [user: IManagedUser];
}>();

const isCardLayout = computed<boolean>(() => props.layout === 'card');

const containerClass = computed<string>(() =>
    isCardLayout.value ? 'grid grid-flow-col auto-cols-fr gap-2' : 'flex flex-wrap items-center justify-end gap-1'
);

/** Target sentuh 44px fisik pada kartu (root mobile 15px, jadi rem tidak cukup); tabel cukup ringkas. */
const actionClass = computed<string>(() => (isCardLayout.value ? 'min-h-[44px] w-full' : ''));
</script>

<template>
    <div :class="containerClass">
        <Button
            v-if="user.can_edit"
            variant="outline"
            size="sm"
            data-testid="user-row-edit"
            :class="actionClass"
            :aria-label="`Edit ${user.name}`"
            @click="emit('edit', user)"
        >
            <Pencil class="size-4" aria-hidden="true" />
            Edit
        </Button>

        <Button as-child variant="ghost" size="sm" :class="actionClass">
            <Link
                :href="routes.admin.users.show(user.id)"
                data-testid="user-row-detail"
                :aria-label="`Detail ${user.name}`"
            >
                <Eye class="size-4" aria-hidden="true" />
                Detail
            </Link>
        </Button>

        <Button
            v-if="user.can_delete"
            variant="ghost"
            size="sm"
            data-testid="user-row-delete"
            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
            :class="actionClass"
            :aria-label="`Hapus ${user.name}`"
            @click="emit('delete', user)"
        >
            <Trash2 class="size-4" aria-hidden="true" />
            Hapus
        </Button>
    </div>
</template>
