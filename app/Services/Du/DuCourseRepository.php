<?php

namespace App\Services\Du;

use App\Models\DuCourse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DuCourseRepository
{
    /** @return Collection<int, DuCourse> */
    public function active(): Collection
    {
        return DuCourse::query()->where('is_active', true)->orderBy('title')->get();
    }

    /** Full cached catalog regardless of active state, for resolving stored answers. */
    public function cached(): Collection
    {
        return DuCourse::query()->orderBy('title')->get();
    }

    /** @return list<string> */
    public function activeSelectableValues(): array
    {
        $active = $this->active();

        return array_values(array_unique([
            ...$active->pluck('uid')->all(),
            ...$active->pluck('title')->all(),
        ]));
    }

    /**
     * Replaces the cached catalog with the given courses; rows missing from the
     * input are kept but deactivated so previously submitted answers still resolve.
     *
     * @param  list<DuCourseOption>  $courses
     */
    public function replace(array $courses): void
    {
        $uids = array_map(static fn (DuCourseOption $course): string => $course->uid, $courses);
        $syncedAt = now();

        DB::transaction(function () use ($courses, $uids, $syncedAt): void {
            if ($uids === []) {
                DuCourse::query()->update(['is_active' => false]);

                return;
            }

            $rows = array_map(static fn (DuCourseOption $course): array => [
                'id' => (string) Str::uuid(),
                'uid' => $course->uid,
                'title' => $course->title,
                'is_active' => true,
                'last_synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ], $courses);

            DuCourse::query()->upsert($rows, ['uid'], ['title', 'is_active', 'last_synced_at', 'updated_at']);

            DuCourse::query()->whereNotIn('uid', $uids)->update(['is_active' => false]);
        });
    }

    public function hasActive(): bool
    {
        return DuCourse::query()->where('is_active', true)->exists();
    }

    /**
     * Resolves one course by uid or title for fail-closed paths. An exact uid wins;
     * a title resolves only when exactly one row carries it, so zero or multiple
     * matches return null instead of picking an arbitrary course.
     */
    public function findUniqueByValue(string $value, bool $activeOnly): ?DuCourse
    {
        $matches = DuCourse::query()
            ->where(static function (Builder $query) use ($value): void {
                $query->where('uid', $value)->orWhere('title', $value);
            })
            ->when($activeOnly, static fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderByRaw('case when uid = ? then 0 else 1 end', [$value])
            ->limit(2)
            ->get();

        $first = $matches->first();
        if ($first === null) {
            return null;
        }

        return $first->uid === $value || $matches->count() === 1 ? $first : null;
    }

    /**
     * Batch form of {@see findUniqueByValue()}: one query for every submitted value,
     * keyed by the submitted value. Exact uid wins; a title resolves only when exactly
     * one row carries it, so zero or multiple matches yield null. Duplicate submitted
     * values are collapsed.
     *
     * @param  list<string>  $values
     * @return array<string, ?DuCourse>
     */
    public function findUniqueForValues(array $values, bool $activeOnly): array
    {
        $uniqueValues = array_values(array_unique($values));
        if ($uniqueValues === []) {
            return [];
        }

        $rows = DuCourse::query()
            ->where(static function (Builder $query) use ($uniqueValues): void {
                $query->whereIn('uid', $uniqueValues)->orWhereIn('title', $uniqueValues);
            })
            ->when($activeOnly, static fn (Builder $query): Builder => $query->where('is_active', true))
            ->get(['uid', 'title']);

        $byUid = [];
        $byTitle = [];
        foreach ($rows as $row) {
            $byUid[$row->uid] = $row;
            $byTitle[$row->title][] = $row;
        }

        $resolved = [];
        foreach ($uniqueValues as $value) {
            $resolved[$value] = $byUid[$value] ?? (count($byTitle[$value] ?? []) === 1 ? $byTitle[$value][0] : null);
        }

        return $resolved;
    }
}
