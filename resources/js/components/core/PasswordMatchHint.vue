<script setup lang="ts">
import { computed } from 'vue';
import { Check, X } from 'lucide-vue-next';

/**
 * Status kecocokan kata sandi dan konfirmasinya. Live region selalu ada agar
 * perubahan status diumumkan screen reader tanpa menggeser layout.
 */
const props = defineProps<{
    password: string;
    confirmation: string;
}>();

const isConfirmationFilled = computed<boolean>(() => props.confirmation.length > 0);

const doPasswordsMatch = computed<boolean>(() => props.password === props.confirmation);
</script>

<template>
    <div class="min-h-5" aria-live="polite" aria-atomic="true">
        <p
            v-if="isConfirmationFilled"
            class="flex items-center gap-1.5 text-[11px] font-medium"
            :class="doPasswordsMatch ? 'text-success' : 'text-destructive'"
        >
            <Check v-if="doPasswordsMatch" class="size-3.5 shrink-0" :stroke-width="3" aria-hidden="true" />
            <X v-else class="size-3.5 shrink-0" :stroke-width="3" aria-hidden="true" />
            {{ doPasswordsMatch ? 'Kata sandi cocok' : 'Kata sandi belum cocok' }}
        </p>
    </div>
</template>
