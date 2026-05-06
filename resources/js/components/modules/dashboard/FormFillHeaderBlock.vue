<script setup lang="ts">
defineProps<{
    formTitle: string
    formDescription: string
    formHasDescription: boolean
    formBannerImageSrc: string
    formBannerCaption: string
}>()
</script>

<template>
    <div>
        <div
            v-if="formBannerImageSrc || formBannerCaption"
            class="mb-10 overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
        >
            <img
                v-if="formBannerImageSrc"
                :src="formBannerImageSrc"
                :alt="formTitle"
                class="aspect-[3/1] w-full object-cover"
            />
            <p
                v-if="formBannerCaption"
                class="border-t border-border px-5 py-4 text-sm leading-relaxed text-muted-foreground"
            >
                {{ formBannerCaption }}
            </p>
        </div>

        <div class="mb-10 text-center">
            <h1 class="font-display text-balance text-4xl font-bold tracking-[-0.035em] text-foreground">{{ formTitle }}</h1>
            <p v-if="formHasDescription" class="mt-3 text-sm leading-relaxed text-muted-foreground">{{ formDescription }}</p>
        </div>
    </div>
</template>
