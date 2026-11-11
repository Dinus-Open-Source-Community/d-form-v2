<script setup lang="ts">
import { Button } from '@/components/ui/button';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { CheckCircle2, Clock, XCircle } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/dateTime';
import { registrantRelativeTimeId, registrantStatusBadgeClass } from '@/lib/registrantsUi';

const open = defineModel<boolean>('open', { required: true });

defineProps<{
    registrant: IRegistrant | null;
}>();

const emit = defineEmits<{
    approve: [];
    reject: [];
}>();
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader class="space-y-0">
                <SheetTitle class="text-base font-semibold">Submission details</SheetTitle>
                <SheetDescription class="text-xs">Review the answers submitted by this registrant.</SheetDescription>
            </SheetHeader>

            <div v-if="registrant" class="flex flex-col gap-5 px-4 pb-4">
                <div class="border-border/60 bg-muted/30 flex items-center gap-3 rounded-2xl border p-3.5">
                    <UserAvatarFallback
                        :src="registrant.user.avatar"
                        :seed="userAvatarSeed(registrant.user)"
                        avatar-class="size-12 ring-2 ring-background"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-foreground truncate text-sm font-semibold">{{ registrant.user.name }}</p>
                        <p class="text-muted-foreground truncate text-xs">{{ registrant.user.email }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Formulir:
                            <span class="text-foreground font-medium">{{ registrant.form.title }}</span>
                        </p>
                    </div>
                    <span
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium capitalize ring-1',
                            registrantStatusBadgeClass(registrant.status),
                        ]"
                    >
                        <span class="size-1.5 rounded-full bg-current" />
                        {{ registrant.status }}
                    </span>
                </div>

                <div class="text-muted-foreground flex items-center gap-2 text-[11px]">
                    <Clock class="size-3" />
                    Submitted {{ registrantRelativeTimeId(registrant.submitted_at) }} ·
                    {{ formatDateTime({ value: registrant.submitted_at, variant: 'date-time' }) }}
                </div>

                <div class="flex flex-col gap-2">
                    <p class="text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">
                        Form answers
                    </p>
                    <div class="divide-border/50 border-border/60 flex flex-col divide-y rounded-2xl border">
                        <div
                            v-for="(value, key) in registrant.answers"
                            :key="key"
                            class="flex flex-col gap-1 px-4 py-3"
                        >
                            <p class="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">
                                {{ key }}
                            </p>
                            <p class="text-foreground text-sm leading-relaxed">{{ value || '—' }}</p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="registrant.status === 'accepted' && registrant.registration_code"
                    class="border-success/25 bg-success/5 rounded-2xl border px-4 py-3"
                >
                    <p class="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">
                        Manual registration code
                    </p>
                    <p class="text-foreground mt-1 font-mono text-lg font-bold tracking-widest">
                        {{ registrant.registration_code }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-[11px] leading-snug">
                        Staff can enter this at check-in if QR scanning is unavailable.
                    </p>
                </div>

                <div v-if="registrant.status === 'pending'" class="flex items-center gap-2 pt-2">
                    <Button
                        class="bg-success text-success-foreground hover:bg-success/90 flex-1"
                        @click="emit('approve')"
                    >
                        <CheckCircle2 class="mr-1.5 size-4" />
                        Approve
                    </Button>
                    <Button
                        variant="outline"
                        class="border-destructive/30 text-destructive hover:bg-destructive/5 hover:text-destructive flex-1"
                        @click="emit('reject')"
                    >
                        <XCircle class="mr-1.5 size-4" />
                        Reject
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
