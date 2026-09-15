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
    Settings2,
    ClipboardCheck,
    ScanLine,
} from 'lucide-vue-next';
import { isSidebarNavActive, routes } from '@/lib/routes';
import useAuth from '@/utils/composables/useAuth';

const page = usePage();
const user = useAuth(page.props);
const { isMobile, setOpenMobile } = useSidebar();

const canManageEvents = computed(() => user.value?.can_manage_events === true);
const canAccessRecruitment = computed(() => user.value?.can_access_recruitment === true);
const canManageRecruitmentPeriods = computed(() => user.value?.can_manage_recruitment_periods === true);
const canListRecruitmentApplications = computed(() => user.value?.can_list_recruitment_applications === true);
const canScheduleRecruitmentInterviews = computed(() => user.value?.can_schedule_recruitment_interviews === true);
const canViewMyRecruitmentInterviews = computed(() => user.value?.can_view_my_recruitment_interviews === true);
const canViewRecruitmentReports = computed(() => user.value?.can_view_recruitment_reports === true);
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
    } else if (canAccessRecruitment.value) {
        items.push({ label: 'Rekrutmen', href: routes.admin.recruitment.index, icon: Users });
    }

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

const interviewerNavItems = computed(() => {
    if (!isInterviewerOnly.value || !canViewMyRecruitmentInterviews.value) return [];

    return [{ label: 'Pusat interview', href: routes.admin.recruitment.myInterviews.index }];
});

const recruitmentOpsItems = computed(() => {
    if (!canAccessRecruitment.value || isInterviewerOnly.value) return [];

    const items = [{ label: 'Pusat kerja', href: routes.admin.recruitment.index }];

    if (canListRecruitmentApplications.value) {
        items.push({ label: 'Applicant', href: routes.admin.recruitment.applications.index });
    }

    if (canScheduleRecruitmentInterviews.value) {
        items.push({ label: 'Interview', href: routes.admin.recruitment.interviewSessions.index });
    }

    if (canViewRecruitmentReports.value) {
        items.push({ label: 'Laporan', href: routes.admin.recruitment.reports.index });
    }

    if (
        canViewMyRecruitmentInterviews.value &&
        (canListRecruitmentApplications.value || canScheduleRecruitmentInterviews.value)
    ) {
        items.push({ label: 'Interview Saya', href: routes.admin.recruitment.myInterviews.index });
    }

    return items;
});

const recruitmentSettingsItems = computed(() => {
    if (!canAccessRecruitment.value || isInterviewerOnly.value) return [];

    const items: { label: string; href: string }[] = [];

    if (canManageRecruitmentPeriods.value) {
        items.push(
            { label: 'Periode', href: routes.admin.recruitment.periods.index },
            { label: 'Divisi', href: routes.admin.recruitment.divisions.index },
        );
    }

    if (canViewRecruitmentActivity.value) {
        items.push({ label: 'Activity Log', href: routes.admin.recruitment.activityLogs.index });
    }

    return items;
});

const showInterviewerSection = computed(() => interviewerNavItems.value.length > 0);
const showRecruitmentSection = computed(
    () => recruitmentOpsItems.value.length > 0 || recruitmentSettingsItems.value.length > 0,
);
const showRecruitmentSettings = computed(() => recruitmentSettingsItems.value.length > 0);

const recruitmentSettingsOpen = ref(false);

function isActive(href: string): boolean {
    return isSidebarNavActive(href, currentPath.value);
}

function isRecruitmentSettingsActive(): boolean {
    return recruitmentSettingsItems.value.some((item) => isActive(item.href));
}

function toggleRecruitmentSettings() {
    recruitmentSettingsOpen.value = !recruitmentSettingsOpen.value;
}

watch(
    currentPath,
    () => {
        if (isRecruitmentSettingsActive()) {
            recruitmentSettingsOpen.value = true;
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
                    <SidebarMenu class="gap-0.5">
                        <SidebarMenuItem v-for="item in mainNavItems" :key="item.href">
                            <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                <Link :href="item.href" class="gap-3 rounded-lg" @click="closeMobileIfNeeded">
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
                    <SidebarMenu class="gap-0.5">
                        <SidebarMenuItem v-for="item in managementItems" :key="item.href">
                            <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                <Link :href="item.href" class="gap-3 rounded-lg" @click="closeMobileIfNeeded">
                                    <component :is="item.icon" class="size-4 shrink-0 opacity-90" />
                                    <span class="font-medium">{{ item.label }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <template v-if="showInterviewerSection">
                <SidebarSeparator class="bg-sidebar-border/60 my-3 opacity-80" />

                <SidebarGroup class="p-0">
                    <SidebarGroupLabel
                        class="text-sidebar-foreground/45 mb-2 px-2 text-[10px] font-semibold tracking-[0.14em] uppercase"
                    >
                        Interview OpRec
                    </SidebarGroupLabel>
                    <SidebarGroupContent class="space-y-0.5">
                        <SidebarMenu class="gap-0.5">
                            <SidebarMenuItem v-for="item in interviewerNavItems" :key="item.href">
                                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                    <Link :href="item.href" class="gap-3 rounded-lg" @click="closeMobileIfNeeded">
                                        <ClipboardCheck class="size-4 shrink-0 opacity-90" />
                                        <span class="font-medium">{{ item.label }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </template>

            <template v-if="showRecruitmentSection">
                <SidebarSeparator class="bg-sidebar-border/60 my-3 opacity-80" />

                <SidebarGroup class="p-0">
                    <SidebarGroupLabel
                        class="text-sidebar-foreground/45 mb-2 px-2 text-[10px] font-semibold tracking-[0.14em] uppercase"
                    >
                        OpRec
                    </SidebarGroupLabel>
                    <SidebarGroupContent class="space-y-0.5">
                        <SidebarMenu class="gap-0.5">
                            <SidebarMenuItem v-for="item in recruitmentOpsItems" :key="item.href">
                                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                    <Link :href="item.href" class="gap-3 rounded-lg" @click="closeMobileIfNeeded">
                                        <span class="font-medium">{{ item.label }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>

                            <SidebarMenuItem v-if="showRecruitmentSettings">
                                <SidebarMenuButton
                                    :is-active="isRecruitmentSettingsActive()"
                                    tooltip="Pengaturan OpRec"
                                    @click="toggleRecruitmentSettings"
                                >
                                    <Settings2 class="size-4 shrink-0 opacity-90" />
                                    <span class="font-medium">Pengaturan OpRec</span>
                                    <ChevronDown
                                        class="ml-auto size-4 shrink-0 opacity-70 transition-transform duration-200"
                                        :class="recruitmentSettingsOpen ? 'rotate-180' : ''"
                                    />
                                </SidebarMenuButton>
                                <SidebarMenuSub v-show="recruitmentSettingsOpen">
                                    <SidebarMenuSubItem
                                        v-for="item in recruitmentSettingsItems"
                                        :key="item.href"
                                    >
                                        <SidebarMenuSubButton as-child :is-active="isActive(item.href)">
                                            <Link :href="item.href" @click="closeMobileIfNeeded">
                                                {{ item.label }}
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </template>
        </SidebarContent>


        <SidebarRail />
    </Sidebar>
</template>
