<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
    registrantRelativeTimeId,
    registrantStatusBadgeClass,
    registrantStatusLabel,
} from '@/lib/registrantsUi'
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue'
import { userAvatarSeed } from '@/lib/userAvatarFallback'
import { formatDateTime } from '@/lib/dateTime'
import { FileText } from 'lucide-vue-next'

defineProps<{
    rows: IRegistrant[]
}>()
</script>

<template>
    <Card class="rounded-xl border-border/70 shadow-xs">
        <CardHeader class="pb-3">
            <CardTitle class="text-base font-medium">Daftar pengiriman</CardTitle>
            <CardDescription class="text-sm">
                Menampilkan {{ rows.length }} baris sesuai filter saat ini. Kolom formulir menunjukkan sumber pengiriman.
            </CardDescription>
        </CardHeader>
        <CardContent class="overflow-x-auto px-0 pt-0 sm:px-6">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-border bg-muted/40 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 sm:px-6">Pendaftar</th>
                        <th class="hidden px-4 py-3 md:table-cell md:px-6">Formulir</th>
                        <th class="px-4 py-3 sm:px-6">Status</th>
                        <th class="hidden px-4 py-3 font-normal lg:table-cell lg:px-6">Kode</th>
                        <th class="px-4 py-3 sm:px-6">Waktu kirim</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="reg in rows"
                        :key="reg.id"
                        class="border-b border-border/60 last:border-0 hover:bg-muted/30"
                    >
                        <td class="px-4 py-4 align-top sm:px-6">
                            <div class="flex items-start gap-3">
                                <UserAvatarFallback
                                    :src="reg.user.avatar"
                                    :seed="userAvatarSeed(reg.user)"
                                    avatar-class="size-10 shrink-0 ring-1 ring-border"
                                />
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-foreground">{{ reg.user.name }}</p>
                                    <p class="truncate text-sm text-muted-foreground">{{ reg.user.email }}</p>
                                    <div class="mt-2 flex items-start gap-1.5 md:hidden">
                                        <FileText class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        <span class="line-clamp-2 text-xs leading-snug text-foreground">
                                            {{ reg.form.title }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="hidden max-w-[14rem] px-4 py-4 align-top md:table-cell md:px-6">
                            <Badge variant="secondary" class="line-clamp-3 whitespace-normal text-left text-xs font-normal leading-snug">
                                {{ reg.form.title }}
                            </Badge>
                        </td>
                        <td class="px-4 py-4 align-top sm:px-6">
                            <span
                                :class="[
 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
 registrantStatusBadgeClass(reg.status),
 ]"
                            >
                                <span class="size-1.5 shrink-0 rounded-full bg-current opacity-80" aria-hidden="true" />
                                {{ registrantStatusLabel(reg.status) }}
                            </span>
                        </td>
                        <td class="hidden px-4 py-4 align-top font-mono text-xs text-muted-foreground lg:table-cell lg:px-6">
                            <span v-if="reg.registration_code">{{ reg.registration_code }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-4 align-top sm:px-6">
                            <p class="text-sm font-medium text-foreground">{{ registrantRelativeTimeId(reg.submitted_at) }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatDateTime({ value: reg.submitted_at, variant: 'date-time-medium' }) || reg.submitted_at }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </CardContent>
    </Card>
</template>
