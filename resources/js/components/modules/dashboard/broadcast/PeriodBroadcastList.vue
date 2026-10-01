<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { BROADCAST_STATUS_META, type IBroadcastPeriodRow } from '@/lib/broadcastHub'
import { routes } from '@/lib/routes'
import { formatIdDateTimeLabel } from '@/lib/shadcnDateFormat'
import { cn } from '@/lib/utils'
import { MailOpen, Megaphone } from 'lucide-vue-next'

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
    <div v-if="props.broadcasts.length > 0" class="overflow-x-auto rounded-xl border border-border/70">
        <Table aria-label="Daftar broadcast periode">
            <TableHeader>
                <TableRow>
                    <TableHead>Nama</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Jadwal</TableHead>
                    <TableHead class="text-right">Penerima</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="row in props.broadcasts" :key="row.id">
                    <TableCell>
                        <Link
                            :href="routes.admin.broadcasts.show(row.id)"
                            class="font-medium underline-offset-4 hover:underline"
                        >
                            {{ row.name }}
                        </Link>
                        <p v-if="row.subject" class="mt-0.5 truncate text-xs text-muted-foreground">
                            {{ row.subject }}
                        </p>
                    </TableCell>
                    <TableCell>
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
                    </TableCell>
                    <TableCell class="whitespace-nowrap text-xs tabular-nums text-muted-foreground">
                        {{ scheduleLabelOf(row.scheduled_at) }}
                    </TableCell>
                    <TableCell class="text-right text-xs tabular-nums">
                        {{ row.recipient_count.toLocaleString('id-ID') }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
    <div
        v-else
        class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border/80 px-4 py-8 text-center"
    >
        <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-full bg-muted">
            <MailOpen class="size-5 text-muted-foreground" />
        </span>
        <p class="text-sm font-medium">Belum ada broadcast untuk periode ini.</p>
        <p class="max-w-sm text-xs leading-relaxed text-muted-foreground">
            Buat broadcast pertama untuk pelamar periode ini.
        </p>
        <Button as-child variant="outline" size="sm" class="mt-1">
            <Link
                :href="routes.admin.broadcasts.create({ periodId: props.periodId })"
                :aria-label="'Kirim broadcast untuk ' + props.periodName"
            >
                <Megaphone class="mr-2 size-4" aria-hidden="true" />
                Kirim Broadcast
            </Link>
        </Button>
    </div>
</template>
