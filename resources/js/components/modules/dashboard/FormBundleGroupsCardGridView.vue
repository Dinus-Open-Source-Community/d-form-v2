<script setup lang="ts">
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ChevronDown } from 'lucide-vue-next';
import {
    groupReviewStatusBadge,
    memberConfirmationStatusBadge,
    registrationRoleLabel,
    submissionReviewBadge,
} from '@/lib/formSubmissionsUi';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import { cn } from '@/lib/utils';

defineProps<{
    bundleGroups: IBundleSubmissionGroup[];
    formatDate: (v: string) => string;
}>();

const emit = defineEmits<{
    openGroup: [group: IBundleSubmissionGroup];
    openMemberDetail: [member: IBundleSubmissionMember];
}>();

const expandedGroups = ref<Set<string>>(new Set());

function toggleExpand(token: string) {
    const next = new Set(expandedGroups.value);
    if (next.has(token)) {
        next.delete(token);
    } else {
        next.add(token);
    }
    expandedGroups.value = next;
}

function isExpanded(token: string) {
    return expandedGroups.value.has(token);
}

function participantEmail(member: IBundleSubmissionMember) {
    return member.user?.email ?? member.invited_email ?? '—';
}

function participantName(member: IBundleSubmissionMember) {
    return member.user?.name ?? member.invited_email ?? 'Tanpa nama';
}

function allParticipants(group: IBundleSubmissionGroup): IBundleSubmissionMember[] {
    const list: IBundleSubmissionMember[] = [];
    if (group.leader) list.push(group.leader);
    list.push(...(group.members ?? []));
    return list;
}

function onMemberClick(member: IBundleSubmissionMember) {
    if (!member.can_open_detail) return;
    emit('openMemberDetail', member);
}

function onMemberKeydown(e: KeyboardEvent, member: IBundleSubmissionMember) {
    if (member.can_open_detail && (e.key === 'Enter' || e.key === ' ')) {
        e.preventDefault();
        emit('openMemberDetail', member);
    }
}

function isLeader(member: IBundleSubmissionMember) {
    return member.registration_role === 'leader';
}
</script>

