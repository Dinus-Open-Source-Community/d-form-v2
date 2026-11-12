<script setup lang="ts">
defineProps<{
    formTitle: string;
    formDescription: string;
    formHasDescription: boolean;
    formBannerImageSrc: string;
    formBannerCaption: string;
}>();
</script>

<template>
    <div>
        <div
            v-if="formBannerImageSrc.trim() || formBannerCaption.trim()"
            class="border-border bg-card mx-auto mb-5 w-full max-w-4xl overflow-hidden rounded-2xl border shadow-sm"
        >
            <img
                v-if="formBannerImageSrc.trim()"
                :src="formBannerImageSrc"
                :alt="formTitle"
                class="mx-auto max-h-72 w-full object-contain"
            />
            <p
                v-if="formBannerCaption"
                class="border-border text-muted-foreground border-t px-5 py-4 text-sm leading-relaxed"
            >
                {{ formBannerCaption }}
            </p>
        </div>

        <div class="border-border bg-card mb-5 rounded-2xl border p-4 text-center shadow-sm">
            <h1 class="font-display text-foreground text-4xl font-bold tracking-[-0.035em] text-balance">
                {{ formTitle }}
            </h1>
            <p v-if="formHasDescription" class="text-muted-foreground mt-3 text-sm leading-relaxed">
                {{ formDescription }}
            </p>
        </div>
    </div>
</template>
