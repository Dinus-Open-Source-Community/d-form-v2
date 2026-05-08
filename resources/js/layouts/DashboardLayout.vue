<script setup lang="ts">
import 'vue-sonner/style.css'
import { SidebarProvider, SidebarInset, SidebarTrigger } from '@/components/ui/sidebar'
import { Toaster } from '@/components/ui/sonner'
import DashboardSidebar from '@/components/modules/dashboard/DashboardSidebar.vue'
</script>

<template>
    <SidebarProvider>
        <DashboardSidebar />
        <SidebarInset class="h-svh bg-gradient-to-b from-background via-muted/20 to-background">
            <header
                class="border-border/60 bg-card/90 sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b px-4 backdrop-blur-xl md:hidden"
            >
                <SidebarTrigger />
                <div class="min-w-0 flex-1">
                    <p class="font-display truncate text-sm font-bold tracking-tight">
                        D<span class="text-primary">Form</span>
                    </p>
                    <p class="text-muted-foreground truncate text-[10px] font-medium">Panel pengelola</p>
                </div>
            </header>
            <div class="flex-1 overflow-y-auto px-4 pb-10 pt-5 md:px-6 md:pb-12 md:pt-8 lg:px-8">
                <div class="w-full max-w-full">
                    <slot />
                </div>
            </div>
        </SidebarInset>
    </SidebarProvider>
    <Toaster position="top-right" richColors />
</template>
