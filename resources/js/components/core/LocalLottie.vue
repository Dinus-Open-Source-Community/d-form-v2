<script setup lang="ts">
import { computed } from 'vue'
import { Vue3Lottie } from 'vue3-lottie'
import { lotties, type LottieName } from '@/lib/lotties'

const props = withDefaults(
    defineProps<{
        name: LottieName
        height?: number | string
        width?: number | string
        loop?: boolean | number
        autoPlay?: boolean
    }>(),
    {
        height: 160,
        width: 160,
        loop: true,
        autoPlay: true,
    },
)

const animationData = computed(() => lotties[props.name])
</script>

<template>
    <Vue3Lottie
        :animation-data="animationData"
        :height="height"
        :width="width"
        :loop="loop"
        :auto-play="autoPlay"
        :no-margin="true"
    />
</template>
