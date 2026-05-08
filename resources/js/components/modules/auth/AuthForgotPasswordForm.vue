<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { AuthSubmitButton } from '@/components/core/button'
import { AuthField } from '@/components/core/field'
import { index as loginPage } from '@/actions/App/Http/Controllers/Auth/LoginController'
import { store as forgotPassword } from '@/actions/App/Http/Controllers/Auth/ForgotPasswordController'
import { toast } from 'vue-sonner'

const page = usePage()

const form = useForm({ email: '' })

function submit(): void {
    form.submit(forgotPassword(), {
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
            <h1 class="font-display text-3xl font-bold tracking-[-0.03em] text-foreground">Reset password</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Enter your email and we will send you a link if an account exists.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <AuthField
                type="email"
                :error="form.errors.email"
                label="Email"
                id="forgot-email"
                v-model="form.email"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :focus="true"
            />

            <AuthSubmitButton :form="form">
                <template #processing>Sending link</template>
                Send reset link
            </AuthSubmitButton>

            <p class="mt-2 text-center text-sm text-muted-foreground">
                Remember your password?
                <Link
                    :href="loginPage()"
                    class="font-semibold text-primary underline-offset-4 transition-colors hover:underline"
                >
                    Sign in
                </Link>
            </p>
        </form>
    </div>
</template>
