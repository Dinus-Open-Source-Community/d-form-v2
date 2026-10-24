<script setup lang="ts">
import Navbar from '@/components/modules/landing/Navbar.vue';
import Footer from '@/components/modules/landing/Footer.vue';
</script>

<template>
    <div class="relative min-h-screen bg-background font-sans antialiased">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[100] focus:rounded-lg focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:shadow"
            >Lewati ke konten</a
        >
        <Navbar />
        <main id="main">
            <slot />
        </main>
        <Footer />
    </div>
</template>
