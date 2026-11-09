<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { CalendarDays, User } from 'lucide-vue-next';

export type TApplicantCardStatusVariant = 'waiting' | 'info' | 'success' | 'locked' | 'muted';

export type TApplicantCardMetaIcon = 'id' | 'cap' | 'calendar' | 'pin' | 'none';

export interface IApplicantCardMetaItem {
    icon: TApplicantCardMetaIcon;
    label: string;
    value: string;
}

const props = withDefaults(
    defineProps<{
        name: string;
        division: string | null;
        statusLabel: string;
        statusVariant?: TApplicantCardStatusVariant;
        /** Label tambahan (mis. "Belum regis ulang"); null = tidak tampil. */
        flagLabel?: string | null;
        box1: IApplicantCardMetaItem[];
        box2?: IApplicantCardMetaItem[] | null;
        box3?: IApplicantCardMetaItem[] | null;
        noteLabel?: string | null;
        noteBody?: string | null;
        dimmed?: boolean;
    }>(),
    {
        statusVariant: 'waiting',
        flagLabel: null,
        box2: null,
        box3: null,
        noteLabel: null,
        noteBody: null,
        dimmed: false,
    }
);

/** Tinted dot + text; no filled pills. */
const STATUS_TEXT: Record<TApplicantCardStatusVariant, string> = {
    waiting: 'text-amber-700',
    info: 'text-blue-700',
    success: 'text-success',
    locked: 'text-secondary-foreground',
    muted: 'text-muted-foreground',
};

/** Only the calendar keeps an icon (disambiguates dates); other rows render label + value. */
const META_ICONS: Partial<Record<TApplicantCardMetaIcon, Component>> = {
    calendar: CalendarDays,
};

const boxes = computed<IApplicantCardMetaItem[][]>(() => {
    const list: IApplicantCardMetaItem[][] = [props.box1];
    if (props.box2) list.push(props.box2);
    if (props.box3) list.push(props.box3);
    return list;
});
</script>

<template>
    <Card class="w-full max-w-xl rounded-2xl shadow-none duration-150" :class="dimmed && 'border-dashed opacity-70'">
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
                            <p class="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">Nama</p>
                            <div class="text-foreground mt-0.5 text-base font-semibold break-words">
                                <slot name="name">{{ name }}</slot>
                            </div>
                            <span
                                v-if="division !== null && division !== ''"
                                class="border-border/70 text-muted-foreground mt-1.5 inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
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
                                class="text-muted-foreground inline-flex items-center gap-1.5 text-xs font-medium"
                            >
                                <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true" />
                                {{ flagLabel }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-for="(box, boxIndex) in boxes" :key="boxIndex" class="border-border/70 rounded-xl border">
                <div class="divide-border/60 grid grid-cols-1 divide-y sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                    <div v-for="item in box" :key="item.label" class="flex min-w-0 items-start gap-2.5 p-4">
                        <component
                            v-if="META_ICONS[item.icon]"
                            :is="META_ICONS[item.icon]"
                            class="text-muted-foreground mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p class="text-muted-foreground text-xs">{{ item.label }}</p>
                            <p class="text-foreground mt-0.5 text-sm font-medium break-words tabular-nums">
                                {{ item.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="noteBody" class="border-border/60 bg-muted/30 rounded-xl border px-4 py-3">
                <p v-if="noteLabel" class="text-muted-foreground text-xs font-medium">
                    {{ noteLabel }}
                </p>
                <p class="text-muted-foreground mt-0.5 text-sm break-words">{{ noteBody }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <slot name="action" />
                <slot name="extra" />
            </div>
        </CardContent>
    </Card>
</template>
