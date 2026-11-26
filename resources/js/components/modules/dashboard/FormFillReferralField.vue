<script setup lang="ts">
import { computed, useId } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TReferralCheckStatus } from '@/hooks/useReferralCodeCheck';

const model = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        error?: string;
        status?: TReferralCheckStatus;
        statusMessage?: string;
    }>(),
    {
        error: '',
        status: 'idle',
        statusMessage: '',
    }
);

const inputId = useId();
const helpId = `${inputId}-help`;
const errorId = `${inputId}-error`;

/** Error submit server selalu menang atas pesan status live. */
const isInvalid = computed<boolean>(() => props.error !== '' || props.status === 'invalid');

const statusClass = computed<string>(() => {
    if (isInvalid.value) {
        return 'border-destructive/70 focus-visible:border-destructive focus-visible:ring-destructive/20';
    }
    if (props.status === 'valid') {
        return 'border-success/60 focus-visible:border-success focus-visible:ring-success/25 bg-success/5';
    }
    return '';
});

const invalidMessage = computed<string>(() => props.error || props.statusMessage || 'Kode referral tidak valid.');

const describedBy = computed<string>(() => (isInvalid.value ? errorId : helpId));
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <Label :for="inputId" class="text-muted-foreground text-xs font-semibold"> Kode referral (opsional) </Label>
        <Input
            :id="inputId"
            :model-value="model"
            autocomplete="off"
            spellcheck="false"
            class="font-mono uppercase"
            :class="statusClass"
            :aria-invalid="isInvalid ? true : undefined"
            :aria-busy="props.status === 'checking' ? true : undefined"
            :aria-describedby="describedBy"
            @update:model-value="(value: string | number) => (model = String(value))"
        />
        <p v-if="!isInvalid && props.status === 'idle'" :id="helpId" class="text-muted-foreground text-xs">
            Kosongkan bila tidak ada.
        </p>
        <p v-else-if="!isInvalid && props.status === 'checking'" :id="helpId" class="text-muted-foreground text-xs">
            Memeriksa kode…
        </p>
        <p v-else-if="!isInvalid && props.status === 'valid'" :id="helpId" class="text-success text-xs font-medium">
            {{ props.statusMessage || 'Kode referral valid.' }}
        </p>
        <p v-else :id="errorId" role="alert" class="text-destructive text-xs font-medium">
            {{ invalidMessage }}
        </p>
    </div>
</template>
