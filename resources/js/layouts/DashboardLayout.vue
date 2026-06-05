<script setup lang="ts">
import 'vue-sonner/style.css'
import { SidebarProvider, SidebarInset, SidebarTrigger } from '@/components/ui/sidebar'
import { Toaster } from '@/components/ui/sonner'
import DashboardSidebar from '@/components/modules/dashboard/DashboardSidebar.vue'
</script>

<template>
    <SidebarProvider>
        <DashboardSidebar />
        <SidebarInset class="h-svh overflow-x-hidden bg-gradient-to-b from-background via-muted/20 to-background">
            <SidebarTrigger
                class="fixed left-4 top-4 z-30 h-11! w-11! rounded-2xl border border-border/70 bg-card/95 shadow-lg shadow-black/5 backdrop-blur-xl md:hidden"
                aria-label="Buka sidebar"
            />
            <div class="flex-1 overflow-y-auto px-4 pb-10 pt-16 md:px-6 md:pb-12 md:pt-8 lg:px-8">
                <div class="w-full max-w-full">
                    <slot />
                </div>
            </div>
        </SidebarInset>
    </SidebarProvider>
    <Toaster position="top-right" richColors />
</template>
