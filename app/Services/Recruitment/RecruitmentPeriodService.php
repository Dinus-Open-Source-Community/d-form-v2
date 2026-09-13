<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentRegistrationSequence;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class RecruitmentPeriodService
{
    public function __construct(
        private readonly RecruitmentSlugGenerator $slugGenerator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $query = RecruitmentPeriod::query()->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RecruitmentPeriod
    {
        $data['slug'] = $this->slugGenerator->generateForName($data['name']);

        return DB::transaction(function () use ($data): RecruitmentPeriod {
            $period = RecruitmentPeriod::query()->create($data);

            RecruitmentRegistrationSequence::query()->create([
                'recruitment_period_id' => $period->id,
                'last_sequence' => 0,
            ]);

            return $period;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RecruitmentPeriod $period, array $data): RecruitmentPeriod
    {
        if (isset($data['name']) && $data['name'] !== $period->name) {
            $data['slug'] = $this->slugGenerator->generateForName($data['name'], $period->id);
        }

        $period->update($data);

        return $period->fresh();
    }

    public function open(RecruitmentPeriod $period): RecruitmentPeriod
    {
        $period->update(['status' => RecruitmentPeriodStatus::Open]);

        return $period->fresh();
    }

    public function close(RecruitmentPeriod $period): RecruitmentPeriod
    {
        $period->update(['status' => RecruitmentPeriodStatus::Closed]);

        return $period->fresh();
    }

    public function archive(RecruitmentPeriod $period): RecruitmentPeriod
    {
        $period->update(['status' => RecruitmentPeriodStatus::Archived]);

        return $period->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function toInertiaArray(RecruitmentPeriod $period): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'slug' => $period->slug,
            'status' => $period->status->value,
            'status_label' => $period->status->label(),
            'description' => $period->description,
            'registration_opens_at' => $period->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $period->registration_closes_at?->toIso8601String(),
            'interview_starts_at' => $period->interview_starts_at?->toDateString(),
            'interview_ends_at' => $period->interview_ends_at?->toDateString(),
            'finalization_deadline_at' => $period->finalization_deadline_at?->toDateString(),
            'applications_count' => $period->applications_count ?? $period->applications()->count(),
            'created_at' => $period->created_at?->toIso8601String(),
            'updated_at' => $period->updated_at?->toIso8601String(),
        ];
    }
}
