<script setup lang="ts">
import { computed } from 'vue';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import ProfileConnectionRow from '@/components/modules/profile/ProfileConnectionRow.vue';
import { PROFILE_PROVIDER_META } from '@/constants/profileConnections';
import type { IProfileConnectionActions } from '@/hooks/useProfileConnections';
import type { IProfileConnection, TProfileProvider } from '@/types/profile';

/**
 * Isi bagian Koneksi akun: satu baris per provider, tanpa kotak tambahan di
 * dalam permukaan halaman. Pemutusan koneksi selalu lewat konfirmasi karena
 * tindakan itu mengurangi cara masuk ke akun; hubungkan tidak perlu konfirmasi
 * karena halaman langsung berpindah ke provider.
 */
const props = defineProps<{
    connections: IProfileConnection[];
    actions: IProfileConnectionActions;
}>();

const isConfirmOpen = computed<boolean>(() => props.actions.isConfirmOpen.value);
const disconnectTarget = computed<TProfileProvider | null>(() => props.actions.disconnectTarget.value);
const pendingProvider = computed<TProfileProvider | null>(() => props.actions.pendingProvider.value);
const isDisconnectPending = computed<boolean>(() => pendingProvider.value !== null);
const targetLabel = computed<string>(() =>
    disconnectTarget.value === null ? '' : PROFILE_PROVIDER_META[disconnectTarget.value].label
);

/** Dialog bisa ditutup lewat Escape atau tombol batal; keduanya membatalkan konfirmasi. */
function onConfirmOpenChange(open: boolean): void {
    if (open) return;

    props.actions.cancelDisconnect();
}
</script>

<template>
    <div class="min-w-0">
        <ul class="divide-border divide-y">
            <ProfileConnectionRow
                v-for="connection in connections"
                :key="connection.provider"
                :connection="connection"
                :pending="pendingProvider === connection.provider"
                @connect="actions.connect(connection.provider)"
                @disconnect="actions.requestDisconnect(connection.provider)"
            />
        </ul>

        <ConfirmationModal
            :open="isConfirmOpen"
            :title="`Putuskan koneksi ${targetLabel}?`"
            :description="`Anda tidak akan bisa masuk dengan ${targetLabel} lagi. Nama, email, dan foto tetap sama.`"
            confirm-text="Putuskan"
            cancel-text="Batal"
            variant="destructive"
            :loading="isDisconnectPending"
            @confirm="actions.confirmDisconnect()"
            @cancel="actions.cancelDisconnect()"
            @update:open="onConfirmOpenChange"
        />
    </div>
</template>
