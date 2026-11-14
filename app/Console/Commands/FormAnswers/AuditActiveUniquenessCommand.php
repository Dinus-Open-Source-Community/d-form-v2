<?php

namespace App\Console\Commands\FormAnswers;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Audit active form-answer uniqueness and marker completeness without writes. */
final class AuditActiveUniquenessCommand extends Command
{
    private bool $activeMarkerColumnChecked = false;

    private bool $activeMarkerColumnAvailable = false;

    protected $signature = 'form-answers:audit-active-uniqueness';

    protected $description = 'Audit active form-answer duplicates and marker completeness without changing data.';

    public function handle(): int
    {
        $userDuplicateGroups = $this->countUserDuplicateGroups();
        $guestDuplicateGroups = $this->countGuestDuplicateGroups();
        $unmarkedActiveRows = $this->countUnmarkedActiveRows();

        $this->line('Active user duplicate groups: '.$userDuplicateGroups);
        $this->line('Active guest duplicate groups: '.$guestDuplicateGroups);

        if ($unmarkedActiveRows === null) {
            $this->line('Unmarked active rows: deferred until is_active exists');
        } else {
            $this->line('Unmarked active rows: '.$unmarkedActiveRows);
        }

        if ($userDuplicateGroups > 0 || $guestDuplicateGroups > 0 || ($unmarkedActiveRows ?? 0) > 0) {
            $this->error('Active uniqueness audit found issues; no rows were changed.');

            return self::FAILURE;
        }

        $this->info('Active uniqueness audit passed; no rows were changed.');

        return self::SUCCESS;
    }

    private function countUserDuplicateGroups(): int
    {
        $groups = $this->activeUniquenessScope()
            ->whereNotNull('user_id')
            ->select(['user_id', 'form_id'])
            ->groupBy(['user_id', 'form_id'])
            ->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($groups, 'active_user_duplicates')->count();
    }

    private function countGuestDuplicateGroups(): int
    {
        $groups = $this->activeUniquenessScope()
            ->whereNull('user_id')
            ->whereNotNull('invited_email')
            ->select(['form_id', 'invited_email'])
            ->groupBy(['form_id', 'invited_email'])
            ->havingRaw('COUNT(*) > 1');

        return DB::query()->fromSub($groups, 'active_guest_duplicates')->count();
    }

    private function countUnmarkedActiveRows(): ?int
    {
        if (! $this->hasActiveMarkerColumn()) {
            return null;
        }

        return $this->activeAnswers()->whereNull('is_active')->count();
    }

    private function activeUniquenessScope(): Builder
    {
        if ($this->hasActiveMarkerColumn()) {
            return DB::table('form_answers')->where('is_active', 1);
        }

        return $this->activeAnswers();
    }

    private function hasActiveMarkerColumn(): bool
    {
        if (! $this->activeMarkerColumnChecked) {
            $this->activeMarkerColumnAvailable = Schema::hasColumn('form_answers', 'is_active');
            $this->activeMarkerColumnChecked = true;
        }

        return $this->activeMarkerColumnAvailable;
    }

    private function activeAnswers(): Builder
    {
        return DB::table('form_answers')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'rejected');
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('registration_role')
                    ->orWhere('registration_role', '!=', 'member')
                    ->orWhereNull('member_confirmation_status')
                    ->orWhereNotIn('member_confirmation_status', ['rejected', 'expired']);
            });
    }
}
