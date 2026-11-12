<script setup lang="ts">
import { Plus } from 'lucide-vue-next';

defineEmits<{
    click: [];
}>();
</script>

<template>
    <!--
        FAB kanvas mobile: bulat penuh, mengambang di atas area aksi bawah dan tidak
        pernah menempel ke tepi bawah browser (`env(safe-area-inset-bottom)`).
        Jarak angkat dibaca dari `--builder-fab-offset` yang dipasang workspace, karena
        tinggi area aksi bawah berbeda antara satu baris (>= sm: 44px + 24px) dan dua
        baris (< sm: 12px + 44px + 8px + 44px + 24px).
    -->
    <button
        type="button"
        class="bg-primary text-primary-foreground hover:bg-primary/92 focus-visible:ring-ring/40 active:bg-primary/85 focus-visible:ring-offset-background absolute right-4 bottom-[calc(env(safe-area-inset-bottom)+var(--builder-fab-offset,5.25rem))] z-40 inline-flex size-14 items-center justify-center rounded-full shadow-lg transition-[background-color,transform] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] outline-none focus-visible:ring-[3px] focus-visible:ring-offset-2 active:scale-[0.97] lg:hidden"
        aria-label="Add field"
        @click="$emit('click')"
    >
        <Plus class="size-6" aria-hidden="true" />
    </button>
</template>
