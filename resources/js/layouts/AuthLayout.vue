<script setup lang="ts">
import 'vue-sonner/style.css';
import AuthIllustration from '@/components/modules/auth/AuthIllustration.vue';
import AuthOAuthButtons from '@/components/modules/auth/AuthOAuthButtons.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="grid min-h-dvh w-full grid-cols-1 bg-background lg:grid-cols-2">
        <!-- Left: Illustration -->
        <AuthIllustration />

        <!-- Right: Auth Forms -->
        <div class="flex min-h-dvh flex-col items-center justify-center overflow-y-auto px-6 py-12 sm:px-12">
            <div class="app-surface w-full max-w-md p-7 sm:p-8">
                <a href="/" class="group mb-9 inline-flex items-center gap-2.5">
                    <div class="grid size-10 place-items-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground shadow-xs transition-transform group-hover:-translate-y-px">
                        DF
                    </div>
                    <span class="font-display text-2xl font-bold tracking-[-0.02em] text-foreground">
                        D<span class="text-primary">Form</span>
                    </span>
                </a>

                <!-- Forms -->
                <slot></slot>

                <!-- OAuth -->
                <AuthOAuthButtons />

                <!-- Mobile footer -->
                <p class="mt-9 text-center text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground lg:hidden">
                    Created by Dinus Open Source Community
                </p>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <Toaster position="top-right" richColors />
</template>
