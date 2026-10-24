<script lang="ts" setup>
import AuthLayout from '@/layouts/AuthLayout.vue'
import AuthForgotPasswordForm from '@/components/modules/auth/AuthForgotPasswordForm.vue'
import SeoHead from '@/components/seo/SeoHead.vue'
</script>

<template>
    <AuthLayout>
        <SeoHead
            title="Lupa kata sandi"
            description="Minta tautan untuk mengatur ulang kata sandi akun DForm."
            canonical-path="/auth/forgot-password"
            noindex
        />
        <AuthForgotPasswordForm />
    </AuthLayout>
</template>
