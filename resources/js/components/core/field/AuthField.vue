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
        <Label :for="id" class="text-sm font-medium text-foreground">
            {{ label }}
        </Label>

        <TextInput type="text" :id="id" v-if="type === 'name'" :focus="focus" v-bind="$attrs" />
        <EmailInput :id="id" v-else-if="type === 'email'" :focus="focus" v-bind="$attrs" />
        <PasswordInput v-else :id="id" v-bind="$attrs" />

        <p v-if="error" class="mt-0.5 text-xs font-medium text-destructive">{{ error }}</p>
    </div>
</template>
