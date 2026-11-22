<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import FormFillLayout from '@/layouts/FormFillLayout.vue';
import { Button } from '@/components/ui/button';
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue';
import { currentOrigin } from '@/adapters/browser/navigation';
import { ClipboardList } from 'lucide-vue-next';

defineOptions({ layout: FormFillLayout });

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
    fillUrl: string;
    canSubmitAgain: boolean;
    registrationUrl: string | null;
}>();

const storysetIllustrationUrl = `${currentOrigin()}/illustrations/forms-rafiki.svg`;

const successContent = computed(() => {
    const html = props.form.success_content;
    if (!html || !html.trim() || html.trim() === '<p></p>') return null;
    return html;
});
</script>

<template>
    <Head :title="`Berhasil: ${form.title}`" />

    <div class="mx-auto max-w-3xl px-4 py-4 sm:px-6 sm:py-8">
        <section class="flex flex-col items-center text-center" aria-labelledby="success-title">
            <figure class="mb-8 w-full max-w-md">
                <img
                    :src="storysetIllustrationUrl"
                    alt=""
                    width="500"
                    height="350"
                    fetchpriority="high"
                    class="mx-auto h-auto w-full"
                />
            </figure>

            <div class="max-w-xl">
                <h1 id="success-title" class="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                    Formulir terkirim
                </h1>
                <p class="text-muted-foreground mt-3 text-base leading-7">
                    Terimakasih, jawaban Anda sudah kami terima.
                </p>
            </div>

            <section
                class="border-border bg-muted/20 text-foreground mt-8 w-full max-w-2xl rounded-2xl border p-5 text-left sm:p-6"
                aria-label="Pesan setelah pengiriman"
            >
                <div class="max-w-prose text-base leading-7">
                    <TiptapRichHtml v-if="successContent" :html="successContent" />
                    <p v-else class="text-muted-foreground">
                        Terimakasih, jawaban Anda untuk
                        <span class="text-foreground font-medium">{{ form.title }}</span>
                        telah kami terima.
                    </p>
                </div>
            </section>

            <div class="mt-8 flex w-full max-w-2xl flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
                <Button
                    v-if="isRegistrationForm && registrationUrl"
                    as-child
                    class="min-h-[44px] w-full font-semibold sm:w-auto"
                >
                    <Link :href="registrationUrl">
                        <ClipboardList class="mr-2 size-4" aria-hidden="true" />
                        Lihat detail pendaftaran
                    </Link>
                </Button>
                <Button
                    v-if="canSubmitAgain"
                    as-child
                    :variant="isRegistrationForm && registrationUrl ? 'outline' : 'default'"
                    class="min-h-[44px] w-full font-semibold sm:w-auto"
                >
                    <Link :href="fillUrl">Isi form lagi</Link>
                </Button>
                <Button
                    v-if="!isRegistrationForm"
                    as-child
                    variant="outline"
                    class="min-h-[44px] w-full font-semibold sm:w-auto"
                >
                    <Link :href="eventUrl">Lihat acara</Link>
                </Button>
            </div>
        </section>
    </div>
</template>
