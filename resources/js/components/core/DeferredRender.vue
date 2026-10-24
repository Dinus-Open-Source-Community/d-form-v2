<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

/** Props penunda render: konten baru dirender setelah elemen masuk viewport. */
interface IDeferredRenderProps {
    /** Jarak pandang sebelum elemen dianggap terlihat (default 200px). */
    rootMargin?: string
    /** Tinggi placeholder agar tidak ada layout shift sebelum konten dimuat. */
    minHeight?: string
}

const props = withDefaults(defineProps<IDeferredRenderProps>(), {
    rootMargin: '200px',
    minHeight: '12rem',
})

const root = ref<HTMLElement | null>(null)
const visible = ref(false)
let observer: IntersectionObserver | null = null

/** Baru merender slot setelah terlihat; SSR & render awal hanya placeholder (hydration-safe). */
onMounted(() => {
    if (typeof IntersectionObserver === 'undefined' || !root.value) {
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
        { rootMargin: props.rootMargin },
    )
    observer.observe(root.value)
})

onBeforeUnmount(() => {
    observer?.disconnect()
    observer = null
})
</script>

<template>
    <div ref="root">
        <slot v-if="visible" />
        <div
            v-else
            class="animate-pulse rounded-2xl bg-muted/60"
            :style="{ minHeight: props.minHeight }"
            aria-hidden="true"
        />
    </div>
</template>
