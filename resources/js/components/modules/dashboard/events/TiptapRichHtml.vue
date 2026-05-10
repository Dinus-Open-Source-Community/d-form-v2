<script setup lang="ts">
/**
 * Wrapper HTML dari TipTap — pakai kelas .dform-rich-text (lihat resources/css/dform-rich-text.css).
 */
defineProps<{
    /** HTML dari TipTap / penyimpanan server */
    html: string | null | undefined
}>()
</script>

<template>
    <div
        v-if="html && html.trim() !== '' && html !== '<p></p>'"
        class="dform-rich-text text-pretty"
        v-html="html"
    />
    <p v-else class="text-sm text-muted-foreground">Belum ada deskripsi untuk acara ini.</p>
</template>
