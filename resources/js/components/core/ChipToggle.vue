<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        active: boolean;
        disabled?: boolean;
    }>(),
    { disabled: false }
);

const emit = defineEmits<{
    select: [];
}>();
</script>

<template>
    <!-- Chip pilihan tunggal: target sentuh 44px saat menumpuk, ringkas saat sejajar. -->
    <button
        type="button"
        :aria-pressed="active"
        :disabled="disabled"
        class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-lg border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-0"
        :class="
            active
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-border bg-card text-muted-foreground hover:border-primary/30 hover:text-foreground'
        "
        @click="emit('select')"
    >
        {{ label }}
    </button>
</template>
