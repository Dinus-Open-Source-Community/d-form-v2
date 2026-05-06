<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { ImageOff } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = withDefaults(
    defineProps<{
        src?: string | null
        alt: string
        imgClass?: string
    }>(),
    { src: '', imgClass: '' },
)

const failed = ref(false)

function onError() {
    failed.value = true
}

watch(
    () => props.src,
    () => {
        failed.value = false
    },
)

const showImg = computed(() => Boolean(props.src) && !failed.value)

const resolvedSrc = computed(() => {
    if (!props.src) return ''
    const value = String(props.src)
    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/') || value.startsWith('data:')) {
        return value
    }

    return `/storage/${value.replace(/^storage\//, '')}`
})
</script>

<template>
    <div class="relative size-full min-h-20 bg-muted">
        <img
            v-if="showImg"
            :src="resolvedSrc"
            :alt="alt"
            loading="lazy"
            decoding="async"
            :class="cn('size-full object-cover', imgClass)"
            @error="onError"
        />
        <div
            v-else
            class="flex size-full min-h-[inherit] flex-col items-center justify-center gap-1.5 px-2 text-center text-muted-foreground"
        >
            <ImageOff class="size-6 shrink-0 opacity-50" :stroke-width="1.8" aria-hidden="true" />
            <span class="text-[10px] font-medium leading-tight">No banner</span>
        </div>
    </div>
</template>
