<script setup lang="ts">
import CopyFeedbackIcon from '@/components/core/CopyFeedbackIcon.vue';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group';

interface IShareLinkFieldProps {
    value: string;
    /** Nama aksesibel untuk field readonly. */
    fieldLabel: string;
    /** Nama aksesibel untuk tombol salin; teks terlihat tetap "Salin". */
    copyLabel: string;
    testId: string;
    disabled?: boolean;
    /** true = satu-satunya aksi utama di dialog; false = aksi sekunder. */
    primary?: boolean;
    /** True selama konfirmasi salin aktif: ikon berubah menjadi centang. */
    confirmed?: boolean;
}

const props = withDefaults(defineProps<IShareLinkFieldProps>(), {
    disabled: false,
    primary: false,
    confirmed: false,
});

const emit = defineEmits<{
    (event: 'copy'): void;
}>();
</script>

<template>
    <div :data-testid="props.testId">
        <InputGroup class="h-auto min-h-[44px] sm:h-10 sm:min-h-10">
            <InputGroupInput
                :model-value="props.value"
                readonly
                :aria-label="props.fieldLabel"
                :disabled="props.disabled"
                class="h-11 font-mono text-xs sm:h-10"
            />
            <InputGroupAddon align="inline-end" class="py-0">
                <InputGroupButton
                    type="button"
                    size="sm"
                    :variant="props.primary ? 'default' : 'outline'"
                    :aria-label="props.copyLabel"
                    :disabled="props.disabled"
                    :data-copy-feedback="props.confirmed ? 'confirmed' : 'idle'"
                    class="h-11 min-h-[44px] cursor-pointer gap-1.5 px-2.5 sm:h-8 sm:min-h-8"
                    @click="emit('copy')"
                >
                    <!-- Ikon salin bertukar ke centang selama konfirmasi; warnanya mengikuti
                         kontras tombol (putih di primary, foreground di outline). -->
                    <CopyFeedbackIcon :confirmed="props.confirmed" />
                    <span class="text-xs">Salin</span>
                </InputGroupButton>
            </InputGroupAddon>
        </InputGroup>
    </div>
</template>
