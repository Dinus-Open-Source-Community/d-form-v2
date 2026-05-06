<script setup lang="ts">
import { useForm, Link, usePage } from '@inertiajs/vue3'
import { AuthSubmitButton } from '@/components/core/button'
import { AuthField } from '@/components/core/field'
import { store as login } from '@/actions/App/Http/Controllers/Auth/LoginController'
import { index as registerPage } from '@/actions/App/Http/Controllers/Auth/RegisterController'
import { toast } from 'vue-sonner'

const page = usePage()

const form = useForm({ email: '', password: '' }).dontRemember('password')

function submit(): void {
    form.submit(login(), {
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
            <h1 class="font-display text-3xl font-bold tracking-[-0.03em] text-foreground">Welcome back</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Don't have an account?
                <Link
                    :href="registerPage()"
                    class="font-semibold text-primary underline-offset-4 transition-colors hover:underline"
                >
                    Sign up
                </Link>
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <AuthField
                type="email"
                :error="form.errors.email"
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
                :error="form.errors.password"
                label="Password"
                id="login-password"
                v-model="form.password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                :focus="false"
            />

            <AuthSubmitButton :form="form">
                <template #processing>Signing you in</template>
                Sign in
            </AuthSubmitButton>
        </form>
    </div>
</template>
