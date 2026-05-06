<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { ArrowUpRight } from 'lucide-vue-next'
import { formatDate } from '@/lib/dummyData'
import { eventParticipantInitials } from '@/lib/eventShowUi'

defineProps<{
    eventId: string
    totalRegistrants: number
    previewRegistrants: IRegistrant[]
    cardShadow: string
}>()
</script>

<template>
    <Card :class="['rounded-2xl border-border/60', cardShadow]">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Recent registrants</CardTitle>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <span class="font-medium tabular-nums text-foreground">{{ totalRegistrants }}</span>
                        {{ totalRegistrants === 1 ? 'person has' : 'people have' }} signed up so far.
                    </p>
                </div>
                <Button variant="ghost" size="sm" class="h-8 gap-1 rounded-full text-xs" as-child>
                    <Link :href="`/dashboard/events/${eventId}/registrants`">
                        View all
                        <ArrowUpRight class="size-3.5" />
                    </Link>
                </Button>
            </div>
        </CardHeader>
        <CardContent class="pt-0">
            <div v-if="previewRegistrants.length > 0" class="flex flex-col gap-1">
                <Link
                    v-for="reg in previewRegistrants"
                    :key="reg.id"
                    :href="`/dashboard/events/${eventId}/registrants`"
                    class="group flex items-center justify-between rounded-xl px-2.5 py-2 transition-colors hover:bg-muted/50"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <Avatar class="size-9 ring-2 ring-background">
                            <AvatarImage :src="reg.user.avatar ?? ''" :alt="reg.user.name" />
                            <AvatarFallback class="bg-primary/10 text-[11px] font-semibold text-primary">
                                {{ eventParticipantInitials(reg.user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-foreground">{{ reg.user.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ reg.user.email }}</p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10.5px] font-medium capitalize',
                                reg.status === 'approved' && 'bg-success/10 text-success',
                                reg.status === 'pending' && 'bg-warning/15 text-warning-foreground',
                                reg.status === 'rejected' && 'bg-destructive/10 text-destructive',
                            ]"
                        >
                            <span class="size-1.5 rounded-full bg-current" />
                            {{ reg.status }}
                        </span>
                        <span class="hidden text-[11px] tabular-nums text-muted-foreground sm:inline">
                            {{ formatDate(reg.submitted_at) }}
                        </span>
                    </div>
                </Link>
            </div>
            <p v-else class="px-2 py-6 text-center text-sm text-muted-foreground">
                No registrants yet — share your event to get things rolling.
            </p>
        </CardContent>
    </Card>
</template>
