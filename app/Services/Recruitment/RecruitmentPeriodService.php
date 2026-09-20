<?php

namespace App\Services\Recruitment;

use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\Recruitment\RecruitmentRegistrationSequence;
use App\Models\User;
use App\Services\User\UserAvatarService;
use App\Support\PublicStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class RecruitmentPeriodService
{
    public function __construct(
        private readonly RecruitmentSlugGenerator $slugGenerator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 15, ?User $user = null): LengthAwarePaginator
    {
        $query = RecruitmentPeriod::query()->with('creator')->orderByDesc('created_at');

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

        if ($user !== null && ! $user->hasRole('super-admin') && ! $user->can('recruitment.periods.list')) {
            $query->where(function ($q) use ($user): void {
                $q->where('created_by', $user->id)->orWhereNull('created_by');
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $banner = null): RecruitmentPeriod
    {
        unset($data['banner']);

        $data['slug'] = $this->slugGenerator->generateForName($data['name']);

        if ($banner !== null) {
            $data['banner'] = $banner->store('recruitment/banners', 'public');
        }

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
    public function update(RecruitmentPeriod $period, array $data, ?UploadedFile $banner = null): RecruitmentPeriod
    {
        unset($data['banner']);

        if (isset($data['name']) && $data['name'] !== $period->name) {
            $data['slug'] = $this->slugGenerator->generateForName($data['name'], $period->id);
        }

        if ($banner !== null) {
            if ($period->banner) {
                Storage::disk('public')->delete($period->banner);
            }

            $data['banner'] = $banner->store('recruitment/banners', 'public');
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
    public function toInertiaArray(RecruitmentPeriod $period, ?User $user = null): array
    {
        $period->loadMissing('creator');
        $creator = $period->creator;

        return [
            'id' => $period->id,
            'name' => $period->name,
            'slug' => $period->slug,
            'status' => $period->status->value,
            'status_label' => $period->status->label(),
            'description' => $period->description,
            'banner_url' => PublicStorage::url($period->banner),
            'registration_opens_at' => $period->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $period->registration_closes_at?->toIso8601String(),
            'interview_starts_at' => $period->interview_starts_at?->toDateString(),
            'interview_ends_at' => $period->interview_ends_at?->toDateString(),
            'finalization_deadline_at' => $period->finalization_deadline_at?->toDateString(),
            'applications_count' => $period->applications_count ?? $period->applications()->count(),
            'creator' => $creator instanceof User ? [
                'name' => $creator->name,
                'avatar_url' => UserAvatarService::resolvePublicUrl($creator->avatar),
            ] : null,
            'created_at' => $period->created_at?->toIso8601String(),
            'updated_at' => $period->updated_at?->toIso8601String(),
            'can_edit' => $user !== null && ($user->hasRole('super-admin') || $user->can('recruitment.periods.edit') || ($period->created_by !== null && (string) $period->created_by === (string) $user->id)),
            'can_delete' => $user !== null && ($user->hasRole('super-admin') || $user->can('recruitment.periods.delete')),
        ];
    }
}
