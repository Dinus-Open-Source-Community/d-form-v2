<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
    FileText,
    Calendar,
    BarChart3,
    Users,
    Webhook,
    ShieldCheck,
    LayoutGrid,
    BellRing,
    Smartphone,
} from 'lucide-vue-next'
import type { FeatureItem } from '@/types/landing'

const visible = ref(false)
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) visible.value = true
        },
        { threshold: 0.1 },
    )
    const el = document.getElementById('features-grid')
    if (el) observer.observe(el)
})

const features: readonly FeatureItem[] = [
    { icon: FileText, title: 'Dynamic Form Builder', description: 'Drag-and-drop interface with 20+ field types — text, select, file upload, date picker, rating, and more.' },
    { icon: Calendar, title: 'Event Management', description: 'Create unlimited events with custom branding. Set capacity limits and track registrations in real-time.' },
    { icon: BarChart3, title: 'Real-time Analytics', description: 'Auto-generated charts for every form. Monitor response rates, track completion times, and export data.' },
    { icon: Users, title: 'Team Collaboration', description: 'Invite unlimited team members with role-based access control. Collaborate with real-time sync.' },
    { icon: Webhook, title: 'Webhooks & API', description: 'Connect DForm with your tools through our REST API and real-time webhooks.' },
    { icon: ShieldCheck, title: 'Enterprise Security', description: 'End-to-end encryption, GDPR compliance, and SOC 2 Type II certification.' },
    { icon: LayoutGrid, title: 'Form Templates', description: 'Start fast with 50+ professionally designed templates for registrations, surveys, and more.' },
    { icon: BellRing, title: 'Smart Notifications', description: 'Automated email and push notifications for new submissions, reminders, and status updates.' },
    { icon: Smartphone, title: 'Mobile Optimized', description: 'Every form is fully responsive. Support for PWA and native-like experience on every screen.' },
]
</script>

<template>
    <section id="features-grid" class="relative overflow-hidden border-y border-border bg-card/40 py-20 lg:py-28">
        <div class="app-grid pointer-events-none absolute inset-0 opacity-30"></div>
        <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mb-14 text-center">
                <h2 class="font-display text-3xl font-bold tracking-[-0.035em] text-foreground sm:text-4xl lg:text-5xl">
                    All the tools, <span class="text-primary">one platform.</span>
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-base leading-relaxed text-muted-foreground">
                    Everything you need to run events at every scale.
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(f, i) in features"
                    :key="f.title"
                    :class="[
                        'app-surface group p-6 transition-[opacity,transform] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${i * 70}ms` }"
                >
                    <div class="mb-4 grid size-10 place-items-center rounded-xl border border-primary/15 bg-primary/8 text-primary transition-colors group-hover:border-primary/30 group-hover:bg-primary/12">
                        <component :is="f.icon" class="size-4" :stroke-width="2" />
                    </div>
                    <h3 class="font-display mb-1.5 text-base font-bold tracking-[-0.015em] text-foreground">
                        {{ f.title }}
                    </h3>
                    <p class="text-sm leading-relaxed text-muted-foreground">{{ f.description }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
