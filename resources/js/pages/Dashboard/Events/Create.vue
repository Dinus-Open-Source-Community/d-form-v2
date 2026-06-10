<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import EventDashboardForm from '@/components/modules/dashboard/events/EventDashboardForm.vue';
import { routes } from '@/lib/routes';

defineOptions({ layout: DashboardFocusLayout });

defineProps<{
    options?: {
        categories: { value: string; label: string }[];
        sessions: { value: string; label: string }[];
    };
}>();
</script>

<template>
    <Head title="Buat acara" />

    <div class="flex flex-col gap-8">
        <PageHeader
            title="Buat acara"
            subtitle="Lengkapi informasi berikut. Simpan dulu sebagai draf atau langsung terbitkan jika sudah siap."
            :back-href="routes.admin.events.index"
        />

        <EventDashboardForm variant="create" :options="options" />
    </div>
</template>
