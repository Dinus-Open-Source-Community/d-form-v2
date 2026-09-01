<script setup lang="ts">
import { onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import EventDashboardForm from '@/components/modules/dashboard/events/EventDashboardForm.vue';
import { routes } from '@/lib/routes';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';

defineOptions({ layout: DashboardFocusLayout });

defineProps<{
    options?: {
        categories: { value: string; label: string }[];
        sessions: { value: string; label: string }[];
    };
}>();

onMounted(() => {
    setTopbar({ title: 'Buat acara', subtitle: 'Lengkapi informasi — simpan draf atau terbitkan' });
});
</script>

<template>
    <Head title="Buat acara" />

    <div class="flex flex-col gap-8">
        <EventDashboardForm variant="create" :options="options" />
    </div>
</template>
