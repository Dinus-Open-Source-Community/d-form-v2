<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) visible.value = true;
        },
        { threshold: 0.1 },
    );
    const el = document.getElementById('feat-integrations');
    if (el) observer.observe(el);
});
</script>

<template>
    <section id="feat-integrations" class="overflow-hidden bg-[#F9FAFB] py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div
                :class="[
                    'mx-auto max-w-2xl text-center transition-all duration-700',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <h2 class="text-2xl font-extrabold tracking-tight text-[#111827] sm:text-3xl">
                    Connects with your <span class="text-[#0A84DC]">favorite tools</span>
                </h2>
                <p class="mt-3 text-sm text-[#6B7280]">
                    Seamlessly integrate DForm with the tools you already use.
                </p>
            </div>
        </div>

        <!-- Marquee rows -->
        <div class="mt-16 flex flex-col gap-5">
            <!-- Row 1: Left to Right -->
            <div class="marquee-wrap">
                <div class="marquee-track marquee-ltr">
                    <template v-for="copy in 3" :key="'top-' + copy">
                        <!-- Google Docs -->
                        <div class="logo-tile" style="background-color: #E8F0FE">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M14 2H6C4.9 2 4 2.9 4 4v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM8 13h8v1.5H8V13zm0 3h8v1.5H8V16zm0-6h3v1.5H8V10z" fill="#4285F4"/></svg>
                        </div>
                        <!-- Google Sheets -->
                        <div class="logo-tile" style="background-color: #E6F4EA">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M14 2H6C4.9 2 4 2.9 4 4v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13z" fill="#34A853"/><path d="M8 13h3v2H8v-2zm0 3h3v2H8v-2zm4-3h4v2h-4v-2zm0 3h4v2h-4v-2zm-5-1h10v.5H7v-.5z" fill="#34A853"/></svg>
                        </div>
                        <!-- Google Calendar -->
                        <div class="logo-tile" style="background-color: #FEF7E0">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M19 4h-1V2h-2v2H8V2H6v2H5C3.9 4 3 4.9 3 6v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z" fill="#F4B400"/><rect x="7" y="12" width="3" height="3" rx="0.5" fill="#F4B400"/><rect x="7" y="16" width="3" height="3" rx="0.5" fill="#F4B400" opacity="0.5"/><rect x="11" y="12" width="3" height="3" rx="0.5" fill="#F4B400" opacity="0.7"/></svg>
                        </div>
                        <!-- Slack -->
                        <div class="logo-tile" style="background-color: #F3E8FF">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zm1.271 0a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313z" fill="#E01E5A"/><path d="M8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zm0 1.271a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312z" fill="#36C5F0"/><path d="M18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zm-1.271 0a2.527 2.527 0 0 1-2.521 2.521 2.527 2.527 0 0 1-2.521-2.521V2.522A2.527 2.527 0 0 1 15.164 0a2.528 2.528 0 0 1 2.521 2.522v6.312z" fill="#2EB67D"/><path d="M15.164 18.956a2.528 2.528 0 0 1 2.521 2.522A2.528 2.528 0 0 1 15.164 24a2.527 2.527 0 0 1-2.521-2.522v-2.522h2.521zm0-1.271a2.527 2.527 0 0 1-2.521-2.521 2.528 2.528 0 0 1 2.521-2.522h6.314A2.528 2.528 0 0 1 24 15.164a2.528 2.528 0 0 1-2.522 2.521h-6.314z" fill="#ECB22E"/></svg>
                        </div>
                        <!-- Zoom -->
                        <div class="logo-tile" style="background-color: #E3F2FD">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M4 6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2v-3l4 3V8l-4 3V8c0-1.1-.9-2-2-2H4z" fill="#2D8CFF"/></svg>
                        </div>
                        <!-- Notion -->
                        <div class="logo-tile" style="background-color: #F5F5F5">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M4 4.5A1.5 1.5 0 015.5 3H14l6 6v9.5a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 18.5v-14z" fill="none" stroke="#111827" stroke-width="1.5"/><path d="M14 3v6h6" fill="none" stroke="#111827" stroke-width="1.5" stroke-linecap="round"/><path d="M8 13l2 2 4-4" fill="none" stroke="#111827" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <!-- Trello -->
                        <div class="logo-tile" style="background-color: #E3F2FD">
                            <svg viewBox="0 0 24 24" class="logo-icon"><rect x="3" y="3" width="18" height="18" rx="3" fill="#0079BF"/><rect x="5.5" y="5.5" width="5" height="11" rx="1.2" fill="white"/><rect x="13.5" y="5.5" width="5" height="7" rx="1.2" fill="white"/></svg>
                        </div>
                        <!-- Mailchimp -->
                        <div class="logo-tile" style="background-color: #FFF9E6">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z" fill="#FFE01B"/><circle cx="12" cy="12" r="3" fill="#111827" opacity="0.2"/></svg>
                        </div>
                        <!-- Jira -->
                        <div class="logo-tile" style="background-color: #E8F0FE">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M12.005 2L6.5 7.505l5.505 5.505L17.51 7.505 12.005 2zm-5.5 5.505L1 13.01l5.505 5.505 5.505-5.505-5.505-5.505zm11.01 0l-5.505 5.505 5.505 5.505L23.02 13.01l-5.505-5.505z" fill="#2684FF"/></svg>
                        </div>
                        <!-- HubSpot -->
                        <div class="logo-tile" style="background-color: #FFF0EC">
                            <svg viewBox="0 0 24 24" class="logo-icon"><circle cx="12" cy="10" r="3" fill="none" stroke="#FF7A59" stroke-width="1.8"/><circle cx="18" cy="7" r="1.5" fill="#FF7A59"/><circle cx="18" cy="17" r="1.5" fill="#FF7A59"/><circle cx="6" cy="17" r="1.5" fill="#FF7A59"/><path d="M14.6 11.6l2.4 4.4M9.4 11.6L7 16.4M12 13v4.5" stroke="#FF7A59" stroke-width="1.2" stroke-linecap="round"/><circle cx="12" cy="19" r="1.5" fill="#FF7A59"/></svg>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Row 2: Right to Left -->
            <div class="marquee-wrap">
                <div class="marquee-track marquee-rtl">
                    <template v-for="copy in 3" :key="'bot-' + copy">
                        <!-- Google Forms -->
                        <div class="logo-tile" style="background-color: #F3E8FF">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M14 2H6C4.9 2 4 2.9 4 4v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13z" fill="#7C3AED"/><circle cx="9" cy="13" r="1.2" fill="#7C3AED"/><rect x="12" y="12.2" width="5" height="1.6" rx="0.8" fill="#7C3AED" opacity="0.4"/><circle cx="9" cy="17" r="1.2" fill="#7C3AED"/><rect x="12" y="16.2" width="5" height="1.6" rx="0.8" fill="#7C3AED" opacity="0.4"/></svg>
                        </div>
                        <!-- Discord -->
                        <div class="logo-tile" style="background-color: #EDE9FE">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.095 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.095 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z" fill="#5865F2"/></svg>
                        </div>
                        <!-- Zapier -->
                        <div class="logo-tile" style="background-color: #FFF3E0">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M12 2l3 5h-2v3h3v-2l5 3-5 3v-2h-3v3h2l-3 5-3-5h2v-3H7v2l-5-3 5-3v2h3V7H8l4-5z" fill="#FF4A00"/></svg>
                        </div>
                        <!-- Airtable -->
                        <div class="logo-tile" style="background-color: #E3F2FD">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M12 3L3 7v2l9 4 9-4V7l-9-4z" fill="#18BFFF"/><path d="M3 9v2l9 4 9-4V9l-9 4-9-4z" fill="#FCB400"/><path d="M3 13v2l9 4 9-4v-2l-9 4-9-4z" fill="#F82B60"/></svg>
                        </div>
                        <!-- Asana -->
                        <div class="logo-tile" style="background-color: #FFEBEE">
                            <svg viewBox="0 0 24 24" class="logo-icon"><circle cx="12" cy="7" r="4" fill="#F06A6A"/><circle cx="6" cy="16" r="4" fill="#F06A6A"/><circle cx="18" cy="16" r="4" fill="#F06A6A"/></svg>
                        </div>
                        <!-- Figma -->
                        <div class="logo-tile" style="background-color: #FCE4EC">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M8 24c2.208 0 4-1.792 4-4v-4H8c-2.208 0-4 1.792-4 4s1.792 4 4 4z" fill="#0ACF83" transform="scale(0.75) translate(4,4)"/><path d="M4 12c0-2.208 1.792-4 4-4h4v8H8c-2.208 0-4-1.792-4-4z" fill="#A259FF" transform="scale(0.75) translate(4,4)"/><path d="M4 4c0-2.208 1.792-4 4-4h4v8H8C5.792 8 4 6.208 4 4z" fill="#F24E1E" transform="scale(0.75) translate(4,4)"/><path d="M12 0h4c2.208 0 4 1.792 4 4s-1.792 4-4 4h-4V0z" fill="#FF7262" transform="scale(0.75) translate(4,4)"/><path d="M20 12c0 2.208-1.792 4-4 4s-4-1.792-4-4 1.792-4 4-4 4 1.792 4 4z" fill="#1ABCFE" transform="scale(0.75) translate(4,4)"/></svg>
                        </div>
                        <!-- Stripe -->
                        <div class="logo-tile" style="background-color: #EDE7F6">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.918 3.757 7.074c0 4.46 2.726 5.943 5.772 7.125 2.441.948 3.217 1.622 3.217 2.644 0 1.018-.839 1.618-2.345 1.618-2.014 0-4.945-.876-7.02-2.118L2.5 21.94C4.343 23.09 7.614 24 10.557 24c2.627 0 4.81-.632 6.35-1.816 1.678-1.29 2.537-3.195 2.537-5.49.005-4.553-2.756-5.984-5.468-7.544z" fill="#635BFF" transform="scale(0.7) translate(5, 5)"/></svg>
                        </div>
                        <!-- GitHub -->
                        <div class="logo-tile" style="background-color: #F5F5F5">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z" fill="#111827"/></svg>
                        </div>
                        <!-- Dropbox -->
                        <div class="logo-tile" style="background-color: #E3F2FD">
                            <svg viewBox="0 0 24 24" class="logo-icon"><path d="M7.004 2L1 6.2l6.004 4.2L13 6.2 7.004 2zm9.996 0L11 6.2l6 4.2 6.004-4.2L17 2zM1 14.6l6.004 4.2L13 14.6 7.004 10.4 1 14.6zm16-4.2L11 14.6l6 4.2 6.004-4.2L17 10.4zM7 20.2l6 4.2 6-4.2-6-4.2-6 4.2z" fill="#0061FF"/></svg>
                        </div>
                        <!-- Microsoft Teams -->
                        <div class="logo-tile" style="background-color: #EDE9FE">
                            <svg viewBox="0 0 24 24" class="logo-icon"><rect x="3" y="5" width="14" height="14" rx="2" fill="#6264A7"/><text x="7" y="15.5" font-size="8" fill="white" font-weight="800" font-family="system-ui">T</text><circle cx="19" cy="8" r="3" fill="#6264A7" opacity="0.6"/><rect x="17" y="12" width="5" height="6" rx="1.5" fill="#6264A7" opacity="0.4"/></svg>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.marquee-wrap {
    position: relative;
    width: 100%;
    overflow: hidden;
    mask-image: linear-gradient(to right, transparent 0%, black 10%, black 90%, transparent 100%);
    -webkit-mask-image: linear-gradient(to right, transparent 0%, black 10%, black 90%, transparent 100%);
}

.marquee-track {
    display: flex;
    gap: 16px;
    width: max-content;
    will-change: transform;
}

.logo-tile {
    flex-shrink: 0;
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(0, 0, 0, 0.04);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: default;
}

.logo-tile:hover {
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.logo-icon {
    width: 32px;
    height: 32px;
}

.marquee-ltr {
    animation: scrollLeft 40s linear infinite;
}

.marquee-rtl {
    animation: scrollRight 38s linear infinite;
}

@keyframes scrollLeft {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(calc(-100% / 3));
    }
}

@keyframes scrollRight {
    0% {
        transform: translateX(calc(-100% / 3));
    }
    100% {
        transform: translateX(0);
    }
}

.marquee-wrap:hover .marquee-track {
    animation-play-state: paused;
}
</style>
