<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { InertiaForm } from '@inertiajs/vue3';

type Props = {
    form: InertiaForm<{email: string; password: string}>;
};

const { form } = defineProps<Props>();
</script>

<template>
    <Button
        size="lg"
        class="w-full disabled:cursor-not-allowed"
        :disabled="form.processing"
    >
        <span v-if="form.processing" class="flex items-center gap-2">
            <Spinner />
            <slot name="processing"></slot>
        </span>

        <span v-else class="flex items-center gap-2">
            <slot></slot>
        </span>
    </Button>
</template>
