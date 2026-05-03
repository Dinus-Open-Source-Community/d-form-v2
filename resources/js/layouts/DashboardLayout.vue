<script setup lang="ts">
import 'vue-sonner/style.css'
import { SidebarProvider, SidebarInset, SidebarTrigger } from '@/components/ui/sidebar'
import { Toaster } from '@/components/ui/sonner'
import DashboardSidebar from '@/components/modules/dashboard/DashboardSidebar.vue'
</script>

<template>
    <SidebarProvider>
        <DashboardSidebar />
        <SidebarInset class="bg-background">
            <header class="sticky top-0 z-20 flex h-14 shrink-0 items-center border-b-[1.5px] border-[var(--brutal-ink)]/15 bg-white/95 px-4 shadow-[var(--shadow-xs)] backdrop-blur-lg md:hidden">
                <SidebarTrigger class="-ml-1" />
            </header>
            <main class="flex-1 overflow-y-auto px-4 pb-8 pt-4 md:pt-6 lg:px-8">
                <div class="mx-auto max-w-screen-2xl">
                    <slot />
                </div>
            </main>
        </SidebarInset>
    </SidebarProvider>
    <Toaster position="top-right" richColors />
</template>
