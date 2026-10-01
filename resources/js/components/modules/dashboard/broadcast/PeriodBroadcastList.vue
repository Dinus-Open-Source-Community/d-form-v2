<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { BROADCAST_STATUS_META, type IBroadcastPeriodRow } from '@/lib/broadcastHub'
import { routes } from '@/lib/routes'
import { formatIdDateTimeLabel } from '@/lib/shadcnDateFormat'
import { cn } from '@/lib/utils'
import { ArrowRight } from 'lucide-vue-next'

const props = defineProps<{
    broadcasts: IBroadcastPeriodRow[]
    periodId: string
    periodName: string
}>()

function scheduleLabelOf(value: string | null): string {
    if (!value) return '—'
    return formatIdDateTimeLabel(value)
}
</script>

<template>
    <Card class="rounded-2xl border-border/70 overflow-hidden">
        <CardContent class="p-0">
            <div class="overflow-x-auto overflow-y-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 border-b text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nama</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Jadwal</th>
                            <th class="px-4 py-3 font-medium text-right">Penerima</th>
                            <th class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in props.broadcasts"
                            :key="row.id"
                            class="border-b last:border-0 transition-colors hover:bg-muted/20"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="routes.admin.broadcasts.show(row.id)"
                                    class="font-medium underline-offset-4 hover:underline"
                                >
                                    {{ row.name }}
                                </Link>
                                <p v-if="row.subject" class="mt-0.5 truncate text-xs text-muted-foreground">
                                    {{ row.subject }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :class="
                                        cn(
                                            'shrink-0 border text-[11px] font-medium',
                                            BROADCAST_STATUS_META[row.status].classes,
                                        )
                                    "
                                >
                                    {{ BROADCAST_STATUS_META[row.status].label }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs tabular-nums text-muted-foreground">
                                {{ scheduleLabelOf(row.scheduled_at) }}
                            </td>
                            <td class="px-4 py-3 text-right text-xs tabular-nums">
                                {{ row.recipient_count.toLocaleString('id-ID') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-0.5">
                                    <Button radius="icon" variant="ghost" size="icon-sm" as-child>
                                        <Link
                                            :href="routes.admin.broadcasts.show(row.id)"
                                            :aria-label="`Lihat detail ${row.name}`"
                                        >
                                            <ArrowRight class="size-4" aria-hidden="true" />
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.broadcasts.length === 0">
                            <td colspan="5" class="text-muted-foreground px-4 py-10 text-center">
                                Belum ada broadcast untuk periode ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
