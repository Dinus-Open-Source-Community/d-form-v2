<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { TextInput, EmailInput, PasswordInput } from '@/components/core/input';

type Props = {
    label: string;
    error?: string;
    type: 'name' | 'email' | 'password';
    id: string;
    focus: boolean;
};

const { error } = defineProps<Props>();
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <Label :for="id" class="text-foreground text-sm font-medium">
            {{ label }}
        </Label>

        <TextInput type="text" :id="id" v-if="type === 'name'" :focus="focus" v-bind="$attrs" />
        <EmailInput v-else-if="type === 'email'" :id="id" :focus="focus" v-bind="$attrs">
            <template v-if="$slots.trailing" #trailing>
                <slot name="trailing" />
            </template>
        </EmailInput>
        <PasswordInput v-else :id="id" v-bind="$attrs" />

        <p v-if="error" class="text-destructive mt-0.5 text-xs font-medium">{{ error }}</p>
    </div>
</template>
