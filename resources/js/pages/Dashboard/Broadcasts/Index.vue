<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { Plus } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface BroadcastRow {
    id: string
    name: string
    status: string
    scheduled_at: string | null
    total_recipients: number
    total_sent: number
    total_failed: number
    event_title: string | null
    created_at: string | null
}

interface Paginator {
    data: BroadcastRow[]
    current_page: number
    last_page: number
    total: number
}

defineProps<{ broadcasts: Paginator }>()

onMounted(() => {
    setTopbar({ title: 'Email Broadcasting', subtitle: 'Kirim email massal terjadwal via queue' })
})

function statusVariant(status: string): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'completed') return 'default'
    if (status === 'processing') return 'secondary'
    if (status === 'failed') return 'destructive'
    return 'outline'
}
</script>

<template>
    <Head title="Email Broadcasting" />
    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 pb-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Email Broadcasting</h1>
                <p class="text-muted-foreground mt-1.5">Dataset → Snapshot → Queue → Tracking</p>
            </div>
            <Button as-child>
                <Link :href="routes.admin.broadcasts.create"><Plus class="mr-2 size-4" />Buat Broadcast</Link>
            </Button>
        </div>

        <Card class="overflow-hidden rounded-2xl">
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Jadwal</TableHead>
                            <TableHead class="text-right">Terkirim</TableHead>
                            <TableHead class="text-right">Gagal</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in broadcasts.data" :key="row.id">
                            <TableCell class="font-medium">
                                <Link :href="routes.admin.broadcasts.show(row.id)" class="hover:underline">
                                    {{ row.name }}
                                </Link>
                                <div v-if="row.event_title" class="text-muted-foreground text-xs">{{ row.event_title }}</div>
                            </TableCell>
                            <TableCell><Badge :variant="statusVariant(row.status)">{{ row.status }}</Badge></TableCell>
                            <TableCell class="text-muted-foreground text-sm">{{ row.scheduled_at ?? '—' }}</TableCell>
                            <TableCell class="text-right">{{ row.total_sent }}/{{ row.total_recipients }}</TableCell>
                            <TableCell class="text-right">{{ row.total_failed }}</TableCell>
                        </TableRow>
                        <TableRow v-if="broadcasts.data.length === 0">
                            <TableCell colspan="5" class="text-muted-foreground py-10 text-center">
                                Belum ada broadcast. Klik “Buat Broadcast”.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </Card>
    </div>
</template>
