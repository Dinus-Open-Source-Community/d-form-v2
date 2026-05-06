<script setup lang="ts">
import LocalLottie from '@/components/core/LocalLottie.vue'
</script>

<template>
    <div class="relative hidden overflow-hidden border-r border-border bg-primary lg:flex lg:flex-col lg:items-center lg:justify-center">
        <div class="app-grid pointer-events-none absolute inset-0 opacity-20"></div>
        <div class="pointer-events-none absolute -right-16 top-20 h-80 w-80 rounded-full bg-white/15 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-10 left-10 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>

        <div class="relative z-10 mx-auto w-full max-w-md px-12">
            <div class="rounded-3xl border border-white/20 bg-white/95 p-8 shadow-sm backdrop-blur-sm">
                <div class="mb-6 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Secure access</p>
                        <p class="font-display mt-1 text-2xl font-bold tracking-[-0.02em] text-foreground">DForm Platform</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full border border-success/20 bg-success/10 px-2.5 py-1 text-[11px] font-semibold text-success">
                        <span class="size-1.5 rounded-full bg-success"></span>
                        Protected
                    </span>
                </div>
                <LocalLottie name="authSecure" :height="260" width="100%" :lazy="false" />
            </div>
        </div>

        <div class="relative z-10 mt-10 px-6 text-center">
            <p class="font-display text-3xl font-bold tracking-[-0.025em] text-white">DForm Platform</p>
            <p class="mt-3 inline-block rounded-full border border-white/25 bg-white/95 px-4 py-1.5 text-xs font-semibold tracking-[0.02em] text-primary">
                Created by Dinus Open Source Community
            </p>
        </div>
    </div>
</template>
