<script setup lang="ts">
import { computed } from 'vue';
import type { IStatePanelProps, TStateIllustration } from '@/types/state';

interface IIllustrationMeta {
    readonly src: string;
    readonly width: number;
    readonly height: number;
}

const props = withDefaults(defineProps<IStatePanelProps>(), {
    description: undefined,
    variant: 'inline',
    headingTag: 'p',
    surface: true,
});

const ILLUSTRATIONS: Readonly<Record<TStateIllustration, IIllustrationMeta>> = {
    error: { src: '/illustrations/state-error.svg', width: 799, height: 618 },
    empty: { src: '/illustrations/state-empty.svg', width: 648, height: 632 },
};

const isPage = computed<boolean>(() => props.variant === 'page');

const illustration = computed<IIllustrationMeta>(() => ILLUSTRATIONS[props.illustration]);

const wrapperClass = computed<string>(() => {
    if (isPage.value) return 'mx-auto flex w-full max-w-lg flex-col items-center text-center';

    const base = 'flex flex-col items-center justify-center px-6';

    return props.surface ? `app-surface rounded-2xl py-14 ${base} text-center` : `py-10 ${base} text-center`;
});

const imageClass = computed<string>(() =>
    isPage.value ? 'h-auto w-full max-w-[20rem] sm:max-w-[22rem]' : 'mb-2 h-auto w-full max-w-[11.25rem]'
);

const headingClass = computed<string>(() =>
    isPage.value
        ? 'font-display text-foreground mt-8 text-2xl font-semibold tracking-tight sm:text-3xl'
        : 'font-display text-foreground text-base font-bold tracking-[-0.015em]'
);

const descriptionClass = computed<string>(() =>
    isPage.value
        ? 'text-muted-foreground mt-4 max-w-md text-sm leading-relaxed text-pretty sm:text-base'
        : 'text-muted-foreground mt-2 max-w-sm text-sm leading-relaxed'
);

const actionsClass = computed<string>(() => (isPage.value ? 'mt-10' : 'mt-5'));
</script>

<template>
    <div :class="wrapperClass">
        <img
            :src="illustration.src"
            :width="illustration.width"
            :height="illustration.height"
            :loading="isPage ? 'eager' : 'lazy'"
            :fetchpriority="isPage ? 'high' : 'auto'"
            decoding="async"
            alt=""
            aria-hidden="true"
            :class="imageClass"
        />

        <component :is="headingTag" :class="headingClass">{{ title }}</component>

        <p v-if="description" :class="descriptionClass">{{ description }}</p>

        <div v-if="$slots.default" :class="actionsClass">
            <slot />
        </div>
    </div>
</template>
