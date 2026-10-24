<script lang="ts" setup>
import AuthLayout from '@/layouts/AuthLayout.vue'
import AuthResetPasswordForm from '@/components/modules/auth/AuthResetPasswordForm.vue'
import SeoHead from '@/components/seo/SeoHead.vue'

defineProps<{
    token: string
    email: string
}>()
</script>

<template>
    <AuthLayout>
        <SeoHead
            title="Atur ulang kata sandi"
            description="Buat kata sandi baru untuk akun DForm."
            canonical-path="/auth/reset-password"
            noindex
        />
        <AuthResetPasswordForm :token="token" :email="email" />
    </AuthLayout>
</template>
