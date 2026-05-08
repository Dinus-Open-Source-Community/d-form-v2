<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { AuthSubmitButton } from '@/components/core/button'
import { AuthField } from '@/components/core/field'
import { index as loginPage } from '@/actions/App/Http/Controllers/Auth/LoginController'
import { store as resetPassword } from '@/actions/App/Http/Controllers/Auth/ResetPasswordController'
import { toast } from 'vue-sonner'

const props = defineProps<{
    token: string
    email: string
}>()

const page = usePage()

const form = useForm({
    email: props.email,
    password: '',
    password_confirmation: '',
}).dontRemember('password', 'password_confirmation')

function submit(): void {
    if (form.password.length < 8) {
        toast.error('Password must be at least 8 characters.')
        return
    }
    if (form.password !== form.password_confirmation) {
        toast.error('Password does not match.')
        return
    }

    form.submit(resetPassword(props.token), {
        onFinish: () => {
            const t = page.flash.toast
            if (!t) return
            if (t.type === 'success') {
                toast.success(t.message)
            } else {
                toast.error(t.message)
            }
        },
    })
}
</script>

<template>
    <div>
        <div class="mb-8">
            <h1 class="font-display text-3xl font-bold tracking-[-0.03em] text-foreground">Choose a new password</h1>
            <p class="mt-2 text-sm text-muted-foreground">Enter and confirm your new password below.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <AuthField
                type="email"
                :error="form.errors.email"
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
                :error="form.errors.password"
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
                :error="form.errors.password_confirmation"
                label="Confirm new password"
                id="reset-password-confirmation"
                v-model="form.password_confirmation"
                required
                autocomplete="new-password"
                placeholder="••••••••"
                :focus="false"
            />

            <AuthSubmitButton :form="form">
                <template #processing>Updating password</template>
                Update password
            </AuthSubmitButton>

            <p class="mt-2 text-center text-sm text-muted-foreground">
                <Link
                    :href="loginPage()"
                    class="font-semibold text-primary underline-offset-4 transition-colors hover:underline"
                >
                    Back to sign in
                </Link>
            </p>
        </form>
    </div>
</template>
