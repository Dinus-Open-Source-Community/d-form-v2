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
    const el = document.getElementById('features');
    if (el) observer.observe(el);
});

const features = [
    {
        icon: 'form',
        title: 'Dynamic Form Builder',
        description: 'Drag-and-drop form builder with 20+ field types. Create registration forms, surveys, and feedback forms in minutes.',
    },
    {
        icon: 'event',
        title: 'Event Management',
        description: 'Create and manage events with ease. Set capacity limits, track registrations, and send automated notifications.',
    },
    {
        icon: 'analytics',
        title: 'Real-time Analytics',
        description: 'Monitor response rates, visualize data with charts, and export detailed reports for every event and form.',
    },
    {
        icon: 'team',
        title: 'Team Collaboration',
        description: 'Invite team members, assign roles, and collaborate seamlessly on events with real-time sync.',
    },
    {
        icon: 'integrate',
        title: 'Easy Integrations',
        description: 'Connect with Google Sheets, Slack, email platforms, and more through our flexible API and webhooks.',
    },
    {
        icon: 'secure',
        title: 'Enterprise Security',
        description: 'Bank-grade encryption, GDPR compliance, and granular access controls to keep your data safe.',
    },
];
</script>

<template>
    <section id="features" class="relative bg-[#F8FAFC] py-24 lg:py-32">
        <!-- Top border decoration -->
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-[#0A84DC]/20 to-transparent" />

        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <!-- Section header -->
            <div
                :class="[
                    'mx-auto max-w-2xl text-center transition-all duration-700',
                    isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-[#0A84DC]/15 bg-white px-4 py-1.5">
                    <span class="text-xs font-semibold tracking-wide text-[#0A84DC] uppercase">Features</span>
                </div>
                <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    Everything you need to
                    <span class="text-[#0A84DC]">organize events</span>
                </h2>
                <p class="mt-4 text-lg leading-relaxed text-gray-500">
                    Powerful tools designed to simplify every aspect of event management,
                    from registration to analytics.
                </p>
            </div>

            <!-- Feature cards -->
            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(feature, index) in features"
                    :key="feature.title"
                    :class="[
                        'group rounded-2xl border border-gray-100 bg-white p-8 transition-all duration-500 hover:-translate-y-1 hover:border-[#0A84DC]/20 hover:shadow-xl hover:shadow-[#0A84DC]/5',
                        isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${200 + index * 100}ms` }"
                >
                    <!-- Icon -->
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-[#0A84DC]/8 transition-colors group-hover:bg-[#0A84DC]/15">
                        <!-- Form icon -->
                        <svg v-if="feature.icon === 'form'" class="h-6 w-6 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                            <path d="M14 2v6h6" /><path d="M16 13H8" /><path d="M16 17H8" /><path d="M10 9H8" />
                        </svg>
                        <!-- Event icon -->
                        <svg v-if="feature.icon === 'event'" class="h-6 w-6 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="4" rx="2" /><path d="M16 2v4" /><path d="M8 2v4" /><path d="M3 10h18" /><path d="M8 14h.01" /><path d="M12 14h.01" /><path d="M16 14h.01" />
                        </svg>
                        <!-- Analytics icon -->
                        <svg v-if="feature.icon === 'analytics'" class="h-6 w-6 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18" /><path d="M18 17V9" /><path d="M13 17V5" /><path d="M8 17v-3" />
                        </svg>
                        <!-- Team icon -->
                        <svg v-if="feature.icon === 'team'" class="h-6 w-6 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <!-- Integrate icon -->
                        <svg v-if="feature.icon === 'integrate'" class="h-6 w-6 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v4" /><path d="m6.8 15-3.5 2" /><path d="m20.7 17-3.5-2" /><path d="M6.8 9 3.3 7" /><path d="m20.7 7-3.5 2" /><path d="M12 22v-4" /><circle cx="12" cy="12" r="4" />
                        </svg>
                        <!-- Secure icon -->
                        <svg v-if="feature.icon === 'secure'" class="h-6 w-6 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" /><path d="m9 12 2 2 4-4" />
                        </svg>
                    </div>

                    <h3 class="mb-2 text-lg font-bold text-gray-900">{{ feature.title }}</h3>
                    <p class="text-sm leading-relaxed text-gray-500">{{ feature.description }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
