<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import FormFillLayout from '@/layouts/FormFillLayout.vue';
import { Button } from '@/components/ui/button';
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue';
import { CheckCircle2, ClipboardList } from 'lucide-vue-next';

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

const successContent = computed(() => {
    const html = props.form.success_content;
    if (!html || !html.trim() || html.trim() === '<p></p>') return null;
    return html;
});
</script>

<template>
    <Head :title="`Berhasil: ${form.title}`" />

    <div class="mx-auto w-full max-w-3xl px-5 py-8 sm:px-8 sm:py-12">
        <section class="mx-auto flex max-w-xl flex-col items-center text-center" aria-labelledby="success-title">
            <img
                :src="'/illustrations/forms-rafiki.png'"
                alt=""
                width="500"
                height="500"
                fetchpriority="high"
                class="mb-2 aspect-square h-auto w-44 object-contain sm:w-48"
                aria-hidden="true"
            />
            <a
                href="https://storyset.com/illustration/forms/rafiki"
                target="_blank"
                rel="noopener noreferrer"
                class="text-muted-foreground mb-5 inline-flex min-h-[44px] items-center justify-center text-xs underline underline-offset-2"
            >
                Illustration by Storyset
            </a>

            <CheckCircle2 class="text-success mb-3 size-12 stroke-[1.5]" aria-hidden="true" />

            <h1 id="success-title" class="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                Formulir terkirim
            </h1>
            <p class="text-muted-foreground mt-3 max-w-lg text-base leading-relaxed break-words">
                {{ form.title }} · {{ event.title }}
            </p>

            <div class="text-foreground mt-6 w-full max-w-prose text-base leading-7">
                <TiptapRichHtml v-if="successContent" :html="successContent" />
                <p v-else class="text-muted-foreground">
                    Terima kasih, jawaban Anda untuk
                    <span class="text-foreground font-medium">{{ form.title }}</span>
                    telah kami terima.
                </p>
            </div>

            <div class="mt-7 flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
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
                    as-child
                    :variant="isRegistrationForm && registrationUrl ? 'outline' : 'default'"
                    class="min-h-[44px] w-full font-semibold sm:w-auto"
                >
                    <Link :href="canSubmitAgain ? fillUrl : eventUrl">
                        {{ canSubmitAgain ? 'Isi form lagi' : 'Lihat acara' }}
                    </Link>
                </Button>
            </div>
        </section>
    </div>
</template>
