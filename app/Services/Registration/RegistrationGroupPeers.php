<?php

namespace App\Services\Registration;

use App\Enums\FormAnswerReviewStatus;
use App\Enums\RegistrationRole;
use App\Models\Form;
use App\Models\FormAnswer;
use Illuminate\Support\Collection;

final class RegistrationGroupPeers
{
    /**
     *
     * All submissions in the same team/bundle group that are not yet admin-rejected.
     *
     * @return Collection<int, FormAnswer>
     *
     */
    public function peersNotYetRejected(Form $form, FormAnswer $trigger): Collection
    {
        $mode = $this->resolveRegistrationMode($form);

        if ($mode === 'bundle') {
            $groupToken = $trigger->group_token;
            if (! is_string($groupToken) || $groupToken === '') {
                return $this->wrapSinglePeer($trigger);
            }

            return FormAnswer::query()
                ->where('form_id', $form->id)
                ->where('group_token', $groupToken)
                ->where(function ($q): void {
                    $q->whereNull('review_status')
                        ->orWhere('review_status', '!=', FormAnswerReviewStatus::Rejected->value);
                })
                ->get();
        }

        if ($mode === 'team' && $trigger->registration_role !== null) {
            $leaderId = $trigger->registration_role === RegistrationRole::Leader
                ? (string) $trigger->id
                : $trigger->leader_form_answer_id;

            if ($leaderId === null || $leaderId === '') {
                return $this->wrapSinglePeer($trigger);
            }

            return FormAnswer::query()
                ->where('form_id', $form->id)
                ->where(function ($q) use ($leaderId): void {
                    $q->where('id', $leaderId)
                        ->orWhere('leader_form_answer_id', $leaderId);
                })
                ->where(function ($q): void {
                    $q->whereNull('review_status')
                        ->orWhere('review_status', '!=', FormAnswerReviewStatus::Rejected->value);
                })
                ->get();
        }

        return $this->wrapSinglePeer($trigger);
    }

    private function resolveRegistrationMode(Form $form): ?string
    {
        $metadata = $form->metadata;
        if (! is_array($metadata)) {
            return null;
        }

        $mode = $metadata['registration_mode'] ?? null;

        return is_string($mode) ? strtolower($mode) : null;
    }

    /**
     *
     * @return Collection<int, FormAnswer>
     *
     */
    private function wrapSinglePeer(FormAnswer $trigger): Collection
    {
        if ($trigger->review_status === FormAnswerReviewStatus::Rejected) {
            return collect();
        }

        return collect([$trigger]);
    }
}
