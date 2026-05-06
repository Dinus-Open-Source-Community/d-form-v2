<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Vue3Lottie } from 'vue3-lottie'
import { lotties, type LottieName } from '@/lib/lotties'
import type { LocalLottieProps } from '@/types/lottie'

const props = withDefaults(
    defineProps<Omit<LocalLottieProps, 'name'> & { name?: LottieName }>(),
    {
        name: undefined,
        src: undefined,
        animationLink: '',
        height: 200,
        width: 200,
        loop: true,
        autoPlay: true,
        speed: 1,
        lazy: true,
    },
)

const root = ref<HTMLElement | null>(null)
const visible = ref(false)
let observer: IntersectionObserver | null = null

const resolvedLink = computed<string>(() => {
    if (props.animationLink) return props.animationLink
    if (props.src) return props.src
    if (props.name && lotties[props.name]) return lotties[props.name].src
    return ''
})

const ariaLabel = computed<string>(() => {
    if (props.name && lotties[props.name]) return lotties[props.name].label
    return 'Animated illustration'
})

const sizeStyle = computed(() => {
    const toCss = (v: number | string): string => (typeof v === 'number' ? `${v}px` : v)
    return {
        width: toCss(props.width),
        height: toCss(props.height),
    }
})

onMounted(() => {
    if (!props.lazy) {
        visible.value = true
        return
    }
    if (typeof IntersectionObserver === 'undefined') {
        visible.value = true
        return
    }
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    visible.value = true
                    observer?.disconnect()
                    observer = null
                    break
                }
            }
        },
        { rootMargin: '120px' },
    )
    if (root.value) observer.observe(root.value)
})

onBeforeUnmount(() => {
    observer?.disconnect()
    observer = null
})

watch(
    () => props.lazy,
    (v) => {
        if (!v) visible.value = true
    },
)
</script>

<template>
    <div
        ref="root"
        :style="sizeStyle"
        :aria-label="ariaLabel"
        role="img"
        class="relative inline-flex items-center justify-center"
    >
        <Vue3Lottie
            v-if="visible && resolvedLink"
            :animation-link="resolvedLink"
            :height="height"
            :width="width"
            :loop="loop"
            :auto-play="autoPlay"
            :speed="speed"
            :no-margin="true"
        />
        <div
            v-else
            class="h-full w-full animate-pulse rounded-2xl bg-gradient-to-br from-muted to-muted/40"
            aria-hidden="true"
        />
    </div>
</template>
