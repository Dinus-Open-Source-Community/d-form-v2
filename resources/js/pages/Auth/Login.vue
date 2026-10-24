<script lang="ts" setup>
import AuthLayout from '@/layouts/AuthLayout.vue';
import AuthLoginForm from '@/components/modules/auth/AuthLoginForm.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
</script>

<template>
    <AuthLayout>
        <SeoHead
            title="Masuk"
            description="Masuk ke akun DForm untuk mengelola acara, formulir, dan kehadiran."
            canonical-path="/auth/login"
            noindex
        />
        <AuthLoginForm />
    </AuthLayout>
</template>
