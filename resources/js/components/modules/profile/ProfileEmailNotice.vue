<script setup lang="ts">
import { computed } from 'vue';
import { CircleAlert } from 'lucide-vue-next';

/**
 * Peringatan saat email di form berbeda dari email tersimpan. Server menghapus
 * `email_verified_at` setiap kali email berubah, dan halaman ini tidak punya
 * jalur kirim ulang verifikasi, jadi pengguna harus tahu sebelum menyimpan.
 */
const props = defineProps<{
    originalEmail: string;
    nextEmail: string;
}>();

const isEmailChanged = computed<boolean>(() => props.nextEmail !== props.originalEmail);
</script>

<template>
    <div
        v-if="isEmailChanged"
        role="status"
        class="border-warning/40 bg-warning/10 flex items-start gap-2 rounded-lg border px-3 py-2"
    >
        <CircleAlert class="text-warning-foreground mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <p class="text-foreground text-sm">
            Mengubah email akan menghapus status verifikasi. Simpan untuk melanjutkan.
        </p>
    </div>
</template>
