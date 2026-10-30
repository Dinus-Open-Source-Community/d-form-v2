<script setup lang="ts">
import 'vue-sonner/style.css'
import { computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { SidebarProvider, SidebarInset } from '@/components/ui/sidebar'
import { Toaster } from '@/components/ui/sonner'
import DashboardSidebar from '@/components/modules/dashboard/DashboardSidebar.vue'
import DashboardTopbar from '@/components/modules/dashboard/DashboardTopbar.vue'
import { usePageFlashToast } from '@/utils/composables/usePageFlashToast'
import { useHttpErrorToast } from '@/utils/composables/useHttpErrorToast'
import { clearTopbar } from '@/utils/composables/useDashboardTopbar'

usePageFlashToast()

// Toast untuk submit yang gagal dan hanya berakhir di halaman Error (mis. 403/500).
useHttpErrorToast()

// Reset state topbar saat navigasi Inertia ke halaman lain,
// supaya judul/subtitle halaman sebelumnya tidak bocor.
const page = usePage()
watch(
    () => page.component,
    () => {
        clearTopbar()
    },
)

/** Halaman builder formulir butuh lebar penuh, jadi sidebar navigasi tidak dirender (editor & tab jawaban). */
const FULL_WIDTH_PAGE_COMPONENTS: ReadonlySet<string> = new Set([
    'Dashboard/Events/Forms/Show',
    'Dashboard/Events/Forms/Create',
])

const showSidebar = computed<boolean>(() => !FULL_WIDTH_PAGE_COMPONENTS.has(page.component))
const isFullWidthPage = computed<boolean>(() => FULL_WIDTH_PAGE_COMPONENTS.has(page.component))
</script>

<template>
    <SidebarProvider data-testid="authenticated-shell">
        <DashboardSidebar v-if="showSidebar" />
        <SidebarInset class="h-svh overflow-x-hidden bg-gradient-to-b from-background via-muted/20 to-background">
            <DashboardTopbar :show-navigation-trigger="showSidebar" />
            <div
                data-testid="dashboard-content"
                :class="[
                    'min-h-0 flex-1 px-4 pb-10 pt-6 md:px-6 md:pb-12 md:pt-8 lg:px-8',
                    isFullWidthPage ? 'overflow-hidden' : 'overflow-y-auto',
                ]"
            >
                <div :class="['w-full max-w-full', isFullWidthPage && 'h-full min-h-0']">
                    <slot />
                </div>
            </div>
        </SidebarInset>
    </SidebarProvider>
    <Toaster position="top-right" richColors />
</template>
