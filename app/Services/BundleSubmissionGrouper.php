<?php

namespace App\Services;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\MemberConfirmationStatus;
use App\Enums\RegistrationRole;
use App\Models\FormAnswer;
use Illuminate\Support\Collection;

final class BundleSubmissionGrouper
{
    /**
     * Group form answers by group_token and prepare bundle group data.
     *
     * @param  Collection<int, FormAnswer>  $formAnswers
     * @return Collection<int, array<string, mixed>>
     */
    public function groupSubmissions(Collection $formAnswers): Collection
    {
        $grouped = $formAnswers->groupBy('group_token');

        return $grouped->map(function (Collection $groupSubmissions, string $groupToken): ?array {
            // Identify leader (registration_role = leader or fallback to first)
            $leader = $groupSubmissions->first(fn (FormAnswer $fa) => $fa->registration_role === RegistrationRole::Leader);
            if ($leader === null) {
                $leader = $groupSubmissions->first();
            }

            if ($leader === null) {
                return null;
            }

            // Separate members from leader
            $members = $groupSubmissions->filter(fn (FormAnswer $fa) => $fa->id !== $leader->id);

            // Calculate counts by member confirmation status
            $acceptedCount = $groupSubmissions->filter(
                fn (FormAnswer $fa) => $fa->registration_role === RegistrationRole::Leader
                    || $fa->member_confirmation_status === MemberConfirmationStatus::Accepted
            )->count();

            $pendingCount = $members->filter(
                fn (FormAnswer $fa) => $fa->member_confirmation_status === MemberConfirmationStatus::Pending
            )->count();

            $rejectedCount = $members->filter(
                fn (FormAnswer $fa) => $fa->member_confirmation_status === MemberConfirmationStatus::Rejected
            )->count();

            $expiredCount = $members->filter(
                fn (FormAnswer $fa) => $fa->member_confirmation_status === MemberConfirmationStatus::Expired
            )->count();

            // Calculate group review status
            $groupReviewStatus = $this->calculateGroupReviewStatus($groupSubmissions);

            // Transform all participants with permissions
            $leaderData = $this->transformSubmission($leader, true);
            $membersData = $members->map(fn (FormAnswer $fa) => $this->transformSubmission($fa, false))->values();

            return [
                'group_token' => $groupToken,
                'leader' => $leaderData,
                'members' => $membersData->all(),
                'total_participants' => $groupSubmissions->count(),
                'accepted_count' => $acceptedCount,
                'pending_count' => $pendingCount,
                'rejected_count' => $rejectedCount,
                'expired_count' => $expiredCount,
                'group_review_status' => $groupReviewStatus,
                'submitted_at' => $leader->created_at->toISOString(),
            ];
        })->filter()->values();
    }

    /**
     * Transform a single FormAnswer into the expected array structure with permissions.
     *
     * @return array<string, mixed>
     */
    private function transformSubmission(FormAnswer $answer, bool $isLeader): array
    {
        $canOpenDetail = $this->canOpenDetail($answer, $isLeader);
        $canReview = $this->canReview($answer, $isLeader);
        $lockedReason = $this->getLockedReason($answer, $isLeader);

        return [
            'id' => $answer->id,
            'user' => $answer->user
                ? [
                    'id' => $answer->user->id,
                    'name' => $answer->user->name,
                    'email' => $answer->user->email,
                    'avatar' => $answer->user->avatar ?? null,
                ]
                : null,
            'invited_email' => $answer->invited_email,
            'answers' => $answer->answers,
            'submitted_at' => $answer->created_at->toISOString(),
            'review_status' => $answer->review_status?->value,
            'reviewed_at' => $answer->reviewed_at?->toIso8601String(),
            'reviewed_by' => $answer->reviewed_by,
            'registration_role' => $answer->registration_role?->value,
            'member_confirmation_status' => $answer->member_confirmation_status?->value,
            'group_token' => $answer->group_token,
            'reviewer' => $answer->reviewer
                ? [
                    'id' => $answer->reviewer->id,
                    'name' => $answer->reviewer->name,
                    'email' => $answer->reviewer->email,
                ]
                : null,
            'can_open_detail' => $canOpenDetail,
            'can_review' => $canReview,
            'locked_reason' => $lockedReason,
        ];
    }

    /**
     * Determine if admin can open detail for this submission.
     */
    private function canOpenDetail(FormAnswer $answer, bool $isLeader): bool
    {
        // Leader can always be opened
        if ($isLeader || $answer->registration_role === RegistrationRole::Leader) {
            return true;
        }

        // Member can only be opened if accepted
        if ($answer->registration_role === RegistrationRole::Member) {
            return $answer->member_confirmation_status === MemberConfirmationStatus::Accepted;
        }

        return true;
    }

    /**
     * Determine if admin can review this submission.
     */
    private function canReview(FormAnswer $answer, bool $isLeader): bool
    {
        // Must be pending review status first
        if ($answer->review_status !== FormAnswerReviewStatus::Pending && $answer->review_status !== null) {
            return false;
        }

        // Leader can always be reviewed (if pending)
        if ($isLeader || $answer->registration_role === RegistrationRole::Leader) {
            return true;
        }

        // Member can only be reviewed if accepted
        if ($answer->registration_role === RegistrationRole::Member) {
            return $answer->member_confirmation_status === MemberConfirmationStatus::Accepted;
        }

        return true;
    }

    /**
     * Get locked reason text if submission is locked.
     */
    private function getLockedReason(FormAnswer $answer, bool $isLeader): ?string
    {
        if ($isLeader || $answer->registration_role === RegistrationRole::Leader) {
            return null;
        }

        if ($answer->registration_role === RegistrationRole::Member) {
            return match ($answer->member_confirmation_status) {
                MemberConfirmationStatus::Pending => 'Menunggu anggota menerima undangan.',
                MemberConfirmationStatus::Rejected => 'Anggota menolak undangan.',
                MemberConfirmationStatus::Expired => 'Undangan anggota sudah kedaluwarsa.',
                MemberConfirmationStatus::Accepted => null,
                default => null,
            };
        }

        return null;
    }

    /**
     * Calculate the overall group review status.
     */
    private function calculateGroupReviewStatus(Collection $groupSubmissions): string
    {
        // Only consider reviewable submissions (leader + accepted members)
        $reviewableSubmissions = $groupSubmissions->filter(function (FormAnswer $fa) {
            return $fa->registration_role === RegistrationRole::Leader
                || ($fa->registration_role === RegistrationRole::Member
                    && $fa->member_confirmation_status === MemberConfirmationStatus::Accepted);
        });

        if ($reviewableSubmissions->isEmpty()) {
            return 'pending';
        }

        $acceptedCount = $reviewableSubmissions->filter(
            fn (FormAnswer $fa) => $fa->review_status === FormAnswerReviewStatus::Accepted
        )->count();

        $rejectedCount = $reviewableSubmissions->filter(
            fn (FormAnswer $fa) => $fa->review_status === FormAnswerReviewStatus::Rejected
        )->count();

        $pendingCount = $reviewableSubmissions->filter(
            fn (FormAnswer $fa) => $fa->review_status === FormAnswerReviewStatus::Pending || $fa->review_status === null
        )->count();

        // All rejected
        if ($rejectedCount === $reviewableSubmissions->count()) {
            return 'rejected';
        }

        // All accepted
        if ($acceptedCount === $reviewableSubmissions->count()) {
            return 'accepted';
        }

        // Some reviewed, some not
        if ($acceptedCount > 0 || $rejectedCount > 0) {
            return 'partial';
        }

        // All pending
        return 'pending';
    }
}
