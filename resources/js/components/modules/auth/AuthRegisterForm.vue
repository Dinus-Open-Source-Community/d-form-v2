<script setup lang="ts">
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Check, Circle } from 'lucide-vue-next'
import { AuthField } from '@/components/core/field'
import { AuthSubmitButton } from '@/components/core/button'
import { index as loginPage } from '@/actions/App/Http/Controllers/Auth/LoginController'
import { store as register } from '@/actions/App/Http/Controllers/Auth/RegisterController'
import { toast } from 'vue-sonner'
import type { PasswordRule, PasswordStrength } from '@/types/auth'

const page = usePage()

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
}).dontRemember('password', 'password_confirmation')

const rules = computed<PasswordRule[]>(() => [
    { label: 'At least 8 characters', met: form.password.length >= 8 },
    { label: 'Contains a number', met: /\d/.test(form.password) },
    { label: 'Contains uppercase', met: /[A-Z]/.test(form.password) },
    {
        label: 'Passwords match',
        met: form.password.length > 0 && form.password === form.password_confirmation,
    },
])

const strengthPercent = computed<number>(() => {
    const met = rules.value.filter((r) => r.met).length
    return Math.round((met / rules.value.length) * 100)
})

const strengthKind = computed<PasswordStrength>(() => {
    if (strengthPercent.value <= 25) return 'weak'
    if (strengthPercent.value <= 50) return 'fair'
    if (strengthPercent.value <= 75) return 'good'
    return 'strong'
})

const strengthLabel = computed<string>(() => {
    const map: Record<PasswordStrength, string> = {
        weak: 'Weak',
        fair: 'Fair',
        good: 'Good',
        strong: 'Strong',
    }
    return map[strengthKind.value]
})

const strengthBarClass = computed<string>(() => {
    const map: Record<PasswordStrength, string> = {
        weak: 'bg-destructive',
        fair: 'bg-warning',
        good: 'bg-primary',
        strong: 'bg-success',
    }
    return map[strengthKind.value]
})

const strengthBadgeClass = computed<string>(() => {
    const map: Record<PasswordStrength, string> = {
        weak: 'border-destructive/25 bg-destructive/10 text-destructive',
        fair: 'border-warning/30 bg-warning/15 text-warning-foreground',
        good: 'border-primary/25 bg-primary/10 text-primary',
        strong: 'border-success/25 bg-success/10 text-success',
    }
    return map[strengthKind.value]
})

function submit(): void {
    if (form.password.length < 8) {
        toast.error('Password must be at least 8 characters.')
        return
    }
    if (form.password !== form.password_confirmation) {
        toast.error('Password does not match')
        return
    }

    form.submit(register(), {
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
            <h1 class="font-display text-3xl font-bold tracking-[-0.03em] text-foreground">Create account</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Start building forms in under a minute.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <AuthField
                label="Name"
                type="name"
                :error="form.errors.name"
                id="register-name"
                v-model="form.name"
                required
                autocomplete="name"
                placeholder="Your name"
                :focus="true"
            />
            <AuthField
                label="Email"
                type="email"
                :error="form.errors.email"
                id="register-email"
                v-model="form.email"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :focus="false"
            />
            <AuthField
                label="Password"
                type="password"
                :error="form.errors.password"
                id="register-password"
                v-model="form.password"
                required
                autocomplete="new-password"
                placeholder="••••••••"
                :focus="false"
            />

            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="-translate-y-1 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="-translate-y-1 opacity-0"
            >
                <div v-if="form.password.length > 0" class="space-y-2.5 rounded-xl border border-border bg-muted/30 p-3">
                    <div class="flex items-center gap-3">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full transition-all duration-500 ease-out"
                                :class="strengthBarClass"
                                :style="{ width: strengthPercent + '%' }"
                            />
                        </div>
                        <span
                            class="rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                            :class="strengthBadgeClass"
                        >
                            {{ strengthLabel }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                        <div v-for="rule in rules" :key="rule.label" class="flex items-center gap-1.5">
                            <Check v-if="rule.met" class="size-3 text-success" :stroke-width="3" />
                            <Circle v-else class="size-3 text-muted-foreground/40" :stroke-width="2" />
                            <span
                                class="text-[11px] transition-colors"
                                :class="rule.met ? 'font-medium text-foreground' : 'text-muted-foreground'"
                            >
                                {{ rule.label }}
                            </span>
                        </div>
                    </div>
                </div>
            </Transition>

            <AuthField
                label="Confirm password"
                type="password"
                :error="form.errors.password_confirmation"
                id="register-password-confirmation"
                v-model="form.password_confirmation"
                required
                autocomplete="new-password"
                placeholder="••••••••"
                :focus="false"
            />

            <AuthSubmitButton :form="form">
                <template #processing>Creating account</template>
                Create account
            </AuthSubmitButton>

            <p class="mt-2 text-center text-sm text-muted-foreground">
                Already have an account?
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
