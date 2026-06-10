<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- ctx.answerForm is Inertia useForm */
import { computed, onBeforeUnmount, ref, watch, type UnwrapNestedRefs } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { FormFillPageContext } from '@/utils/composables/useFormFillPage'
import { CheckCircle2, ChevronDown, Loader2, UserRound, XCircle } from 'lucide-vue-next'
import { routes } from '@/lib/routes'

const CHECK_EMAIL_URL = routes.member.checkEmail
const DEBOUNCE_MS = 1000

/** Trailing debounce: one independent timer per slot so multiple fields don’t cancel each other. */
function createPerSlotTrailingDebounce(
    delayMs: number,
    run: (slot: number) => void,
): {
    schedule: (slot: number) => void
    cancel: (slot: number) => void
    cancelAll: () => void
} {
    const timers = new Map<number, ReturnType<typeof setTimeout>>()
    return {
        schedule(slot: number) {
            const prev = timers.get(slot)
            if (prev !== undefined) {
                clearTimeout(prev)
            }
            timers.set(
                slot,
                setTimeout(() => {
                    timers.delete(slot)
                    run(slot)
                }, delayMs),
            )
        },
        cancel(slot: number) {
            const t = timers.get(slot)
            if (t !== undefined) {
                clearTimeout(t)
                timers.delete(slot)
            }
        },
        cancelAll() {
            for (const t of timers.values()) {
                clearTimeout(t)
            }
            timers.clear()
        },
    }
}

type CheckStatus = 'idle' | 'loading' | 'found' | 'not_found' | 'invalid' | 'error'

interface FoundUser {
    name: string
    email: string
    created_at: string
}

const props = defineProps<{
    ctx: UnwrapNestedRefs<FormFillPageContext>
}>()

const page = usePage()
const currentUserEmail = computed(() => (page.props as Props).auth?.user?.email?.trim().toLowerCase() ?? '')

const expandedBySlot = ref<Record<number, boolean>>({})
const statusBySlot = ref<Record<number, CheckStatus>>({})
const foundUserBySlot = ref<Record<number, FoundUser | undefined>>({})
const helperBySlot = ref<Record<number, string>>({})

/** Debounced email verification — waits `DEBOUNCE_MS` after the last keystroke per slot. */
const debouncedEmailCheck = createPerSlotTrailingDebounce(DEBOUNCE_MS, (slot) => {
    void runEmailCheck(slot)
})

const abortBySlot = new Map<number, AbortController>()

const memberSlots = computed(() => props.ctx.memberSlots)
const registrationMode = computed(() => props.ctx.registrationMode)

const sectionTitle = computed(() =>
    registrationMode.value === 'bundle' ? 'Participant emails' : 'Team member emails',
)

const sectionHint = computed(() =>
    registrationMode.value === 'bundle'
        ? 'Each address is checked against an existing account before you submit.'
        : 'We verify each teammate’s email before registration is sent.',
)

watch(
    memberSlots,
    (n) => {
        const next = { ...expandedBySlot.value }
        for (let s = 1; s <= n; s++) {
            if (next[s] === undefined) {
                next[s] = n <= 1 || s === 1
            }
        }
        expandedBySlot.value = next
    },
    { immediate: true },
)

function isExpanded(slot: number): boolean {
    const v = expandedBySlot.value[slot]
    if (v !== undefined) {
        return v
    }
    return memberSlots.value <= 1 || slot === 1
}

function toggleSlot(slot: number) {
    expandedBySlot.value = { ...expandedBySlot.value, [slot]: !isExpanded(slot) }
}

function emailAt(slot: number): string {
    return String((props.ctx.answerForm.team_member_emails as string[] | undefined)?.[slot - 1] ?? '')
}

function setTeamEmail(slot: number, value: string) {
    const n = memberSlots.value
    const arr = [...((props.ctx.answerForm.team_member_emails as string[]) ?? [])]
    while (arr.length < n) {
        arr.push('')
    }
    arr[slot - 1] = value
    props.ctx.answerForm.team_member_emails = arr
}

function validEmailFormat(s: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s.trim())
}

function setCheckState(slot: number, status: CheckStatus, found?: FoundUser, helper?: string) {
    statusBySlot.value = { ...statusBySlot.value, [slot]: status }
    if (arguments.length >= 3) {
        foundUserBySlot.value = { ...foundUserBySlot.value, [slot]: found }
    }
    if (arguments.length >= 4) {
        helperBySlot.value = { ...helperBySlot.value, [slot]: helper ?? '' }
    }
}

