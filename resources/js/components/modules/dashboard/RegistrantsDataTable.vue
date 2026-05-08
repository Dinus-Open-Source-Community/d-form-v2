<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import {
    registrantInitials,
    registrantRelativeTimeId,
    registrantStatusBadgeClass,
    registrantStatusLabel,
} from '@/lib/registrantsUi'

defineProps<{
    rows: IRegistrant[]
    cardShadow: string
}>()

function formatSubmittedDetail(iso: string): string {
    try {
        return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
    } catch {
        return iso
    }
}
</script>

<template>
    <section :class="['overflow-hidden rounded-2xl border border-border/60 bg-card', cardShadow]">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border/60 bg-muted/35 text-left text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground"
                    >
                        <th class="px-4 py-3 sm:px-5">Pendaftar</th>
                        <th class="px-4 py-3 sm:px-5">Status</th>
                        <th class="hidden px-4 py-3 sm:table-cell sm:px-5">Kode</th>
                        <th class="px-4 py-3 sm:px-5">Dikirim</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="reg in rows"
                        :key="reg.id"
                        class="border-b border-border/40 transition-colors last:border-0 hover:bg-muted/25"
                    >
                        <td class="px-4 py-3.5 sm:px-5">
                            <div class="flex items-center gap-3">
                                <Avatar class="size-9 shrink-0 ring-2 ring-background">
                                    <AvatarImage :src="reg.user.avatar ?? ''" :alt="reg.user.name" />
                                    <AvatarFallback class="bg-primary/10 text-[11px] font-semibold text-primary">
                                        {{ registrantInitials(reg.user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-foreground">{{ reg.user.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ reg.user.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 sm:px-5">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium ring-1',
                                    registrantStatusBadgeClass(reg.status),
                                ]"
                            >
                                <span class="size-1.5 shrink-0 rounded-full bg-current" aria-hidden="true" />
                                {{ registrantStatusLabel(reg.status) }}
                            </span>
                        </td>
                        <td class="hidden px-4 py-3.5 font-mono text-xs text-muted-foreground sm:table-cell sm:px-5">
                            <span v-if="reg.registration_code">{{ reg.registration_code }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3.5 sm:px-5">
                            <div class="flex flex-col gap-0.5">
                                <span class="text-xs font-medium text-foreground">{{
                                    registrantRelativeTimeId(reg.submitted_at)
                                }}</span>
                                <span class="text-[11px] text-muted-foreground">{{
                                    formatSubmittedDetail(reg.submitted_at)
                                }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
