<script lang="ts" setup>
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import AuthTabs from '@/components/modules/auth/AuthTabs.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import { useAuthTabs } from '@/hooks/useAuthTabs';
import { getAuthTab } from '@/lib/authTabs';
import type { IAuthTabConfig } from '@/lib/authTabs';

const { activeTab, setActiveTab } = useAuthTabs({ initialTab: 'signin' });

// Judul, deskripsi, dan canonical ikut tab aktif supaya URL hasil switch tetap konsisten.
const seo: ComputedRef<IAuthTabConfig> = computed(() => getAuthTab(activeTab.value));
</script>

<template>
    <AuthLayout>
        <SeoHead
            :title="seo.title"
            :description="seo.description"
            :canonical-path="seo.path"
            noindex
        />
        <AuthTabs :active-tab="activeTab" @update:active-tab="setActiveTab" />
    </AuthLayout>
</template>
