<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { AuthSubmitButton } from '@/components/core/button'
import { AuthField } from '@/components/core/field'
import { store as login } from '@/actions/App/Http/Controllers/Auth/LoginController'
import { index as forgotPasswordPage } from '@/actions/App/Http/Controllers/Auth/ForgotPasswordController'
import { getFieldError, handleInertiaFormErrors } from '@/lib/error-message'

const form = useForm({ email: '', password: '' }).dontRemember('password')

function submit(): void {
    form.submit(login(), {
        onError: (errors) => {
            handleInertiaFormErrors(errors, { title: 'Gagal masuk' })
        },
    })
}
</script>

<template>
    <div>
        <div class="mb-8">
            <h1 class="font-display text-[2rem] leading-tight font-bold tracking-[-0.035em] text-foreground sm:text-4xl">
                Selamat datang kembali
            </h1>
            <p class="mt-2.5 text-sm text-muted-foreground">Masuk untuk mengelola acara, formulir, dan kehadiran.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <AuthField
                type="email"
                :error="getFieldError(form.errors, 'email')"
                label="Email"
                id="login-email"
                v-model="form.email"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :focus="true"
            />
            <AuthField
                type="password"
                :error="getFieldError(form.errors, 'password')"
                label="Password"
                id="login-password"
                v-model="form.password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                :focus="false"
            />

            <div class="flex justify-end">
                <Link
                    :href="forgotPasswordPage()"
                    class="text-sm font-semibold text-primary underline-offset-4 transition-colors hover:underline"
                >
                    Lupa kata sandi?
                </Link>
            </div>

            <AuthSubmitButton :form="form">
                <template #processing>Sedang masuk</template>
                Masuk
            </AuthSubmitButton>
        </form>
    </div>
</template>
