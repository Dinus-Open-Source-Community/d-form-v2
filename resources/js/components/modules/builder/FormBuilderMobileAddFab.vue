<script setup lang="ts">
import { Plus } from 'lucide-vue-next'

defineEmits<{
    click: []
}>()
</script>

<template>
    <button
        type="button"
        class="bg-primary text-primary-foreground hover:bg-primary/92 fixed right-5 bottom-6 z-40 inline-flex size-14 items-center justify-center rounded-full shadow-sm transition-[transform,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px active:scale-[0.96] lg:hidden"
        aria-label="Add field"
        @click="$emit('click')"
    >
        <Plus class="size-6" />
    </button>
</template>
