<script setup lang="ts">
import { onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent } from '@/components/ui/card'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

defineProps<{ datasets: { data: Array<{ id: string; name: string; source_type: string; recipients_count: number }> } }>()

onMounted(() => {
    setTopbar({ title: 'Custom Datasets', subtitle: 'Kelola dataset manual/CSV untuk broadcast' })
})
</script>

<template>
    <Head title="Custom Datasets" />
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 pb-8">
        <h1 class="text-2xl font-semibold">Custom Datasets</h1>
        <Card class="rounded-2xl">
            <CardContent class="p-4 text-sm">
                <p class="text-muted-foreground">
                    Custom dataset dibuat saat Generate Snapshot (manual + CSV). Daftar reusable menyusul;
                    untuk v1.0 gunakan snapshot per broadcast sebagai source of truth.
                </p>
                <ul class="mt-3 flex flex-col gap-1">
                    <li v-for="d in datasets.data" :key="d.id" class="flex justify-between border-b py-1">
                        <span>{{ d.name }} ({{ d.source_type }})</span>
                        <span class="font-mono text-xs">{{ d.recipients_count }} recipients</span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
