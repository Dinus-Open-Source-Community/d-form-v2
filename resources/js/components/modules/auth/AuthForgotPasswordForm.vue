<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { ref } from 'vue'
import { AuthField } from '@/components/core/field'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { index as loginPage } from '@/actions/App/Http/Controllers/Auth/LoginController'
import { toast } from 'vue-sonner'
import { routes } from '@/lib/routes'

const PASSWORD_RESET_LINK_URL = routes.auth.passwordResetLink

const email = ref('')
const emailError = ref<string | undefined>()
const processing = ref(false)

async function submit(): Promise<void> {
    if (processing.value) {
        return
    }

    emailError.value = undefined
    processing.value = true

    try {
        const { data } = await axios.post<{ message: string }>(PASSWORD_RESET_LINK_URL, {
            email: email.value,
        })
        toast.success(data.message)
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const errors = error.response.data?.errors as Record<string, string[]> | undefined
            emailError.value = errors?.email?.[0]
            return
        }

        toast.error('Unable to send reset link. Please try again.')
    } finally {
        processing.value = false
    }
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
                :error="emailError"
                label="Email"
                id="forgot-email"
                v-model="email"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :focus="true"
            />

            <Button size="lg" class="w-full disabled:cursor-not-allowed" :disabled="processing">
                <span v-if="processing" class="flex items-center gap-2">
                    <Spinner />
                    Sending link
                </span>
                <span v-else>Send reset link</span>
            </Button>

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
