<script setup lang="ts">
import { computed } from 'vue';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import {
    groupReviewStatusBadge,
    memberConfirmationStatusBadge,
    registrationRoleLabel,
    submissionReviewBadge,
} from '@/lib/formSubmissionsUi';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import { formatDateTime } from '@/lib/dateTime';
import { cn } from '@/lib/utils';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';

const props = defineProps<{
    group: IBundleSubmissionGroup | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    openDetail: [submission: IBundleSubmissionMember];
}>();

function groupLabel(token: string | undefined) {
    if (!token) return '—';
    const t = token.trim();
    if (t.length <= 10) return t;
    return t.slice(-10).toUpperCase();
}

const participants = computed(() => {
    if (!props.group) return [];
    const list: IBundleSubmissionMember[] = [];
    if (props.group.leader) list.push(props.group.leader);
    list.push(...(props.group.members ?? []));
    return list;
});

function participantEmail(member: IBundleSubmissionMember) {
    return member.user?.email ?? member.invited_email ?? '—';
}

function participantName(member: IBundleSubmissionMember) {
    return member.display_name ?? member.user?.name ?? member.invited_email ?? 'Tanpa nama';
}

function openParticipantDetail(member: IBundleSubmissionMember) {
    if ('can_open_detail' in member && !member.can_open_detail) return;
    open.value = false;
    emit('openDetail', member);
}

function onParticipantKeydown(e: KeyboardEvent, member: IBundleSubmissionMember) {
    if ('can_open_detail' in member && !member.can_open_detail) return;
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openParticipantDetail(member);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :class="
                cn(
                    '!flex flex-col gap-0 overflow-hidden p-0 sm:max-w-lg',
                    'max-md:top-0 max-md:right-0 max-md:bottom-0 max-md:left-0 max-md:h-[100dvh] max-md:max-h-[100dvh] max-md:w-full max-md:max-w-none max-md:translate-x-0 max-md:translate-y-0 max-md:rounded-none max-md:border-0',
                    'md:top-[50%] md:left-[50%] md:h-auto md:max-h-[min(90vh,40rem)] md:w-full md:translate-x-[-50%] md:translate-y-[-50%] md:rounded-xl md:border'
                )
            "
        >
            <div class="border-border/80 shrink-0 border-b px-5 pt-6 pr-14 pb-5 md:px-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <p class="text-muted-foreground text-[0.6875rem] font-medium tracking-wide uppercase">
                            Grup bundle
                        </p>
                        <h2 class="text-foreground font-mono text-xl font-semibold tracking-tight">
                            #{{ groupLabel(group?.group_token) }}
                        </h2>
                        <p class="text-muted-foreground text-sm tabular-nums">
                            {{ group?.total_participants ?? 0 }} peserta ·
                            {{
                                group ? formatDateTime({ value: group.submitted_at, variant: 'date-time-medium' }) : '—'
                            }}
                        </p>
                    </div>
                    <Badge
                        v-if="group"
                        variant="outline"
                        class="shrink-0 px-2.5 py-0.5 text-[0.6875rem] font-medium"
                        :class="groupReviewStatusBadge(group.group_review_status).class"
                    >
                        {{ groupReviewStatusBadge(group.group_review_status).label }}
                    </Badge>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 md:px-6">
                <p v-if="participants.length === 0" class="text-muted-foreground py-8 text-center text-sm">
                    Belum ada peserta.
                </p>

                <div v-else class="space-y-2">
                    <button
                        v-for="member in participants"
                        :key="member.id"
                        type="button"
                        :disabled="'can_open_detail' in member ? !member.can_open_detail : false"
                        :class="
                            cn(
                                'border-border/70 bg-card flex w-full items-start gap-3 border px-4 py-3.5 text-left transition-colors',
                                !('can_open_detail' in member) || member.can_open_detail
                                    ? 'hover:border-primary/30 hover:bg-muted/20 focus-visible:ring-ring cursor-pointer focus-visible:ring-2 focus-visible:outline-none'
                                    : 'cursor-default opacity-60'
                            )
                        "
                        @click="openParticipantDetail(member)"
                        @keydown="onParticipantKeydown($event, member)"
                    >
                        <UserAvatarFallback
                            :src="member.user?.avatar ?? null"
                            :seed="userAvatarSeed(member.user)"
                            avatar-class="size-10 shrink-0 rounded-xl border border-border/80"
                            fallback-round-class="rounded-xl"
                        />

                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-foreground truncate text-sm font-semibold">
                                    {{ participantName(member) }}
                                </span>
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
                                    class="h-5 px-2 text-[0.625rem] font-medium"
                                    :class="submissionReviewBadge(member.review_status).class"
                                >
                                    {{ submissionReviewBadge(member.review_status).label }}
                                </Badge>
                                <Badge
                                    v-if="member.registration_role !== 'leader'"
                                    variant="outline"
                                    class="h-5 px-2 text-[0.625rem] font-medium"
                                    :class="memberConfirmationStatusBadge(member.member_confirmation_status).class"
                                >
                                    {{ memberConfirmationStatusBadge(member.member_confirmation_status).label }}
                                </Badge>
                            </div>
                            <p
                                v-if="member.locked_reason && 'can_open_detail' in member && !member.can_open_detail"
                                class="text-muted-foreground text-[0.6875rem]"
                            >
                                {{ member.locked_reason }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
