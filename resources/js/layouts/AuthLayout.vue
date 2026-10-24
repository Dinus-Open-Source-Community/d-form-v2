<script setup lang="ts">
import 'vue-sonner/style.css';
import { DFORM_WORDMARK_SRC } from '@/lib/brand';
import { Toaster } from '@/components/ui/sonner';
import { routes } from '@/lib/routes';
import { usePageFlashToast } from '@/utils/composables/usePageFlashToast';

usePageFlashToast();
</script>

<template>
    <div class="relative flex min-h-dvh flex-col overflow-x-hidden">
        <!-- Atmosfer halaman: cahaya lembut + kisi tipis, tanpa panel/kartu di belakang form. -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-52 left-1/2 size-[34rem] -translate-x-1/2 rounded-full bg-primary/10 blur-[110px]" />
            <div class="absolute -right-40 -bottom-48 size-[26rem] rounded-full bg-primary/5 blur-[110px]" />
            <div
                class="absolute inset-0 opacity-[0.35] [background-image:linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] [background-size:96px_96px] [mask-image:radial-gradient(70%_55%_at_50%_28%,black,transparent)]"
            />
        </div>

        <header class="relative z-10 w-full px-5 pt-7 sm:px-8 sm:pt-9">
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

        <main class="relative z-10 flex w-full flex-1 items-start justify-center px-5 pt-10 pb-14 sm:px-8 sm:pt-14">
            <div class="w-full max-w-xl motion-safe:animate-app-fade-in">
                <slot></slot>
            </div>
        </main>

        <footer class="relative z-10 w-full px-5 pb-8 sm:px-8">
            <div class="mx-auto w-full max-w-xl border-t border-border/60 pt-5">
                <p class="text-center text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
                    Created by Dinus Open Source Community
                </p>
            </div>
        </footer>
    </div>

    <!-- Toast -->
    <Toaster position="top-right" richColors />
</template>
