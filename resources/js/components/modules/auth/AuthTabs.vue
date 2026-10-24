<script setup lang="ts">
import AuthLoginForm from '@/components/modules/auth/AuthLoginForm.vue';
import AuthRegisterForm from '@/components/modules/auth/AuthRegisterForm.vue';
import AuthOAuthButtons from '@/components/modules/auth/AuthOAuthButtons.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { authTabs, isAuthTab } from '@/lib/authTabs';
import type { TAuthTab } from '@/lib/authTabs';

const { activeTab } = defineProps<{ activeTab: TAuthTab }>();
const emit = defineEmits<{ 'update:activeTab': [tab: TAuthTab] }>();

/** Teruskan tab pilihan pengguna (klik atau panah keyboard) ke pemilik state di halaman. */
function selectTab(value: string): void {
    if (isAuthTab(value)) emit('update:activeTab', value);
}
</script>

<template>
    <div>
        <!-- Kedua panel tetap ter-mount (force-mount) agar isian form tidak hilang saat pindah tab. -->
        <Tabs :model-value="activeTab" :unmount-on-hide="false" @update:model-value="selectTab">
            <TabsList
                class="flex h-auto w-full items-stretch rounded-none border-0 border-b border-border bg-transparent p-0"
            >
                <TabsTrigger
                    v-for="tab in authTabs"
                    :key="tab.value"
                    :value="tab.value"
                    class="-mb-px flex h-auto flex-1 items-center justify-center rounded-none border-0 border-b-[3px] border-transparent bg-transparent px-2 py-3 text-base font-semibold text-muted-foreground shadow-none transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none sm:text-lg"
                >
                    {{ tab.label }}
                </TabsTrigger>
            </TabsList>

            <!-- Kedua panel menempati satu sel grid: tinggi sel mengikuti panel tertinggi (Daftar),
                 jadi tablist dan judul tidak bergeser saat pindah tab. Panel non-aktif tetap
                 ter-mount (state form aman) tetapi invisible + inert + aria-hidden. -->
            <div class="grid">
                <TabsContent
                    value="signin"
                    force-mount
                    :inert="activeTab !== 'signin' ? true : undefined"
                    :aria-hidden="activeTab !== 'signin' ? 'true' : undefined"
                    class="col-start-1 row-start-1 mt-0 rounded-xl pt-6 focus-visible:ring-2 focus-visible:ring-ring/30 focus-visible:ring-offset-0 data-[state=inactive]:invisible"
                >
                    <AuthLoginForm />
                    <AuthOAuthButtons />
                </TabsContent>

                <TabsContent
                    value="signup"
                    force-mount
                    :inert="activeTab !== 'signup' ? true : undefined"
                    :aria-hidden="activeTab !== 'signup' ? 'true' : undefined"
                    class="col-start-1 row-start-1 mt-0 rounded-xl pt-6 focus-visible:ring-2 focus-visible:ring-ring/30 focus-visible:ring-offset-0 data-[state=inactive]:invisible"
                >
                    <AuthRegisterForm />
                    <AuthOAuthButtons />
                </TabsContent>
            </div>
        </Tabs>
    </div>
</template>
