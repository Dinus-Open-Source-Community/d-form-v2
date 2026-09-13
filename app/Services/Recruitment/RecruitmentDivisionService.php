<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class RecruitmentDivisionService
{
    /**
     * @return Collection<int, RecruitmentDivision>
     */
    public function listActiveOrdered(): Collection
    {
        return RecruitmentDivision::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, RecruitmentDivision>
     */
    public function listAllOrdered(): Collection
    {
        return RecruitmentDivision::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount('interviewerAssignments')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RecruitmentDivision $division, array $data): RecruitmentDivision
    {
        $division->update($data);

        return $division->fresh();
    }

    public function assignInterviewer(RecruitmentDivision $division, User $user): RecruitmentInterviewerDivision
    {
        return RecruitmentInterviewerDivision::query()->firstOrCreate([
            'user_id' => $user->id,
            'recruitment_division_id' => $division->id,
        ]);
    }

    public function unassignInterviewer(RecruitmentInterviewerDivision $assignment): void
    {
        $assignment->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function toInertiaArray(RecruitmentDivision $division): array
    {
        return [
            'id' => $division->id,
            'code' => $division->code,
            'name' => $division->name,
            'description' => $division->description,
            'is_active' => $division->is_active,
            'sort_order' => $division->sort_order,
            'interviewer_assignments_count' => $division->interviewer_assignments_count
                ?? $division->interviewerAssignments()->count(),
        ];
    }
}
