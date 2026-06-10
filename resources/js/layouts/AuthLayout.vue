<script setup lang="ts">
import 'vue-sonner/style.css';
import AuthIllustration from '@/components/modules/auth/AuthIllustration.vue';
import AuthOAuthButtons from '@/components/modules/auth/AuthOAuthButtons.vue';
import { Toaster } from '@/components/ui/sonner';
import { routes } from '@/lib/routes';
</script>

<template>
    <div class="grid min-h-dvh w-full grid-cols-1 bg-background lg:grid-cols-2">
        <!-- Left: Illustration -->
        <AuthIllustration />

        <!-- Right: Auth Forms -->
        <div class="flex min-h-dvh flex-col items-center justify-center overflow-y-auto px-6 py-12 sm:px-12">
            <div class="app-surface w-full max-w-md p-7 sm:p-8">
                <a :href="routes.home" class="group mb-9 inline-block transition-transform hover:-translate-y-px">
                    <img
                        :src="`/${encodeURIComponent('DForm 1.png')}`"
                        alt="DForm"
                        class="h-10 w-auto select-none"
                        width="160"
                        height="40"
                    />
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
