<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { CheckCircle2, Clock, XCircle } from 'lucide-vue-next'
import { formatDateTime } from '@/lib/dummyData'
import { registrantInitials, registrantRelativeTime, registrantStatusBadgeClass } from '@/lib/registrantsUi'

const open = defineModel<boolean>('open', { required: true })

defineProps<{
    registrant: IRegistrant | null
}>()

const emit = defineEmits<{
    approve: []
    reject: []
}>()
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader class="space-y-0">
                <SheetTitle class="text-base font-semibold">Submission details</SheetTitle>
                <SheetDescription class="text-xs">Review the answers submitted by this registrant.</SheetDescription>
            </SheetHeader>

            <div v-if="registrant" class="flex flex-col gap-5 px-4 pb-4">
                <div class="flex items-center gap-3 rounded-2xl border border-border/60 bg-muted/30 p-3.5">
                    <Avatar class="size-12 ring-2 ring-background">
                        <AvatarImage :src="registrant.user.avatar ?? ''" :alt="registrant.user.name" />
                        <AvatarFallback class="bg-primary/10 text-sm font-semibold text-primary">
                            {{ registrantInitials(registrant.user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-foreground">{{ registrant.user.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ registrant.user.email }}</p>
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

                <div class="flex items-center gap-2 text-[11px] text-muted-foreground">
                    <Clock class="size-3" />
                    Submitted {{ registrantRelativeTime(registrant.submitted_at) }} ·
                    {{ formatDateTime(registrant.submitted_at) }}
                </div>

                <div class="flex flex-col gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
                        Form answers
                    </p>
                    <div class="flex flex-col divide-y divide-border/50 rounded-2xl border border-border/60">
                        <div
                            v-for="(value, key) in registrant.answers"
                            :key="key"
                            class="flex flex-col gap-1 px-4 py-3"
                        >
                            <p class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                {{ key }}
                            </p>
                            <p class="text-sm leading-relaxed text-foreground">{{ value || '—' }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="registrant.status === 'pending'" class="flex items-center gap-2 pt-2">
                    <Button
                        class="flex-1 rounded-xl bg-success text-success-foreground hover:bg-success/90"
                        @click="emit('approve')"
                    >
                        <CheckCircle2 class="mr-1.5 size-4" />
                        Approve
                    </Button>
                    <Button
                        variant="outline"
                        class="flex-1 rounded-xl border-destructive/30 text-destructive hover:bg-destructive/5 hover:text-destructive"
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
