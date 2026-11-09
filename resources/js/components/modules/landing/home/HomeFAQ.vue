<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { findElementById } from '@/adapters/browser/domQuery';

const faqs = [
    {
        q: 'Apa itu DForm dan untuk siapa?',
        a: 'DForm adalah platform web yang dirancang untuk mempermudah pembuatan formulir pendaftaran acara. Cocok untuk panitia kampus, organisasi mahasiswa seperti DOSCOM, komunitas, dan siapa pun yang butuh alur pendaftaran digital yang terstruktur.',
    },
    {
        q: 'Apakah DForm gratis digunakan?',
        a: 'Ya, DForm sepenuhnya gratis. Anda bisa langsung membuat akun, menambahkan acara, merancang formulir, dan mengumpulkan data peserta tanpa biaya apa pun.',
    },
    {
        q: 'Bagaimana cara membuat formulir pendaftaran?',
        a: 'Setelah login, buat acara baru lalu masuk ke Form Builder. Anda bisa menambahkan berbagai jenis field (teks, dropdown, checkbox, radio, dsb.) dengan cara drag & drop. Atur mana yang wajib diisi, tambahkan opsi, lalu publikasikan formulir.',
    },
    {
        q: 'Jenis field apa saja yang tersedia?',
        a: 'DForm mendukung text input, textarea, dropdown/select, checkbox, radio button, dan beberapa tipe lainnya. Setiap field bisa dikustomisasi label, placeholder, dan aturan validasinya.',
    },
    {
        q: 'Bisa mengelola lebih dari satu acara?',
        a: 'Tentu. Setiap acara memiliki formulir dan data peserta tersendiri. Anda bisa beralih antar acara dari dasbor tanpa khawatir data tercampur.',
    },
    {
        q: 'Bagaimana peserta mengisi formulir?',
        a: 'Cukup bagikan link formulir yang sudah dipublikasikan. Peserta tidak perlu membuat akun — mereka bisa langsung mengisi formulir dari browser di perangkat apa pun.',
    },
    {
        q: 'Apakah data peserta aman?',
        a: 'Ya. Data dilindungi oleh sistem autentikasi dan otorisasi. Hanya penyelenggara yang berwenang yang bisa mengakses, melihat, dan mengekspor data peserta.',
    },
    {
        q: 'Bisa mengekspor data pendaftaran?',
        a: 'Ya, fitur ekspor tersedia sehingga Anda bisa mengunduh data peserta untuk keperluan administrasi, pelaporan, atau pengolahan lebih lanjut.',
    },
    {
        q: 'Apakah formulir bisa diakses dari HP?',
        a: 'Ya, seluruh halaman DForm — termasuk formulir, dasbor, dan form builder — dirancang responsif dan bisa diakses dengan nyaman dari desktop, tablet, maupun ponsel.',
    },
];

const visible = ref(false);
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => {
            if (e?.isIntersecting) {
                visible.value = true;
                obs.disconnect();
            }
        },
        { threshold: 0.08 }
    );
    const el = findElementById('section-faq');
    if (el !== null) obs.observe(el);
});
</script>

<template>
    <section id="section-faq" class="py-24 md:py-32">
        <div class="mx-auto max-w-3xl px-6 lg:px-10">
            <div
                :class="[
                    'mb-12 text-center transition-all duration-500',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                ]"
            >
                <p class="text-primary text-xs font-semibold tracking-[0.2em] uppercase">FAQ</p>
                <h2 class="font-display text-foreground mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                    Pertanyaan yang sering diajukan
                </h2>
                <p class="text-muted-foreground mx-auto mt-3 max-w-lg text-base leading-relaxed">
                    Belum yakin? Temukan jawaban atas pertanyaan umum tentang DForm di bawah ini.
                </p>
            </div>

            <div
                :class="[
                    'transition-all delay-75 duration-500',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-5 opacity-0',
                ]"
            >
                <Accordion type="single" collapsible class="w-full">
                    <AccordionItem v-for="(faq, i) in faqs" :key="faq.q" :value="`faq-${i}`" class="border-border/40">
                        <AccordionTrigger
                            class="data-[state=open]:text-primary py-5 text-left text-sm leading-snug font-medium hover:no-underline sm:text-base"
                        >
                            {{ faq.q }}
                        </AccordionTrigger>
                        <AccordionContent>
                            <p class="text-muted-foreground pb-4 text-sm leading-relaxed">
                                {{ faq.a }}
                            </p>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>
            </div>
        </div>
    </section>
</template>
