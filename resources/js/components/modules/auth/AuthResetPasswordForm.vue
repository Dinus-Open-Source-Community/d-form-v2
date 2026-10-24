<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { AuthSubmitButton } from '@/components/core/button'
import { AuthField } from '@/components/core/field'
import { index as loginPage } from '@/actions/App/Http/Controllers/Auth/LoginController'
import { store as resetPassword } from '@/actions/App/Http/Controllers/Auth/ResetPasswordController'
import { getFieldError, handleInertiaFormErrors, showErrorToast } from '@/lib/error-message'

const props = defineProps<{
    token: string
    email: string
}>()

const form = useForm({
    email: props.email,
    password: '',
    password_confirmation: '',
}).dontRemember('password', 'password_confirmation')

function submit(): void {
    if (form.password.length < 8) {
        showErrorToast('Password must be at least 8 characters.')
        return
    }
    if (form.password !== form.password_confirmation) {
        showErrorToast('Password does not match.')
        return
    }

    form.submit(resetPassword(props.token), {
        onError: (errors) => {
            handleInertiaFormErrors(errors, { title: 'Gagal mengatur ulang kata sandi' })
        },
    })
}
</script>

<template>
    <div>
        <div class="mb-8">
            <h1 class="font-display text-[2rem] leading-tight font-bold tracking-[-0.035em] text-foreground sm:text-4xl">
                Pilih kata sandi baru
            </h1>
            <p class="mt-2.5 text-sm text-muted-foreground">Masukkan dan konfirmasi kata sandi baru Anda.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <AuthField
                type="email"
                :error="getFieldError(form.errors, 'email')"
                label="Email"
                id="reset-email"
                v-model="form.email"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :focus="true"
            />
            <AuthField
                type="password"
                :error="getFieldError(form.errors, 'password')"
                label="New password"
                id="reset-password"
                v-model="form.password"
                required
                autocomplete="new-password"
                placeholder="••••••••"
                :focus="false"
            />
            <AuthField
                type="password"
                :error="getFieldError(form.errors, 'password_confirmation')"
                label="Confirm new password"
                id="reset-password-confirmation"
                v-model="form.password_confirmation"
                required
                autocomplete="new-password"
                placeholder="••••••••"
                :focus="false"
            />

            <AuthSubmitButton :form="form">
                <template #processing>Memperbarui kata sandi</template>
                Perbarui kata sandi
            </AuthSubmitButton>

            <p class="mt-2 text-center text-sm text-muted-foreground">
                <Link
                    :href="loginPage()"
                    class="font-semibold text-primary underline-offset-4 transition-colors hover:underline"
                >
                    Kembali ke halaman masuk
                </Link>
            </p>
        </form>
    </div>
</template>
