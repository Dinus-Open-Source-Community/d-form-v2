<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

interface LogRow {
    id: string
    action: string
    actor_type: string
    actor: { id: string; name: string } | null
    application: { id: string; registration_number: string; full_name: string } | null
    created_at: string | null
}

const props = defineProps<{
    logs: { data: LogRow[]; links: { url: string | null; label: string; active: boolean }[] }
    periodOptions: { id: string; name: string }[]
    query: { period_id: string | null; action: string | null }
}>()

onMounted(() => {
    setTopbar({ title: 'Activity log Open Recruitment', subtitle: 'Audit trail keputusan staff' })
})

function applyFilters(periodId: string, action: string) {
    router.get(
        routes.admin.recruitment.activityLogs.index,
        {
            period_id: periodId || undefined,
            action: action || undefined,
        },
        { preserveState: true },
    )
}
</script>

<template>
    <Head title="Activity Log Open Recruitment" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <Card class="rounded-2xl border-border/70">
            <CardContent class="flex flex-wrap gap-3 p-4">
                <select
                    class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    :value="query.period_id ?? ''"
                    @change="applyFilters(($event.target as HTMLSelectElement).value, query.action ?? '')"
                >
                    <option value="">Semua periode</option>
                    <option v-for="period in periodOptions" :key="period.id" :value="period.id">
                        {{ period.name }}
                    </option>
                </select>
                <input
                    type="search"
                    class="border-input bg-background h-9 min-w-[200px] flex-1 rounded-md border px-3 text-sm"
                    placeholder="Filter action..."
                    :value="query.action ?? ''"
                    @change="applyFilters(query.period_id ?? '', ($event.target as HTMLInputElement).value)"
                />
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70">
            <CardContent class="divide-y p-0">
                <div v-for="log in logs.data" :key="log.id" class="space-y-1 p-4 text-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="font-medium">{{ log.action }}</p>
                        <p class="text-muted-foreground text-xs">
                            {{ log.created_at ? new Date(log.created_at).toLocaleString('id-ID') : '—' }}
                        </p>
                    </div>
                    <p class="text-muted-foreground text-xs">
                        {{ log.actor?.name ?? log.actor_type }}
                        <template v-if="log.application">
                            ·
                            <Link
                                :href="routes.admin.recruitment.applications.show(log.application.id)"
                                class="text-primary underline"
                            >
                                {{ log.application.registration_number }}
                            </Link>
                        </template>
                    </p>
                </div>
                <p v-if="logs.data.length === 0" class="text-muted-foreground p-6 text-center text-sm">
                    Tidak ada activity log.
                </p>
            </CardContent>
        </Card>

        <div v-if="logs.links.length > 3" class="flex flex-wrap gap-2">
            <Button
                v-for="link in logs.links"
                :key="link.label"
                as-child
                size="sm"
                :variant="link.active ? 'default' : 'outline'"
                :disabled="!link.url"
            >
                <Link v-if="link.url" :href="link.url" preserve-state>{{ link.label }}</Link>
                <span v-else>{{ link.label }}</span>
            </Button>
        </div>
    </div>
</template>
