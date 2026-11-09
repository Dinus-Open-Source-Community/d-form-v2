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
            v-if="formBannerImageSrc || formBannerCaption"
            class="border-border bg-card mb-5 overflow-hidden rounded-2xl border shadow-sm"
        >
            <img
                v-if="formBannerImageSrc"
                :src="formBannerImageSrc"
                :alt="formTitle"
                class="aspect-[3/1] w-full object-cover"
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
