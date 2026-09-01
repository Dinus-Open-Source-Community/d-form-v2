<script setup lang="ts">
import { onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import EventDashboardForm from '@/components/modules/dashboard/events/EventDashboardForm.vue';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';

defineOptions({ layout: DashboardFocusLayout });

defineProps<{
    event: IEvent;
    options: { categories: { value: string; label: string }[]; sessions: { value: string; label: string }[] };
}>();

onMounted(() => {
    setTopbar({ title: 'Ubah acara', subtitle: 'Perbarui detail — banner baru opsional' });
});
</script>

<template>
    <Head title="Ubah acara" />

    <div class="flex flex-col gap-8">
        <EventDashboardForm variant="edit" :event="event" :options="options" />
    </div>
</template>
