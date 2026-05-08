<script lang="ts" setup>
import AuthLayout from '@/layouts/AuthLayout.vue'
import AuthResetPasswordForm from '@/components/modules/auth/AuthResetPasswordForm.vue'

defineProps<{
    token: string
    email: string
}>()
</script>

<template>
    <AuthLayout>
        <AuthResetPasswordForm :token="token" :email="email" />
    </AuthLayout>
</template>
