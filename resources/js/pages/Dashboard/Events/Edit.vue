<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import EventDashboardForm from '@/components/modules/dashboard/events/EventDashboardForm.vue';
import { routes } from '@/lib/routes';

defineOptions({ layout: DashboardFocusLayout });

defineProps<{
    event: IEvent;
    options: { categories: { value: string; label: string }[]; sessions: { value: string; label: string }[] };
}>();
</script>

<template>
    <Head title="Ubah acara" />

    <div class="flex flex-col gap-8">
        <PageHeader
            title="Ubah acara"
            subtitle="Perbarui detail di bawah. Banner baru bersifat opsional — kosongkan jika tidak diganti."
            :back-href="routes.admin.events.show(event.id)"
        />

        <EventDashboardForm variant="edit" :event="event" :options="options" />
    </div>
</template>
