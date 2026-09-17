<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarRail,
    SidebarSeparator,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    LayoutDashboard,
    CalendarDays,
    CalendarCheck2,
    Compass,
    Users,
    ChevronDown,
    ClipboardCheck,
    ScanLine,
    Briefcase,
    UserCheck,
    History,
} from 'lucide-vue-next';
import { isSidebarNavActive, routes } from '@/lib/routes';
import useAuth from '@/utils/composables/useAuth';

const page = usePage();
const user = useAuth(page.props);
const { isMobile, setOpenMobile } = useSidebar();

const canManageEvents = computed(() => user.value?.can_manage_events === true);
const canAccessRecruitment = computed(() => user.value?.can_access_recruitment === true);
const canListRecruitmentApplications = computed(() => user.value?.can_list_recruitment_applications === true);
const canScheduleRecruitmentInterviews = computed(() => user.value?.can_schedule_recruitment_interviews === true);
const canViewMyRecruitmentInterviews = computed(() => user.value?.can_view_my_recruitment_interviews === true);
const canViewRecruitmentActivity = computed(() => user.value?.can_view_recruitment_activity === true);
const isInterviewerOnly = computed(() => user.value?.is_recruitment_interviewer_only === true);
const canScanGlobal = computed(() => user.value?.can_manage_events === true || user.value?.can_scan_recruitment_attendance === true);

const currentPath = computed(() => page.url);

/** Beranda penyelenggara vs portal peserta — URL terpisah, sama-sama “Beranda” di UI. */
const mainNavItems = computed(() => [
    { label: 'Beranda', href: routes.dashboard.index, icon: LayoutDashboard },
]);

const managementItems = computed(() => {
    const items: { label: string; href: string; icon: typeof CalendarDays }[] = [];

    if (canScanGlobal.value) {
        items.push({ label: 'Scan Global', href: routes.admin.scan.index, icon: ScanLine });
    }

    if (canManageEvents.value) {
        items.push({ label: 'Acara', href: routes.admin.events.index, icon: CalendarDays });
    }

    if (canAccessRecruitment.value && isInterviewerOnly.value) {
        items.push({ label: 'Interview OpRec', href: routes.admin.recruitment.myInterviews.index, icon: ClipboardCheck });
    }
    // Rekrutmen untuk non-interviewer dirender sebagai parent collapsible
    // di bawah (showRecruitmentParent), bukan flat item di sini.

    if (!canManageEvents.value && !canAccessRecruitment.value) {
        items.push(
            { label: 'Acara diikuti', href: routes.member.joined, icon: CalendarCheck2 },
            { label: 'Jelajah acara', href: routes.member.browse, icon: Compass },
        );
    } else if (!canManageEvents.value && canAccessRecruitment.value) {
        items.push(
            { label: 'Acara diikuti', href: routes.member.joined, icon: CalendarCheck2 },
            { label: 'Jelajah acara', href: routes.member.browse, icon: Compass },
        );
    }

    return items;
});

const recruitmentOpsItems = computed(() => {
    if (!canAccessRecruitment.value || isInterviewerOnly.value) return [];

    const items: { label: string; href: string; icon: typeof CalendarDays }[] = [
        { label: 'Pusat kerja', href: routes.admin.recruitment.index, icon: Briefcase },
    ];

    if (
        canViewMyRecruitmentInterviews.value &&
        (canListRecruitmentApplications.value || canScheduleRecruitmentInterviews.value)
    ) {
        items.push({ label: 'Interview Saya', href: routes.admin.recruitment.myInterviews.index, icon: UserCheck });
    }

    return items;
});

const recruitmentSettingsItems = computed(() => {
    if (!canAccessRecruitment.value || isInterviewerOnly.value) return [];

    const items: { label: string; href: string; icon: typeof CalendarDays }[] = [];

    if (canViewRecruitmentActivity.value) {
        items.push({ label: 'Activity Log', href: routes.admin.recruitment.activityLogs.index, icon: History });
    }

    return items;
});

const showRecruitmentSettings = computed(() => recruitmentSettingsItems.value.length > 0);
/** FLATTEN: gabung ops + settings jadi satu level sublist di bawah parent Rekrutmen. */
const recruitmentSubItems = computed(() => [...recruitmentOpsItems.value, ...recruitmentSettingsItems.value]);
const showRecruitmentParent = computed(
    () => recruitmentOpsItems.value.length > 0 || showRecruitmentSettings.value,
);

const recruitmentOpen = ref(false);

function isActive(href: string): boolean {
    return isSidebarNavActive(href, currentPath.value);
}

function isRecruitmentActive(): boolean {
    return recruitmentSubItems.value.some((item) => isActive(item.href));
}

function toggleRecruitment() {
    recruitmentOpen.value = !recruitmentOpen.value;
}