async function runEmailCheck(slot: number) {
    const raw = emailAt(slot).trim()
    abortBySlot.get(slot)?.abort()

    if (!raw) {
        setCheckState(slot, 'idle', undefined, '')
        return
    }
    if (!validEmailFormat(raw)) {
        setCheckState(slot, 'invalid', undefined, 'Enter a valid email address.')
        return
    }

    const selfEmail = currentUserEmail.value
    if (selfEmail && raw.toLowerCase() === selfEmail) {
        setCheckState(slot, 'invalid', undefined, 'Gunakan email peserta lain — bukan email akun Anda.')
        return
    }

    const controller = new AbortController()
    abortBySlot.set(slot, controller)
    setCheckState(slot, 'loading')

    try {
        const url = `${CHECK_EMAIL_URL}?${new URLSearchParams({ email: raw }).toString()}`
        const res = await fetch(url, {
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        const body = (await res.json()) as {
            exists?: boolean
            message?: string
            data?: FoundUser
        }

        if (controller.signal.aborted) {
            return
        }

        if (!res.ok) {
            setCheckState(
                slot,
                'error',
                undefined,
                (body.message as string | undefined) || 'Could not verify this email.',
            )
            return
        }

        if (body.exists && body.data) {
            setCheckState(slot, 'found', body.data, body.message ?? '')
        } else {
            setCheckState(slot, 'not_found', undefined, body.message ?? 'No account exists for this email.')
        }
    } catch (e) {
        if ((e as Error).name === 'AbortError') {
            return
        }
        setCheckState(slot, 'error', undefined, 'Could not verify this email. Try again.')
    }
}

function scheduleCheck(slot: number) {
    const raw = emailAt(slot).trim()
    if (!raw) {
        abortBySlot.get(slot)?.abort()
        debouncedEmailCheck.cancel(slot)
        setCheckState(slot, 'idle', undefined, '')
        return
    }
    if (!validEmailFormat(raw)) {
        abortBySlot.get(slot)?.abort()
        debouncedEmailCheck.cancel(slot)
        setCheckState(slot, 'invalid', undefined, 'Enter a valid email address.')
        return
    }
    const selfEmail = currentUserEmail.value
    if (selfEmail && raw.toLowerCase() === selfEmail) {
        abortBySlot.get(slot)?.abort()
        debouncedEmailCheck.cancel(slot)
        setCheckState(slot, 'invalid', undefined, 'Gunakan email peserta lain — bukan email akun Anda.')
        return
    }

    debouncedEmailCheck.schedule(slot)
}

function onEmailInput(slot: number, v: string | number) {
    props.ctx.answerForm.clearErrors('team_member_emails')
    setTeamEmail(slot, String(v))
    scheduleCheck(slot)
}

function statusFor(slot: number): CheckStatus {
    return statusBySlot.value[slot] ?? 'idle'
}

function shortDate(iso: string): string {
    try {
        return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
    } catch {
        return ''
    }
}

function summaryLine(slot: number): string {
    const e = emailAt(slot).trim()
    if (e) {
        return e
    }
    return 'Tap to add email'
}

function rowAccentClass(slot: number): string {
    const s = statusFor(slot)
    if (s === 'found') {
        return 'border-emerald-500/30 bg-emerald-500/[0.04] shadow-[0_0_0_1px_rgba(16,185,129,0.06)]'
    }
    if (s === 'not_found' || s === 'invalid' || s === 'error') {
        return 'border-destructive/25 bg-destructive/[0.03]'
    }
    return 'border-border/80 bg-card'
}

const badgeClass =
    'border font-medium text-[10px] uppercase tracking-wide tabular-nums px-2 py-0.5'

onBeforeUnmount(() => {
    debouncedEmailCheck.cancelAll()
    for (const c of abortBySlot.values()) {
        c.abort()
    }
})
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm ring-1 ring-black/[0.04] dark:bg-card/95 dark:ring-white/[0.05]"
    >
        <header class="border-b border-border/70 bg-muted/25 px-4 py-3.5 sm:px-5 sm:py-4">
            <h2 class="font-display text-sm font-semibold tracking-tight text-foreground sm:text-base">
                {{ sectionTitle }}
            </h2>
            <p class="mt-0.5 text-xs leading-relaxed text-muted-foreground sm:text-[13px]">
                {{ sectionHint }}
            </p>
        </header>

        <div class="flex flex-col gap-3 p-3 sm:gap-3.5 sm:p-4">
            <div
                v-for="slot in memberSlots"
                :key="slot"
                :class="
                    cn(
                        'overflow-hidden rounded-xl border shadow-xs transition-[border-color,box-shadow,background-color] duration-200',
                        rowAccentClass(slot),
                    )
                "
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-3 py-3 text-left outline-none transition-colors hover:bg-muted/35 focus-visible:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring/40 sm:px-4"
                    :aria-expanded="isExpanded(slot)"
                    @click="toggleSlot(slot)"
                >
                    <ChevronDown
                        class="size-4 shrink-0 text-muted-foreground transition-transform duration-200 ease-out"
                        :class="{ 'rotate-180': isExpanded(slot) }"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold leading-none tracking-tight text-foreground">
                                {{ registrationMode === 'bundle' ? 'Participant' : 'Teammate' }} {{ slot }}
                            </span>
                            <Badge
                                v-if="statusFor(slot) === 'found'"
                                variant="secondary"
                                :class="cn(badgeClass, 'border-emerald-500/30 bg-emerald-500/12 text-emerald-800 dark:text-emerald-200')"
                            >
                                Verified
                            </Badge>
                            <Badge
                                v-else-if="statusFor(slot) === 'loading'"
                                variant="secondary"
                                :class="cn(badgeClass, 'border-primary/25 bg-primary/8 text-primary')"
                            >
                                Checking
                            </Badge>
                            <Badge
                                v-else-if="statusFor(slot) === 'not_found' || statusFor(slot) === 'invalid'"
                                variant="secondary"
                                :class="cn(badgeClass, 'border-destructive/25 bg-destructive/10 text-destructive')"
                            >
                                {{ statusFor(slot) === 'invalid' ? 'Invalid' : 'Not found' }}
                            </Badge>
                            <Badge
                                v-else-if="statusFor(slot) === 'error'"
                                variant="secondary"
                                :class="cn(badgeClass, 'border-destructive/25 bg-destructive/10 text-destructive')"
                            >
                                Error
                            </Badge>
                        </div>
                        <p class="mt-1 truncate text-xs leading-relaxed text-muted-foreground">
                            {{ summaryLine(slot) }}
                        </p>
                    </div>
                    <div class="pointer-events-none flex shrink-0 items-center">
                        <Loader2
                            v-if="statusFor(slot) === 'loading'"
                            class="size-[18px] animate-spin text-primary"
                            aria-hidden="true"
                        />
                        <CheckCircle2
                            v-else-if="statusFor(slot) === 'found'"
                            class="size-[18px] text-emerald-600 dark:text-emerald-400"
                            aria-hidden="true"
                        />
                        <XCircle
                            v-else-if="
                                statusFor(slot) === 'not_found' ||
                                statusFor(slot) === 'invalid' ||
                                statusFor(slot) === 'error'
                            "
                            class="size-[18px] text-destructive"
                            aria-hidden="true"
                        />
                    </div>
                </button>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    leave-active-class="transition duration-200 ease-in"
                    enter-from-class="opacity-0 -translate-y-0.5"
                    leave-to-class="opacity-0 -translate-y-0.5"
                >
                    <div
                        v-show="isExpanded(slot)"
                        class="border-t border-border/60 bg-muted/[0.45] px-3 pb-4 pt-3 sm:px-4"
                    >
                        <label
                            class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                            :for="`team_member_emails_${slot}`"
                        >
                            Email <span class="text-destructive">*</span>
                        </label>

                        <div class="relative">
                            <div
                                class="pointer-events-none absolute left-3 top-1/2 z-[1] flex size-5 -translate-y-1/2 items-center justify-center"
                                aria-hidden="true"
                            >
                                <Transition
                                    mode="out-in"
                                    enter-active-class="transition-all duration-200 ease-out"
                                    leave-active-class="transition-all duration-150 ease-in"
                                    enter-from-class="scale-75 opacity-0"
                                    enter-to-class="scale-100 opacity-100"
                                    leave-from-class="scale-100 opacity-100"
                                    leave-to-class="scale-75 opacity-0"
                                >
                                    <Loader2
                                        v-if="statusFor(slot) === 'loading'"
                                        key="load"
                                        class="size-5 animate-spin text-primary"
                                    />
                                    <CheckCircle2
                                        v-else-if="statusFor(slot) === 'found'"
                                        key="ok"
                                        class="size-5 text-emerald-600 dark:text-emerald-400"
                                    />
                                    <XCircle
                                        v-else-if="
                                            statusFor(slot) === 'not_found' ||
                                            statusFor(slot) === 'invalid' ||
                                            statusFor(slot) === 'error'
                                        "
                                        key="bad"
                                        class="size-5 text-destructive"
                                    />
                                </Transition>
                            </div>

                            <Input
                                :id="`team_member_emails_${slot}`"
                                type="email"
                                autocomplete="email"
                                inputmode="email"
                                placeholder="name@example.com"
                                :class="
                                    cn(
                                        'h-11 min-h-11 rounded-lg pl-10 text-[15px] shadow-none transition-[border-color,box-shadow] duration-200',
                                        statusFor(slot) === 'found' &&
                                            'border-emerald-500/50 focus-visible:border-emerald-600 focus-visible:ring-2 focus-visible:ring-emerald-500/20',
                                        (statusFor(slot) === 'not_found' ||
                                            statusFor(slot) === 'invalid' ||
                                            statusFor(slot) === 'error') &&
                                            'border-destructive/50 focus-visible:border-destructive focus-visible:ring-2 focus-visible:ring-destructive/15',
                                    )
                                "
                                :model-value="emailAt(slot)"
                                :aria-busy="statusFor(slot) === 'loading'"
                                @update:model-value="(v: string | number) => onEmailInput(slot, v)"
                            />
                        </div>

                        <Transition
                            enter-active-class="transition duration-200 ease-out"
                            leave-active-class="transition duration-200 ease-in"
                            enter-from-class="opacity-0 -translate-y-1"
                            leave-to-class="opacity-0 -translate-y-1"
                        >
                            <div
                                v-if="statusFor(slot) === 'found' && foundUserBySlot[slot]"
                                class="mt-3 overflow-hidden rounded-lg border border-emerald-500/20 bg-gradient-to-br from-emerald-500/[0.09] to-emerald-500/[0.02] px-3 py-3 dark:from-emerald-950/40 dark:to-transparent"
                            >
                                <div class="flex gap-3">
                                    <div
                                        class="grid size-10 shrink-0 place-items-center rounded-full border border-emerald-500/25 bg-background/90 text-emerald-700 shadow-xs dark:text-emerald-300"
                                    >
                                        <UserRound class="size-4" aria-hidden="true" />
                                    </div>
                                    <dl class="min-w-0 flex-1 space-y-2 text-sm">
                                        <div>
                                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                Name
                                            </dt>
                                            <dd class="mt-0.5 truncate font-semibold leading-snug text-foreground">
                                                {{ foundUserBySlot[slot]!.name }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                Email
                                            </dt>
                                            <dd class="mt-0.5 break-all text-xs leading-relaxed text-muted-foreground">
                                                {{ foundUserBySlot[slot]!.email }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                Member since
                                            </dt>
                                            <dd class="mt-0.5 text-xs text-foreground">
                                                {{ shortDate(foundUserBySlot[slot]!.created_at) }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </Transition>

                        <p
                            v-if="
                                helperBySlot[slot] &&
                                statusFor(slot) !== 'found' &&
                                statusFor(slot) !== 'loading' &&
                                statusFor(slot) !== 'idle'
                            "
                            class="mt-2.5 text-xs leading-relaxed"
                            :class="statusFor(slot) === 'error' ? 'font-medium text-destructive' : 'text-muted-foreground'"
                        >
                            {{ helperBySlot[slot] }}
                        </p>

                        <p
                            v-if="ctx.fieldError(`team_member_emails.${slot - 1}`)"
                            class="mt-2 text-xs font-medium text-destructive"
                        >
                            {{ ctx.fieldError(`team_member_emails.${slot - 1}`) }}
                        </p>
                    </div>
                </Transition>
            </div>

            <p
                v-if="ctx.fieldError('team_member_emails')"
                class="rounded-lg border border-destructive/20 bg-destructive/5 px-3 py-2.5 text-xs font-medium text-destructive"
            >
                {{ ctx.fieldError('team_member_emails') }}
            </p>
        </div>
    </section>
</template>
