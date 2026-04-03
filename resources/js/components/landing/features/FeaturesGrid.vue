<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) visible.value = true; },
        { threshold: 0.1 }
    );
    const el = document.getElementById('features-grid');
    if (el) observer.observe(el);
});

const features = [
    { icon: 'form', title: 'Dynamic Form Builder', desc: 'Drag-and-drop interface with 20+ field types — text, select, file upload, date picker, rating, and more.' },
    { icon: 'event', title: 'Event Management', desc: 'Create unlimited events with custom branding. Set capacity limits and track registrations in real-time.' },
    { icon: 'chart', title: 'Real-time Analytics', desc: 'Auto-generated charts for every form. Monitor response rates, track completion times, and export data.' },
    { icon: 'team', title: 'Team Collaboration', desc: 'Invite unlimited team members with role-based access control. Collaborate with real-time sync.' },
    { icon: 'integrate', title: 'Webhooks & API', desc: 'Connect DForm with your tools through our REST API and real-time webhooks.' },
    { icon: 'shield', title: 'Enterprise Security', desc: 'End-to-end encryption, GDPR compliance, and SOC 2 Type II certification.' },
    { icon: 'template', title: 'Form Templates', desc: 'Start fast with 50+ professionally designed templates for registrations, surveys, and more.' },
    { icon: 'notify', title: 'Smart Notifications', desc: 'Automated email and push notifications for new submissions, reminders, and status updates.' },
    { icon: 'mobile', title: 'Mobile Optimized', desc: 'Every form is fully responsive. Support for PWA and native-like experience on any screen.' },
];

const iconPaths: Record<string, string> = {
    form: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z|M14 2v6h6|M16 13H8|M16 17H8',
    event: 'R3,4,18,18,2|M16 2v4|M8 2v4|M3 10h18',
    chart: 'M3 3v18h18|M18 17V9|M13 17V5|M8 17v-3',
    team: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2|C9,7,4|M22 21v-2a4 4 0 0 0-3-3.87|M16 3.13a4 4 0 0 1 0 7.75',
    integrate: 'm16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z|m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z|M7 21h10|M12 3v18|M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2',
    shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10|m9 12 2 2 4-4',
    template: 'R3,3,18,18,2|M3 9h18|M9 21V9',
    notify: 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9|M10.3 21a1.94 1.94 0 0 0 3.4 0',
    mobile: 'R5,2,14,20,2|M12 18h.01',
};
</script>

<template>
    <section id="features-grid" class="bg-[#F9FAFB] py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mb-12 text-center">
                <h2 class="text-2xl font-extrabold tracking-tight text-[#111827] sm:text-3xl">
                    All the tools, <span class="text-[#0A84DC]">one platform.</span>
                </h2>
                <p class="mt-3 text-sm text-[#6B7280]">Everything you need to run events at any scale.</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(f, i) in features" :key="f.title"
                    :class="['group rounded-xl border border-[#E5E7EB] bg-white p-7 transition-all duration-500 hover:-translate-y-0.5 hover:border-[#0A84DC]/15 hover:shadow-md', visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0']"
                    :style="{ transitionDelay: `${i * 80}ms` }"
                >
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-[#0A84DC]/[0.06] transition-colors group-hover:bg-[#0A84DC]/[0.12]">
                        <svg class="h-5 w-5 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <template v-if="f.icon === 'form'"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></template>
                            <template v-if="f.icon === 'event'"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></template>
                            <template v-if="f.icon === 'chart'"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></template>
                            <template v-if="f.icon === 'team'"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></template>
                            <template v-if="f.icon === 'integrate'"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></template>
                            <template v-if="f.icon === 'shield'"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></template>
                            <template v-if="f.icon === 'template'"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></template>
                            <template v-if="f.icon === 'notify'"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></template>
                            <template v-if="f.icon === 'mobile'"><rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/></template>
                        </svg>
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-[#111827]">{{ f.title }}</h3>
                    <p class="text-xs leading-relaxed text-[#6B7280]">{{ f.desc }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
