<script setup lang="ts">
import { computed } from 'vue';
import { PASSWORD_STRENGTH_MAX_SEGMENTS, passwordStrengthSegmentCount } from '@/lib/passwordStrength';

/**
 * Indikator kekuatan kata sandi baru: empat segmen terisi kiri→kanan plus satu
 * baris hint. Selalu tampil (juga saat kosong) agar tinggi layout tidak melompat.
 */
const props = defineProps<{
    password: string;
    hintId: string;
    hint: string;
}>();

const filledSegmentCount = computed<number>(() => passwordStrengthSegmentCount(props.password));
</script>

<template>
    <div class="space-y-2">
        <div
            class="flex gap-1.5"
            role="progressbar"
            aria-label="Kekuatan kata sandi"
            :aria-describedby="hintId"
            aria-valuemin="0"
            :aria-valuemax="PASSWORD_STRENGTH_MAX_SEGMENTS"
            :aria-valuenow="filledSegmentCount"
        >
            <span
                v-for="segment in PASSWORD_STRENGTH_MAX_SEGMENTS"
                :key="segment"
                class="h-1 flex-1 rounded-full transition-colors duration-300 ease-out"
                :class="segment <= filledSegmentCount ? 'bg-success' : 'bg-muted'"
            />
        </div>
        <p :id="hintId" class="text-muted-foreground text-xs">{{ hint }}</p>
    </div>
</template>