<template>
    <div class="grid grid-cols-1 items-start gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <article
            v-for="(group, idx) in bundleGroups"
            :key="group.group_token"
            class="animate-app-fade-in border-border/80 bg-card hover:border-border flex h-auto flex-col self-start overflow-hidden rounded-2xl border shadow-sm transition-all duration-300 hover:shadow-md"
            :class="isExpanded(group.group_token) ? 'border-primary/30 ring-primary/15 ring-1' : ''"
            :style="{ animationDelay: `${idx * 70}ms` }"
        >
            <section
                class="hover:bg-muted/[0.06] focus-visible:ring-ring flex w-full flex-col p-5 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <p class="text-muted-foreground text-[0.6875rem] font-medium tracking-wide uppercase">
                            Grup bundle
                        </p>
                        <h4 class="text-foreground truncate text-base font-semibold">
                            GRUP : {{ group.leader.user?.name as string }}
                        </h4>
                        <p class="text-muted-foreground text-xs tabular-nums">
                            {{ group.total_participants }} peserta · {{ formatDate(group.submitted_at) }}
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        class="shrink-0 rounded-full px-2.5 py-0.5 text-[0.6875rem] font-medium"
                        :class="groupReviewStatusBadge(group.group_review_status).class"
                    >
                        {{ groupReviewStatusBadge(group.group_review_status).label }}
                    </Badge>
                </div>

                <div
                    v-if="group.accepted_count > 0 || group.pending_count > 0 || group.rejected_count > 0"
                    class="mt-4 flex flex-wrap gap-1.5"
                >
                    <span
                        v-if="group.accepted_count > 0"
                        class="bg-success/10 text-success rounded-md px-2 py-0.5 text-[0.6875rem] font-medium"
                    >
                        {{ group.accepted_count }} diterima
                    </span>
                    <span
                        v-if="group.pending_count > 0"
                        class="bg-warning/10 text-warning rounded-md px-2 py-0.5 text-[0.6875rem] font-medium"
                    >
                        {{ group.pending_count }} menunggu
                    </span>
                    <span
                        v-if="group.rejected_count > 0"
                        class="bg-destructive/10 text-destructive rounded-md px-2 py-0.5 text-[0.6875rem] font-medium"
                    >
                        {{ group.rejected_count }} ditolak
                    </span>
                </div>
            </section>

            <div class="border-border/60 border-t px-4 py-3">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-foreground h-9 w-full justify-between rounded-xl px-3 text-sm font-medium"
                    :aria-expanded="isExpanded(group.group_token)"
                    @click.stop="toggleExpand(group.group_token)"
                >
                    <span>Anggota ({{ allParticipants(group).length }})</span>
                    <ChevronDown
                        class="text-muted-foreground size-4 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
                        :class="isExpanded(group.group_token) ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </Button>
            </div>

            <div
                class="grid h-auto transition-[grid-template-rows] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
                :class="isExpanded(group.group_token) ? 'grid-rows-[1fr] h-auto' : 'grid-rows-[0fr] h-auto'"
            >
                <div class="overflow-hidden">
                    <div class="border-border/60 bg-muted/[0.05] space-y-2 border-t px-4 py-4">
                        <p
                            v-if="allParticipants(group).length === 0"
                            class="text-muted-foreground py-2 text-center text-sm"
                        >
                            Belum ada anggota.
                        </p>

                        <div
                            v-for="member in allParticipants(group)"
                            :key="member.id"
                            :role="member.can_open_detail ? 'button' : undefined"
                            :tabindex="member.can_open_detail ? 0 : undefined"
                            :class="
                                cn(
                                    'border-border/60 bg-card rounded-xl border px-3.5 py-3 transition-colors duration-200',
                                    member.can_open_detail
                                        ? 'hover:border-primary/30 hover:bg-muted/20 focus-visible:ring-ring cursor-pointer focus-visible:ring-2 focus-visible:outline-none'
                                        : 'cursor-default opacity-65'
                                )
                            "
                            @click="onMemberClick(member)"
                            @keydown="onMemberKeydown($event, member)"
                        >
                            <div class="flex items-start gap-3">
                                <UserAvatarFallback
                                    :src="member.user?.avatar ?? null"
                                    :seed="userAvatarSeed(member.user)"
                                    avatar-class="size-9 shrink-0 rounded-xl border border-border/70"
                                    fallback-round-class="rounded-xl"
                                />

                                <div class="min-w-0 flex-1 space-y-2">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <p
                                            class="truncate text-sm font-semibold"
                                            :class="
                                                member.can_open_detail ? 'text-foreground' : 'text-muted-foreground'
                                            "
                                        >
                                            {{ participantName(member) }}
                                        </p>
                                        <span class="text-muted-foreground text-[0.6875rem] font-medium">
                                            {{ registrationRoleLabel(member.registration_role) }}
                                        </span>
                                    </div>

                                    <p class="text-muted-foreground truncate text-xs">
                                        {{ participantEmail(member) }}
                                    </p>

                                    <div class="flex flex-wrap gap-1.5">
                                        <Badge
                                            variant="outline"
                                            class="h-5 rounded-full px-2 text-[0.625rem] font-medium"
                                            :class="submissionReviewBadge(member.review_status).class"
                                        >
                                            {{ submissionReviewBadge(member.review_status).label }}
                                        </Badge>
                                        <Badge
                                            v-if="!isLeader(member)"
                                            variant="outline"
                                            class="h-5 rounded-full px-2 text-[0.625rem] font-medium"
                                            :class="
                                                memberConfirmationStatusBadge(member.member_confirmation_status).class
                                            "
                                        >
                                            {{ memberConfirmationStatusBadge(member.member_confirmation_status).label }}
                                        </Badge>
                                    </div>

                                    <dl class="text-muted-foreground grid grid-cols-1 gap-1 text-[0.6875rem]">
                                        <div class="flex justify-between gap-3">
                                            <dt>Dikirim</dt>
                                            <dd class="text-foreground/85 font-medium tabular-nums">
                                                {{ formatDate(member.submitted_at) }}
                                            </dd>
                                        </div>
                                        <div v-if="member.reviewed_at" class="flex justify-between gap-3">
                                            <dt>Direview</dt>
                                            <dd class="text-foreground/85 font-medium tabular-nums">
                                                {{ formatDate(member.reviewed_at) }}
                                            </dd>
                                        </div>
                                        <div v-if="member.reviewer" class="flex justify-between gap-3">
                                            <dt>Peninjau</dt>
                                            <dd class="text-foreground/85 truncate font-medium">
                                                {{ member.reviewer.name }}
                                            </dd>
                                        </div>
                                        <div class="text-primary flex justify-between gap-3">
                                            <dt>Klik untuk melihat detail</dt>
                                        </div>
                                    </dl>

                                    <p
                                        v-if="member.locked_reason && !member.can_open_detail"
                                        class="text-muted-foreground text-[0.6875rem] leading-relaxed"
                                    >
                                        {{ member.locked_reason }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
</template>
