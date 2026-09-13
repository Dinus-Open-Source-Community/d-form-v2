<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

interface Period {
    id: string
    name: string
    slug: string
    status: string
    status_label: string
    description: string | null
    registration_opens_at: string | null
    registration_closes_at: string | null
    interview_starts_at: string | null
    interview_ends_at: string | null
    finalization_deadline_at: string | null
    applications_count: number
}

const props = defineProps<{ period: Period }>()

onMounted(() => {
    setTopbar({ title: props.period.name, subtitle: 'Detail periode OpRec' })
})

function openPeriod() {
    router.post(routes.admin.recruitment.periods.open(props.period.id))
}

function closePeriod() {
    router.post(routes.admin.recruitment.periods.close(props.period.id))
}
</script>

<template>
    <Head :title="period.name" />

    <div class="mx-auto flex max-w-3xl flex-col gap-6">
        <PageHeader
            :title="period.name"
            :subtitle="`Status: ${period.status_label} · ${period.applications_count} applicant`"
            :back-href="routes.admin.recruitment.periods.index"
        >
            <template #actions>
                <Button v-if="period.status === 'draft' || period.status === 'closed'" size="sm" @click="openPeriod">
                    Buka pendaftaran
                </Button>
                <Button v-if="period.status === 'open'" variant="secondary" size="sm" @click="closePeriod">
                    Tutup pendaftaran
                </Button>
                <Button as-child variant="outline" size="sm">
                    <Link :href="routes.admin.recruitment.periods.edit(period.id)">Edit</Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="rounded-2xl border-border/70">
            <CardContent class="space-y-4 p-6">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Slug</dt>
                        <dd class="font-mono">{{ period.slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Status</dt>
                        <dd>{{ period.status_label }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted-foreground">Deskripsi</dt>
                        <dd>{{ period.description || '—' }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
