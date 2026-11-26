<script setup lang="ts">
defineProps<{
    formTitle: string;
    formDescription: string;
    formHasDescription: boolean;
    formBannerImageSrc: string;
}>();
</script>

<template>
    <div>
        <div
            v-if="formBannerImageSrc.trim()"
            class="border-border bg-muted mx-auto mb-5 w-full max-w-4xl overflow-hidden rounded-2xl border shadow-sm"
        >
            <img
                :src="formBannerImageSrc"
                :alt="formTitle"
                width="1200"
                height="400"
                class="block h-56 w-full object-cover sm:h-72"
            />
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
