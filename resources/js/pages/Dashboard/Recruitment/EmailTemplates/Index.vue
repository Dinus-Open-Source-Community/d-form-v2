<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

defineProps<{
    templates: {
        id: string
        event_type: string
        subject: string
        is_active: boolean
        available_variables: string[]
    }[]
}>()

onMounted(() => {
    setTopbar({ title: 'Template email OpRec', subtitle: 'Kelola notifikasi applicant & staff' })
})
</script>

<template>
    <Head title="Template Email OpRec" />

    <div class="mx-auto flex max-w-4xl flex-col gap-6">
        <PageHeader
            title="Template email"
            subtitle="Edit subject dan body HTML untuk setiap event notifikasi."
            :back-href="routes.admin.recruitment.index"
        />

        <Card class="rounded-2xl border-border/70">
            <CardContent class="divide-y p-0">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="flex flex-wrap items-center justify-between gap-3 p-4"
                >
                    <div>
                        <p class="font-medium">{{ template.event_type }}</p>
                        <p class="text-muted-foreground text-sm">{{ template.subject }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge :variant="template.is_active ? 'default' : 'secondary'">
                            {{ template.is_active ? 'Aktif' : 'Nonaktif' }}
                        </Badge>
                        <Button as-child size="sm" variant="outline">
                            <Link :href="routes.admin.recruitment.emailTemplates.edit(template.id)">
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
