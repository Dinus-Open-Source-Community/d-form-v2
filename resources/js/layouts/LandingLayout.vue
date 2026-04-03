<script setup lang="ts">
import Navbar from '@/components/landing/Navbar.vue';
import Footer from '@/components/landing/Footer.vue';
</script>

<template>
    <div class="min-h-screen bg-white font-sans antialiased">
        <Navbar />
        <main>
            <slot />
        </main>
        <Footer />
    </div>
</template>
