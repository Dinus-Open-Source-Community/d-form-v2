<script setup lang="ts">
import { computed, type Component } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import { CalendarDays, User } from 'lucide-vue-next'

export type ApplicantCardStatusVariant = 'waiting' | 'info' | 'success' | 'locked' | 'muted'

export type ApplicantCardMetaIcon = 'id' | 'cap' | 'calendar' | 'pin' | 'none'

export interface ApplicantCardMetaItem {
    icon: ApplicantCardMetaIcon
    label: string
    value: string
}

const props = withDefaults(
    defineProps<{
        name: string
        division: string | null
        statusLabel: string
        statusVariant?: ApplicantCardStatusVariant
        /** Label tambahan (mis. "Belum regis ulang"); null = tidak tampil. */
        flagLabel?: string | null
        box1: ApplicantCardMetaItem[]
        box2?: ApplicantCardMetaItem[] | null
        box3?: ApplicantCardMetaItem[] | null
        noteLabel?: string | null
        noteBody?: string | null
        dimmed?: boolean
    }>(),
    {
        statusVariant: 'waiting',
        flagLabel: null,
        box2: null,
        box3: null,
        noteLabel: null,
        noteBody: null,
        dimmed: false,
    },
)

/** Tinted dot + text; no filled pills. */
const STATUS_TEXT: Record<ApplicantCardStatusVariant, string> = {
    waiting: 'text-amber-700',
    info: 'text-blue-700',
    success: 'text-success',
    locked: 'text-secondary-foreground',
    muted: 'text-muted-foreground',
}

/** Only the calendar keeps an icon (disambiguates dates); other rows render label + value. */
const META_ICONS: Partial<Record<ApplicantCardMetaIcon, Component>> = {
    calendar: CalendarDays,
}

const boxes = computed<ApplicantCardMetaItem[][]>(() => {
    const list: ApplicantCardMetaItem[][] = [props.box1]
    if (props.box2) list.push(props.box2)
    if (props.box3) list.push(props.box3)
    return list
})
</script>

<template>
    <Card
        class="w-full max-w-xl rounded-2xl shadow-none duration-150"
        :class="dimmed && 'border-dashed opacity-70'"
    >
        <CardContent class="space-y-4 p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500"
                    aria-hidden="true"
                >
                    <User class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                Nama
                            </p>
                            <div class="mt-0.5 text-base font-semibold break-words text-foreground">
                                <slot name="name">{{ name }}</slot>
                            </div>
                            <span
                                v-if="division !== null && division !== ''"
                                class="mt-1.5 inline-flex items-center rounded-full border border-border/70 px-2 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ division }}
                            </span>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                            <span
                                class="inline-flex items-center gap-1.5 text-xs font-medium"
                                :class="STATUS_TEXT[statusVariant]"
                            >
                                <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true" />
                                {{ statusLabel }}
                            </span>
                            <span
                                v-if="flagLabel"
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                            >
                                <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true" />
                                {{ flagLabel }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-for="(box, boxIndex) in boxes"
                :key="boxIndex"
                class="rounded-xl border border-border/70"
            >
                <div
                    class="grid grid-cols-1 divide-y divide-border/60 sm:grid-cols-2 sm:divide-x sm:divide-y-0"
                >
                    <div
                        v-for="item in box"
                        :key="item.label"
                        class="flex min-w-0 items-start gap-2.5 p-4"
                    >
                        <component
                            v-if="META_ICONS[item.icon]"
                            :is="META_ICONS[item.icon]"
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">{{ item.label }}</p>
                            <p class="mt-0.5 text-sm font-medium break-words tabular-nums text-foreground">
                                {{ item.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="noteBody"
                class="rounded-xl border border-border/60 bg-muted/30 px-4 py-3"
            >
                <p v-if="noteLabel" class="text-xs font-medium text-muted-foreground">
                    {{ noteLabel }}
                </p>
                <p class="mt-0.5 text-sm break-words text-muted-foreground">{{ noteBody }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <slot name="action" />
                <slot name="extra" />
            </div>
        </CardContent>
    </Card>
</template>