watch(
    currentPath,
    () => {
        if (isRecruitmentActive()) {
            recruitmentOpen.value = true;
        }
    },
    { immediate: true },
);

function closeMobileIfNeeded() {
    if (isMobile.value) setOpenMobile(false);
}

/** URL logo publik — dibentuk saat runtime agar Vite tidak mem-bundel path file PNG. */
const sidebarLogoSrc = `/${encodeURIComponent('DForm 1.png')}`;
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-sidebar-border bg-sidebar overflow-x-hidden border-r">
        <SidebarHeader class="gap-0 overflow-hidden border-b border-sidebar-border/50 p-0">
            <Link
                :href="routes.dashboard.index"
                class="hover:bg-sidebar-accent/25 flex w-full min-w-0 items-center overflow-hidden px-4 py-3.5 transition-colors"
                @click="closeMobileIfNeeded"
            >
                <img
                    :src="sidebarLogoSrc"
                    alt="DForm"
                    class="h-auto max-h-9 w-full max-w-full object-contain object-center select-none group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:h-8 group-data-[collapsible=icon]:w-8 group-data-[collapsible=icon]:max-h-8 group-data-[collapsible=icon]:max-w-8 group-data-[collapsible=icon]:object-contain"
                    width="160"
                    height="40"
                />
            </Link>
        </SidebarHeader>

        <SidebarContent class="flex-1 overflow-x-hidden px-2.5 pb-3 pt-3">
            <SidebarGroup class="p-0">
                <SidebarGroupLabel
                    class="text-sidebar-foreground/45 mb-2 px-2 text-[10px] font-semibold tracking-[0.14em] uppercase"
                >
                    Menu utama
                </SidebarGroupLabel>
                <SidebarGroupContent class="space-y-0.5">
                    <SidebarMenu class="gap-1">
                        <SidebarMenuItem v-for="item in mainNavItems" :key="item.href">
                            <SidebarMenuButton
                                as-child
                                :is-active="isActive(item.href)"
                                :tooltip="item.label"
                                class="h-auto min-h-10 gap-2.5 rounded-lg px-2.5 py-2 text-sm"
                            >
                                <Link :href="item.href" @click="closeMobileIfNeeded">
                                    <component :is="item.icon" class="size-4 shrink-0 opacity-90" />
                                    <span class="font-medium">{{ item.label }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarSeparator class="bg-sidebar-border/60 my-3 opacity-80" />

            <SidebarGroup class="p-0">
                <SidebarGroupLabel
                    class="text-sidebar-foreground/45 mb-2 px-2 text-[10px] font-semibold tracking-[0.14em] uppercase"
                >
                    Kelola
                </SidebarGroupLabel>
                <SidebarGroupContent class="space-y-0.5">
                    <SidebarMenu class="gap-1">
                        <SidebarMenuItem v-for="item in managementItems" :key="item.href">
                            <SidebarMenuButton
                                as-child
                                :is-active="isActive(item.href)"
                                :tooltip="item.label"
                                class="h-auto min-h-10 gap-2.5 rounded-lg px-2.5 py-2 text-sm"
                            >
                                <Link :href="item.href" @click="closeMobileIfNeeded">
                                    <component :is="item.icon" class="size-4 shrink-0 opacity-90" />
                                    <span class="font-medium">{{ item.label }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        <SidebarMenuItem v-if="showRecruitmentParent">
                            <SidebarMenuButton
                                :is-active="isRecruitmentActive()"
                                tooltip="Rekrutmen"
                                class="h-auto min-h-10 gap-2.5 rounded-lg px-2.5 py-2 text-sm"
                                @click="toggleRecruitment"
                            >
                                <Users class="size-4 shrink-0 opacity-90" />
                                <span class="font-medium">Rekrutmen</span>
                                <ChevronDown
                                    class="ml-auto size-4 shrink-0 opacity-70 transition-transform duration-200"
                                    :class="recruitmentOpen ? 'rotate-180' : ''"
                                />
                            </SidebarMenuButton>
                            <SidebarMenuSub v-show="recruitmentOpen" class="mt-1 gap-1 py-1">
                                <SidebarMenuSubItem v-for="item in recruitmentSubItems" :key="item.href">
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isActive(item.href)"
                                        class="h-auto min-h-9 gap-2 rounded-md px-2 py-1.5 text-[13px] font-medium transition-colors"
                                        :class="
                                            isActive(item.href)
                                                ? 'bg-sidebar-accent text-sidebar-accent-foreground font-semibold shadow-xs'
                                                : undefined
                                        "
                                    >
                                        <Link :href="item.href" @click="closeMobileIfNeeded">
                                            <component :is="item.icon" class="size-3.5 shrink-0 opacity-80" />
                                            <span class="truncate">{{ item.label }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

        </SidebarContent>


        <SidebarRail />
    </Sidebar>
</template>
