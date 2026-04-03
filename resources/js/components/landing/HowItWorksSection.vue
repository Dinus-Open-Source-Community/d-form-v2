<script setup lang="ts">
import { ref, onMounted } from 'vue';

const isVisible = ref(false);

onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) isVisible.value = true;
        },
        { threshold: 0.15 }
    );
    const el = document.getElementById('how-it-works');
    if (el) observer.observe(el);
});

const steps = [
    {
        number: '01',
        title: 'Create Your Event',
        description: 'Set up your event details — name, date, location, capacity, and description. Go live in under 2 minutes.',
    },
    {
        number: '02',
        title: 'Build Your Form',
        description: 'Use our drag-and-drop builder to create custom registration forms with validations and conditional logic.',
    },
    {
        number: '03',
        title: 'Collect & Analyze',
        description: 'Share your form link, collect responses in real-time, and gain insights with built-in analytics dashboards.',
    },
];
</script>

<template>
    <section id="how-it-works" class="relative bg-white py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <!-- Section header -->
            <div
                :class="[
                    'mx-auto max-w-2xl text-center transition-all duration-700',
                    isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-[#0A84DC]/15 bg-[#0A84DC]/5 px-4 py-1.5">
                    <span class="text-xs font-semibold tracking-wide text-[#0A84DC] uppercase">How It Works</span>
                </div>
                <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    Three simple steps to
                    <span class="text-[#0A84DC]">get started</span>
                </h2>
                <p class="mt-4 text-lg leading-relaxed text-gray-500">
                    From event creation to data collection — everything is designed to be intuitive and fast.
                </p>
            </div>

            <!-- Steps -->
            <div class="relative mt-20">
                <!-- Connecting SVG line (desktop) -->
                <div class="pointer-events-none absolute top-[60px] right-0 left-0 hidden lg:block">
                    <svg class="mx-auto" width="100%" height="4" preserveAspectRatio="none">
                        <line x1="16.5%" y1="2" x2="83.5%" y2="2" stroke="#0A84DC" stroke-width="2" stroke-dasharray="8 6" opacity="0.2">
                            <animate attributeName="stroke-dashoffset" from="28" to="0" dur="2s" repeatCount="indefinite" />
                        </line>
                    </svg>
                </div>

                <div class="grid gap-12 lg:grid-cols-3 lg:gap-8">
                    <div
                        v-for="(step, index) in steps"
                        :key="step.number"
                        :class="[
                            'relative text-center transition-all duration-700',
                            isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                        ]"
                        :style="{ transitionDelay: `${300 + index * 200}ms` }"
                    >
                        <!-- Step number circle -->
                        <div class="relative mx-auto mb-8">
                            <div class="relative z-10 mx-auto flex h-[120px] w-[120px] items-center justify-center rounded-3xl border border-gray-100 bg-white shadow-lg shadow-gray-100/50">
                                <span class="text-4xl font-extrabold text-[#0A84DC]/20">{{ step.number }}</span>
                            </div>
                            <!-- Animated ring -->
                            <div class="absolute inset-0 z-0 mx-auto h-[120px] w-[120px]">
                                <svg viewBox="0 0 120 120" class="h-full w-full">
                                    <circle cx="60" cy="60" r="56" fill="none" stroke="#0A84DC" stroke-width="1" opacity="0.1" stroke-dasharray="8 6">
                                        <animateTransform attributeName="transform" type="rotate" from="0 60 60" to="360 60 60" dur="20s" repeatCount="indefinite" />
                                    </circle>
                                </svg>
                            </div>
                        </div>

                        <h3 class="mb-3 text-xl font-bold text-gray-900">{{ step.title }}</h3>
                        <p class="mx-auto max-w-xs text-sm leading-relaxed text-gray-500">{{ step.description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
