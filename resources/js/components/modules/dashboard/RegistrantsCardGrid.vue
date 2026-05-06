<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Clock } from 'lucide-vue-next'
import { registrantInitials, registrantRelativeTime } from '@/lib/registrantsUi'

defineProps<{
    rows: IRegistrant[]
}>()

const emit = defineEmits<{
    openDetail: [reg: IRegistrant]
    approve: [reg: IRegistrant]
    reject: [reg: IRegistrant]
}>()
</script>

<template>
    <section class="grid gap-4 sm:grid-cols-2">
        <Card
            v-for="reg in rows"
            :key="reg.id"
            class="overflow-hidden rounded-2xl border border-border/60 shadow-xs transition-all hover:shadow-sm"
        >
            <CardHeader class="border-b bg-muted/20 pb-3 pt-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Avatar class="size-10 ring-2 ring-background">
                            <AvatarImage :src="reg.user.avatar ?? ''" :alt="reg.user.name" />
                            <AvatarFallback class="bg-primary/10 text-xs font-semibold text-primary">
                                {{ registrantInitials(reg.user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-foreground">{{ reg.user.name }}</p>
                            <p class="truncate text-[11px] text-muted-foreground">{{ reg.user.email }}</p>
                        </div>
                    </div>
                    <span
                        :class="[
                            'inline-flex items-center gap-1 size-2 rounded-full',
                            reg.status === 'approved' && 'bg-success',
                            reg.status === 'rejected' && 'bg-destructive',
                            reg.status === 'pending' && 'bg-warning',
                        ]"
                    />
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="flex flex-col divide-y divide-border/40">
                    <div v-for="(val, key) in reg.answers" :key="key" class="px-4 py-2.5">
                        <p class="text-[9px] font-bold uppercase tracking-wider text-muted-foreground">{{ key }}</p>
                        <p class="mt-0.5 line-clamp-2 text-xs font-medium text-foreground/90">{{ val || '—' }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t border-border/40 bg-muted/5 px-4 py-3">
                    <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                        <Clock class="size-3" />
                        {{ registrantRelativeTime(reg.submitted_at) }}
                    </div>
                    <div class="flex items-center gap-1">
                        <Button variant="ghost" size="sm" class="h-7 px-2 text-[10px]" @click="emit('openDetail', reg)">
                            Details
                        </Button>
                        <template v-if="reg.status === 'pending'">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-[10px] text-success hover:bg-success/10 hover:text-success"
                                @click="emit('approve', reg)"
                            >
                                Approve
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-[10px] text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="emit('reject', reg)"
                            >
                                Reject
                            </Button>
                        </template>
                    </div>
                </div>
            </CardContent>
        </Card>
    </section>
</template>
