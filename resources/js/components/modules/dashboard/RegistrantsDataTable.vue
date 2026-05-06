<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { CheckCircle2, Eye, XCircle } from 'lucide-vue-next'
import { formatDateTime } from '@/lib/dummyData'
import { registrantInitials, registrantRelativeTime, registrantStatusBadgeClass } from '@/lib/registrantsUi'

defineProps<{
    rows: IRegistrant[]
    cardShadow: string
}>()

const emit = defineEmits<{
    openDetail: [reg: IRegistrant]
    approve: [reg: IRegistrant]
    reject: [reg: IRegistrant]
}>()
</script>

<template>
    <section :class="['overflow-hidden rounded-2xl border border-border/60 bg-card', cardShadow]">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border/60 bg-muted/40 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground"
                    >
                        <th class="px-5 py-3.5">Registrant</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5">Submitted</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="reg in rows"
                        :key="reg.id"
                        class="group border-b border-border/40 transition-colors last:border-0 hover:bg-muted/30"
                    >
                        <td class="px-5 py-3.5">
                            <button type="button" class="flex items-center gap-3 text-left" @click="emit('openDetail', reg)">
                                <Avatar class="size-9 ring-2 ring-background transition-transform group-hover:scale-[1.03]">
                                    <AvatarImage :src="reg.user.avatar ?? ''" :alt="reg.user.name" />
                                    <AvatarFallback class="bg-primary/10 text-[11px] font-semibold text-primary">
                                        {{ registrantInitials(reg.user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-foreground">{{ reg.user.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ reg.user.email }}</p>
                                </div>
                            </button>
                        </td>
                        <td class="px-5 py-3.5">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium capitalize ring-1',
                                    registrantStatusBadgeClass(reg.status),
                                ]"
                            >
                                <span class="size-1.5 rounded-full bg-current" />
                                {{ reg.status }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex flex-col">
                                <span class="text-xs font-medium text-foreground">{{
                                    registrantRelativeTime(reg.submitted_at)
                                }}</span>
                                <span class="text-[11px] text-muted-foreground">{{
                                    formatDateTime(reg.submitted_at)
                                }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 rounded-full"
                                            @click="emit('openDetail', reg)"
                                        >
                                            <Eye class="size-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>View submission</TooltipContent>
                                </Tooltip>
                                <template v-if="reg.status === 'pending'">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-8 rounded-full text-success hover:bg-success/10 hover:text-success"
                                                @click="emit('approve', reg)"
                                            >
                                                <CheckCircle2 class="size-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Approve</TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-8 rounded-full text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                @click="emit('reject', reg)"
                                            >
                                                <XCircle class="size-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Reject</TooltipContent>
                                    </Tooltip>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
