<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue';
import { CheckCircle2, ArrowRight, ClipboardList } from 'lucide-vue-next';

defineOptions({ layout: DashboardLayout });

const props = defineProps<{
    event: { id: string; slug: string; title: string };
    form: {
        id: string;
        title: string;
        purpose: 'registration' | 'other';
        success_content: string | null;
    };
    isRegistrationForm: boolean;
    eventUrl: string;
    registrationUrl: string | null;
}>();

const successContent = computed(() => {
    const html = props.form.success_content;
    if (!html || !html.trim() || html.trim() === '<p></p>') return null;
    return html;
});
</script>

<template>
    <Head :title="`Berhasil — ${form.title}`" />

    <div class="mx-auto w-full max-w-xl py-4 sm:py-8">
        <Card class="overflow-hidden rounded-2xl border shadow-sm">
            <CardHeader class="bg-muted/20 border-b px-6 py-5">
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="text-success size-5" aria-hidden="true" />
                    <CardTitle class="font-display text-base font-semibold tracking-tight sm:text-lg">
                        Formulir terkirim
                    </CardTitle>
                </div>
                <CardDescription class="mt-1.5 text-sm"> {{ form.title }} · {{ event.title }} </CardDescription>
            </CardHeader>
            <CardContent class="px-6 py-6">
                <TiptapRichHtml v-if="successContent" :html="successContent" />
                <p v-else class="text-muted-foreground text-sm leading-relaxed">
                    Terima kasih — jawaban Anda untuk
                    <span class="text-foreground font-medium">{{ form.title }}</span>
                    telah kami terima.
                </p>
            </CardContent>
            <CardFooter class="flex flex-wrap gap-3 border-t px-6 py-4">
                <Button v-if="isRegistrationForm && registrationUrl" as-child class="h-10 font-semibold shadow-sm">
                    <Link :href="registrationUrl">
                        <ClipboardList class="mr-2 size-4" aria-hidden="true" />
                        Lihat detail pendaftaran
                    </Link>
                </Button>
                <Button
                    as-child
                    :variant="isRegistrationForm && registrationUrl ? 'outline' : 'default'"
                    class="h-10 font-semibold"
                >
                    <Link :href="eventUrl">
                        Kembali ke acara
                        <ArrowRight class="ml-2 size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
