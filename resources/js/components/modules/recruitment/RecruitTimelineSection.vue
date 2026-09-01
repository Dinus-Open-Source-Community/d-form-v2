<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { CalendarDays, ClipboardList, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    period: {
        registrationStart: string;
        registrationEnd: string;
        interviewStart?: string;
        interviewEnd?: string;
    };
}>();

const fmt = (iso?: string): string =>
    iso ? new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '—';

const items = computed(() => [
    {
        icon: ClipboardList,
        title: 'Pendaftaran',
        range: `${fmt(props.period.registrationStart)} – ${fmt(props.period.registrationEnd)}`,
    },
    {
        icon: Users,
        title: 'Screening',
        range: 'Setelah pendaftaran ditutup',
    },
    {
        icon: CalendarDays,
        title: 'Interview',
        range: `${fmt(props.period.interviewStart)} – ${fmt(props.period.interviewEnd)}`,
    },
]);
</script>

<template>
    <section class="mx-auto max-w-5xl px-6 py-12">
        <div class="text-center">
            <h2 class="text-2xl font-bold tracking-tight">Timeline Seleksi</h2>
            <p class="text-muted-foreground mt-2">Tahapan proses Open Recruitment.</p>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <Card v-for="item in items" :key="item.title" class="rounded-2xl">
                <CardContent class="flex flex-col items-start gap-3 p-5">
                    <span class="rounded-lg bg-primary/10 p-2 text-primary">
                        <component :is="item.icon" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="font-semibold">{{ item.title }}</h3>
                        <p class="text-muted-foreground mt-1 text-sm">{{ item.range }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </section>
</template>
