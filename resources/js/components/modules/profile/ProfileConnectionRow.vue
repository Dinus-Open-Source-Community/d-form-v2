<script setup lang="ts">
import { computed } from 'vue';
import { Check } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import ProviderBrandIcon from '@/components/core/ProviderBrandIcon.vue';
import { PROFILE_PROVIDER_META } from '@/constants/profileConnections';
import type { IProfileConnection } from '@/types/profile';

/**
 * Satu baris koneksi provider: brand mark, nama, status polos, dan satu aksi
 * tenang. Semua keputusan tampil berasal dari server (`configured`,
 * `can_disconnect`): provider yang belum dikonfigurasi tidak menawarkan tombol
 * sama sekali (tombol mati terbaca seperti fitur rusak), dan tombol putuskan
 * hilang saat aksi itu akan mengunci pengguna dari akunnya.
 *
 * Brand mark dibungkus `span` berukuran agar bisa tampil 20px; komponen ikon
 * mengunci `size-4` pada `svg`-nya, dan selector anak memberi specificity lebih
 * tinggi tanpa menyentuh komponen bersama.
 */
const props = defineProps<{
    connection: IProfileConnection;
    pending: boolean;
}>();

const emit = defineEmits<{
    connect: [];
    disconnect: [];
}>();

/** 44px literal: `app.css` memakai `html { font-size: 15px }`, jadi `min-h-11` hanya 41.25px. */
const ACTION_TOUCH_TARGET_CLASS = 'min-h-[44px]';

const meta = computed(() => PROFILE_PROVIDER_META[props.connection.provider]);
const isConnected = computed<boolean>(() => props.connection.connected);
const isConfigured = computed<boolean>(() => props.connection.configured);
const canDisconnect = computed<boolean>(() => props.connection.can_disconnect);
const isDisconnectBlocked = computed<boolean>(() => isConnected.value && !canDisconnect.value);
const rowTestId = computed<string>(() => `profile-connection-${props.connection.provider}`);
</script>

<template>
    <li data-testid="profile-connection-row" class="py-4">
        <div :data-testid="rowTestId" class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex size-5 shrink-0 items-center justify-center [&>svg]:size-5" aria-hidden="true">
                    <ProviderBrandIcon :provider="connection.provider" />
                </span>

                <div class="min-w-0">
                    <p class="text-foreground text-sm font-medium">{{ meta.label }}</p>

                    <p v-if="isConnected" class="text-foreground mt-1 flex items-center gap-1.5 text-sm">
                        <Check class="text-success size-3.5 shrink-0" aria-hidden="true" />
                        <span>Terhubung</span>
                    </p>
                    <p
                        v-else-if="!isConfigured"
                        data-testid="profile-connection-blocked"
                        class="text-muted-foreground mt-1 text-sm"
                    >
                        Belum tersedia di server ini.
                    </p>
                    <p v-else class="text-muted-foreground mt-1 text-sm">Belum terhubung</p>
                </div>
            </div>

            <div class="shrink-0">
                <Button
                    v-if="isConnected && canDisconnect"
                    type="button"
                    variant="ghost"
                    size="sm"
                    data-testid="profile-connection-disconnect"
                    class="text-destructive hover:text-destructive"
                    :class="ACTION_TOUCH_TARGET_CLASS"
                    :disabled="pending"
                    @click="emit('disconnect')"
                >
                    {{ pending ? 'Memproses…' : 'Putuskan' }}
                </Button>
                <Button
                    v-else-if="!isConnected && isConfigured"
                    type="button"
                    variant="outline"
                    size="sm"
                    data-testid="profile-connection-connect"
                    :class="ACTION_TOUCH_TARGET_CLASS"
                    :disabled="pending"
                    @click="emit('connect')"
                >
                    Hubungkan
                </Button>
            </div>
        </div>

        <p
            v-if="isDisconnectBlocked"
            data-testid="profile-connection-blocked"
            class="text-muted-foreground mt-2 text-sm"
        >
            Hubungkan kata sandi atau akun lain dulu supaya Anda tidak kehilangan akses.
        </p>
    </li>
</template>
