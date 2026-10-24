<script lang="ts" setup>
import AuthLayout from '@/layouts/AuthLayout.vue';
import AuthRegisterForm from '@/components/modules/auth/AuthRegisterForm.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
</script>

<template>
    <AuthLayout>
        <SeoHead
            title="Daftar"
            description="Buat akun DForm untuk mengikuti acara dan mengelola pendaftaran."
            canonical-path="/auth/register"
            noindex
        />
        <AuthRegisterForm />
    </AuthLayout>
</template>
