<script setup lang="ts">
import 'vue-sonner/style.css';
import { DFORM_WORDMARK_SRC } from '@/lib/brand';
import { Toaster } from '@/components/ui/sonner';
import { routes } from '@/lib/routes';
import { usePageFlashToast } from '@/utils/composables/usePageFlashToast';

usePageFlashToast();
</script>

<template>
    <div class="relative min-h-dvh w-full">
        <header class="absolute inset-x-0 top-0 z-10 w-full px-5 pt-7 sm:px-8 sm:pt-9">
            <div class="mx-auto flex w-full max-w-xl items-center justify-between gap-4">
                <a :href="routes.home" class="inline-flex items-center">
                    <img
                        :src="DFORM_WORDMARK_SRC"
                        alt="DForm"
                        class="h-9 w-auto select-none sm:h-10"
                        width="113"
                        height="49"
                    />
                </a>
                <a
                    :href="routes.home"
                    class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                >
                    Beranda
                </a>
            </div>
        </header>

        <!-- Anchor vertikal: blok auth di tengah; padding atas khusus mobile agar tidak menabrak header. -->
        <main class="flex min-h-dvh w-full items-center justify-center px-5 pt-24 pb-12 sm:px-8 sm:pt-20 sm:pb-12 lg:py-16">
            <div class="w-full max-w-xl motion-safe:animate-app-fade-in">
                <slot></slot>
            </div>
        </main>
    </div>

    <!-- Toast -->
    <Toaster position="top-right" richColors />
</template>
